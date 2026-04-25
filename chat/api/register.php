<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');

$data = readJsonInput();
if ($data === []) {
    $data = $_POST;
}

$firstName = trim((string) ($data['first_name'] ?? ''));
$lastNamePaterno = trim((string) ($data['last_name_paterno'] ?? ''));
$lastNameMaterno = trim((string) ($data['last_name_materno'] ?? ''));
$phone = sanitizePhone((string) ($data['phone'] ?? ''));
$email = normalizeEmail((string) ($data['email'] ?? ''));

if ($firstName === '' || $lastNamePaterno === '' || $lastNameMaterno === '' || $phone === '' || $email === '') {
    jsonResponse(['ok' => false, 'message' => 'Completa todos los campos requeridos.'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'message' => 'Correo electronico invalido.'], 422);
}

try {
    $pdo = db();

    $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute(['email' => $email]);
    if ($exists->fetch()) {
        jsonResponse(['ok' => false, 'message' => 'Ya existe una cuenta con ese correo.'], 409);
    }

    $generatedPassword = generateRandomPassword(12);
    $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

    $insert = $pdo->prepare(
        'INSERT INTO users (
            first_name,
            last_name_paterno,
            last_name_materno,
            phone,
            email,
            password_hash,
            is_bot,
            auto_login_enabled,
            created_at,
            updated_at
        ) VALUES (
            :first_name,
            :last_name_paterno,
            :last_name_materno,
            :phone,
            :email,
            :password_hash,
            0,
            0,
            NOW(),
            NOW()
        )'
    );

    $insert->execute([
        'first_name' => $firstName,
        'last_name_paterno' => $lastNamePaterno,
        'last_name_materno' => $lastNameMaterno,
        'phone' => $phone,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    $mailer = new SmtpMailer(mailConfig());
    $fullName = trim($firstName . ' ' . $lastNamePaterno . ' ' . $lastNameMaterno);

    $subject = APP_NAME . ' - Tu cuenta ha sido creada';
    $htmlBody = '<h2>Bienvenido a ' . APP_NAME . '</h2>'
        . '<p>Hola <strong>' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
        . '<p>Tu cuenta fue registrada correctamente. Esta es tu contrasena temporal:</p>'
        . '<p style="font-size:18px;"><strong>' . htmlspecialchars($generatedPassword, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p>Inicia sesion con tu correo y esta contrasena. Luego puedes usar acceso biometrico (huella/rostro) para entrar sin escribirla.</p>'
        . '<p>URL: <a href="' . htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') . '</a></p>';

    $textBody = "Bienvenido a " . APP_NAME . "\n"
        . "Tu contrasena temporal es: " . $generatedPassword . "\n"
        . "Acceso: " . APP_URL;

    $emailSent = $mailer->send($email, $fullName, $subject, $htmlBody, $textBody);

    $botId = ChatService::findBotId();
    if ($botId !== null) {
        ChatService::createMessage(
            $botId,
            $newUserId,
            'Hola ' . $firstName . ', soy ChatUbam Bot. Tu cuenta ya esta activa. Escribe "ayuda" para comenzar.',
            null,
            'none'
        );
    }

    $response = [
        'ok' => true,
        'message' => $emailSent
            ? 'Registro exitoso. Te enviamos tu contrasena al correo.'
            : 'Registro exitoso, pero no se pudo enviar el correo. Revisa SMTP y usa recuperar contrasena.',
        'email_sent' => $emailSent,
    ];

    if (boolEnv('APP_DEBUG', false)) {
        $response['debug_password'] = $generatedPassword;
    }

    jsonResponse($response, 201);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'Error al registrar: ' . $error->getMessage()], 500);
}
