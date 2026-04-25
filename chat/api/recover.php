<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');

$data = readJsonInput();
if ($data === []) {
    $data = $_POST;
}

$email = normalizeEmail((string) ($data['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'message' => 'Ingresa un correo valido.'], 422);
}

try {
    $user = ChatService::getUserByEmail($email);

    if ($user && (int) ($user['is_bot'] ?? 0) === 0) {
        $newPassword = generateRandomPassword(12);
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = db()->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'hash' => $hash,
            'id' => (int) $user['id'],
        ]);

        $fullName = formatFullName($user);
        $subject = APP_NAME . ' - Recuperacion de contrasena';
        $htmlBody = '<h2>Recuperacion de acceso</h2>'
            . '<p>Hola <strong>' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</strong>, tu nueva contrasena temporal es:</p>'
            . '<p style="font-size:18px;"><strong>' . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Usala para iniciar sesion y luego registra biometria para acceso mas rapido.</p>';
        $textBody = "Tu nueva contrasena temporal es: " . $newPassword;

        $mailer = new SmtpMailer(mailConfig());
        $mailer->send($email, $fullName, $subject, $htmlBody, $textBody);
    }

    jsonResponse([
        'ok' => true,
        'message' => 'Si el correo existe, ya enviamos una nueva contrasena temporal.',
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'Error al recuperar contrasena: ' . $error->getMessage()], 500);
}
