<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$user = requireAuth();
$data = readJsonInput();

try {
    $success = WebAuthn::finishRegistration((int) $user['id'], (string) $user['email'], $data);

    if (!$success) {
        jsonResponse(['ok' => false, 'message' => 'No se pudo validar la biometria.'], 422);
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Biometria registrada correctamente en este dispositivo.',
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'Error en registro biometrico: ' . $error->getMessage()], 500);
}
