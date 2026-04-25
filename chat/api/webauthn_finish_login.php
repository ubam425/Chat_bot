<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$data = readJsonInput();

$email = normalizeEmail((string) ($data['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'message' => 'Ingresa un correo valido.'], 422);
}

try {
    $userId = WebAuthn::finishLogin($email, $data);
    if ($userId === null) {
        jsonResponse(['ok' => false, 'message' => 'No se pudo autenticar con biometria.'], 401);
    }

    loginUser($userId);

    $stmt = db()->prepare('SELECT id, first_name, last_name_paterno, last_name_materno, email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Inicio de sesion biometrico exitoso.',
        'user' => [
            'id' => (int) $user['id'],
            'name' => formatFullName($user),
            'email' => (string) $user['email'],
        ],
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'Error en login biometrico: ' . $error->getMessage()], 500);
}
