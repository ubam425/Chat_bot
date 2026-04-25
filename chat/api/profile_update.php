<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$currentUser = requireAuth();

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastNamePaterno = trim((string) ($_POST['last_name_paterno'] ?? ''));
$lastNameMaterno = trim((string) ($_POST['last_name_materno'] ?? ''));
$phone = sanitizePhone((string) ($_POST['phone'] ?? ''));
$email = normalizeEmail((string) ($_POST['email'] ?? ''));
$removeAvatar = (string) ($_POST['remove_avatar'] ?? '0') === '1';

if ($firstName === '' || $lastNamePaterno === '' || $lastNameMaterno === '' || $phone === '' || $email === '') {
    jsonResponse(['ok' => false, 'message' => 'Completa todos los campos del perfil.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'message' => 'Correo invalido.'], 422);
}

$pdo = db();
$avatarPathToSave = (string) ($currentUser['avatar_path'] ?? '');
$oldAvatarPath = $avatarPathToSave;
$newUploadedAvatar = false;

$existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
$existsStmt->execute([
    'email' => $email,
    'id' => (int) $currentUser['id'],
]);

if ($existsStmt->fetch()) {
    jsonResponse(['ok' => false, 'message' => 'Ese correo ya esta en uso por otro usuario.'], 409);
}

if (isset($_FILES['avatar']) && (int) $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['avatar'];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'message' => 'Error al subir foto de perfil. Codigo: ' . $errorCode], 422);
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        jsonResponse(['ok' => false, 'message' => 'La foto debe pesar maximo 5MB.'], 422);
    }

    $tempPath = (string) ($file['tmp_name'] ?? '');
    if ($tempPath === '' || !is_uploaded_file($tempPath)) {
        jsonResponse(['ok' => false, 'message' => 'Archivo de foto no valido.'], 422);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tempPath) : 'application/octet-stream';
    if ($finfo) {
        finfo_close($finfo);
    }

    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!array_key_exists($mimeType, $mimeToExt)) {
        jsonResponse(['ok' => false, 'message' => 'Solo se permiten imagenes JPG, PNG, WEBP o GIF.'], 422);
    }

    ensureUploadsPath();
    $relativeDir = 'uploads/avatars/' . date('Y') . '/' . date('m');
    $absoluteDir = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0775, true);
    }

    $fileName = 'avatar_' . (int) $currentUser['id'] . '_' . bin2hex(random_bytes(10)) . '.' . $mimeToExt[$mimeType];
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tempPath, $absolutePath)) {
        jsonResponse(['ok' => false, 'message' => 'No se pudo guardar la foto de perfil.'], 500);
    }

    $avatarPathToSave = $relativeDir . '/' . $fileName;
    $newUploadedAvatar = true;
}

if ($removeAvatar && !$newUploadedAvatar) {
    $avatarPathToSave = '';
}

$update = $pdo->prepare(
    'UPDATE users
     SET first_name = :first_name,
         last_name_paterno = :last_name_paterno,
         last_name_materno = :last_name_materno,
         phone = :phone,
         email = :email,
         avatar_path = :avatar_path,
         updated_at = NOW()
     WHERE id = :id'
);
$update->execute([
    'first_name' => $firstName,
    'last_name_paterno' => $lastNamePaterno,
    'last_name_materno' => $lastNameMaterno,
    'phone' => $phone,
    'email' => $email,
    'avatar_path' => $avatarPathToSave !== '' ? $avatarPathToSave : null,
    'id' => (int) $currentUser['id'],
]);

if (($removeAvatar || $newUploadedAvatar) && $oldAvatarPath !== '' && $oldAvatarPath !== $avatarPathToSave) {
    deleteLocalAvatar($oldAvatarPath);
}

$freshStmt = $pdo->prepare('SELECT id, first_name, last_name_paterno, last_name_materno, phone, email, avatar_path FROM users WHERE id = :id LIMIT 1');
$freshStmt->execute(['id' => (int) $currentUser['id']]);
$fresh = $freshStmt->fetch();

if (!$fresh) {
    jsonResponse(['ok' => false, 'message' => 'No se pudo cargar el perfil actualizado.'], 500);
}

jsonResponse([
    'ok' => true,
    'message' => 'Perfil actualizado correctamente.',
    'user' => [
        'id' => (int) $fresh['id'],
        'name' => formatFullName($fresh),
        'first_name' => (string) $fresh['first_name'],
        'last_name_paterno' => (string) $fresh['last_name_paterno'],
        'last_name_materno' => (string) $fresh['last_name_materno'],
        'phone' => (string) $fresh['phone'],
        'email' => (string) $fresh['email'],
        'avatar_url' => !empty($fresh['avatar_path']) ? str_replace('\\', '/', (string) $fresh['avatar_path']) : null,
    ],
]);

function deleteLocalAvatar(string $avatarRelativePath): void
{
    $safeRelative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $avatarRelativePath);
    $absolutePath = BASE_PATH . DIRECTORY_SEPARATOR . $safeRelative;

    $uploadsReal = realpath(UPLOADS_PATH);
    $avatarReal = realpath($absolutePath);

    if ($uploadsReal === false || $avatarReal === false) {
        return;
    }

    if (str_starts_with($avatarReal, $uploadsReal) && is_file($avatarReal)) {
        @unlink($avatarReal);
    }
}
