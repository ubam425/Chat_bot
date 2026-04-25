<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$data = readJsonInput();

$email = normalizeEmail((string) ($data['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'message' => 'Ingresa un correo valido para usar biometria.'], 422);
}

try {
    $options = WebAuthn::beginLogin($email);

    jsonResponse([
        'ok' => true,
        'options' => $options,
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => $error->getMessage()], 404);
}
