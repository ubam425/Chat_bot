<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');

if (!boolEnv('ALLOW_AUTO_LOGIN', false)) {
    jsonResponse(['ok' => false, 'message' => 'Auto login deshabilitado.'], 403);
}

$testEmail = normalizeEmail((string) (env('TEST_USER_EMAIL', 'pruebas@chatubam.local') ?? 'pruebas@chatubam.local'));

$stmt = db()->prepare('SELECT id, auto_login_enabled FROM users WHERE email = :email AND is_bot = 0 LIMIT 1');
$stmt->execute(['email' => $testEmail]);
$user = $stmt->fetch();

if (!$user || (int) ($user['auto_login_enabled'] ?? 0) !== 1) {
    jsonResponse(['ok' => false, 'message' => 'No existe usuario de prueba habilitado.'], 404);
}

loginUser((int) $user['id']);

jsonResponse([
    'ok' => true,
    'message' => 'Sesion iniciada con usuario de prueba.',
]);
