<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$user = requireAuth();

try {
    $options = WebAuthn::beginRegistration(
        (int) $user['id'],
        (string) $user['email'],
        formatFullName($user)
    );

    jsonResponse([
        'ok' => true,
        'options' => $options,
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'No se pudo iniciar el registro biometrico: ' . $error->getMessage()], 500);
}
