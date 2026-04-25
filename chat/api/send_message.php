<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$currentUser = requireAuth();

$receiverId = (int) ($_POST['receiver_id'] ?? 0);
$body = trim((string) ($_POST['message'] ?? ''));

if ($receiverId <= 0) {
    jsonResponse(['ok' => false, 'message' => 'Falta receptor del mensaje.'], 422);
}

if ($receiverId === (int) $currentUser['id']) {
    jsonResponse(['ok' => false, 'message' => 'No puedes enviarte mensajes a ti mismo.'], 422);
}

$receiverStmt = db()->prepare('SELECT id, first_name, last_name_paterno, last_name_materno, is_bot FROM users WHERE id = :id LIMIT 1');
$receiverStmt->execute(['id' => $receiverId]);
$receiver = $receiverStmt->fetch();
if (!$receiver) {
    jsonResponse(['ok' => false, 'message' => 'El receptor no existe.'], 404);
}

$attachmentPath = null;
$attachmentType = 'none';
$originalName = null;
$mimeType = null;
$attachmentSize = null;

if (isset($_FILES['attachment']) && (int) $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'message' => 'Error al subir archivo. Codigo: ' . $errorCode], 422);
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_SIZE) {
        jsonResponse(['ok' => false, 'message' => 'El archivo excede el limite de 20MB o esta vacio.'], 422);
    }

    $tempPath = (string) ($file['tmp_name'] ?? '');
    if ($tempPath === '' || !is_uploaded_file($tempPath)) {
        jsonResponse(['ok' => false, 'message' => 'Archivo temporal no valido.'], 422);
    }

    $originalName = basename((string) ($file['name'] ?? 'archivo'));
    $originalName = preg_replace('/[^A-Za-z0-9._\-\s]/', '_', $originalName) ?? 'archivo';

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tempPath) : 'application/octet-stream';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (str_starts_with($mimeType, 'image/')) {
        $attachmentType = 'image';
    } elseif (str_starts_with($mimeType, 'video/')) {
        $attachmentType = 'video';
    } else {
        $attachmentType = 'file';
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $blocked = ['php', 'phtml', 'phar', 'exe', 'cmd', 'bat', 'sh'];
    if ($extension === '' || in_array($extension, $blocked, true)) {
        $extension = 'bin';
    }

    ensureUploadsPath();
    $relativeDir = 'uploads/' . date('Y') . '/' . date('m');
    $absoluteDir = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0775, true);
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tempPath, $absolutePath)) {
        jsonResponse(['ok' => false, 'message' => 'No se pudo guardar el archivo adjunto.'], 500);
    }

    $attachmentPath = $relativeDir . '/' . $fileName;
    $attachmentSize = $size;
}

if ($body === '' && $attachmentPath === null) {
    jsonResponse(['ok' => false, 'message' => 'Escribe un mensaje o adjunta un archivo.'], 422);
}

try {
    $messageId = ChatService::createMessage(
        (int) $currentUser['id'],
        $receiverId,
        $body !== '' ? $body : null,
        $attachmentPath,
        $attachmentType,
        $originalName,
        $mimeType,
        $attachmentSize
    );

    $sentMessage = ChatService::getMessageById($messageId);
    $botReply = null;

    if ((int) ($receiver['is_bot'] ?? 0) === 1) {
        $replyText = Chatbot::reply($body !== '' ? $body : 'adjunto');
        $botMessageId = ChatService::createMessage(
            $receiverId,
            (int) $currentUser['id'],
            $replyText,
            null,
            'none'
        );
        $botReply = ChatService::getMessageById($botMessageId);
    }

    jsonResponse([
        'ok' => true,
        'message' => $sentMessage,
        'bot_reply' => $botReply,
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'No se pudo enviar el mensaje: ' . $error->getMessage()], 500);
}
