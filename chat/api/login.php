<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');

$data = readJsonInput();
if ($data === []) {
    $data = $_POST;
}

$email = normalizeEmail((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse(['ok' => false, 'message' => 'Ingresa correo y contrasena.'], 422);
}

try {
    $user = ChatService::getUserByEmail($email);

    if (!$user || (int) ($user['is_bot'] ?? 0) === 1) {
        jsonResponse(['ok' => false, 'message' => 'Credenciales invalidas.'], 401);
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        jsonResponse(['ok' => false, 'message' => 'Credenciales invalidas.'], 401);
    }

    loginUser((int) $user['id']);

    jsonResponse([
        'ok' => true,
        'message' => 'Inicio de sesion correcto.',
        'user' => [
            'id' => (int) $user['id'],
            'name' => formatFullName($user),
            'email' => (string) $user['email'],
        ],
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'Error en login: ' . $error->getMessage()], 500);
}
