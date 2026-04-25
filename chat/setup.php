<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/helpers.php';

$status = 'ok';
$message = 'Instalacion completada.';
$testUserEmail = normalizeEmail((string) (env('TEST_USER_EMAIL', 'pruebas@chatubam.local') ?? 'pruebas@chatubam.local'));
$testUserPassword = (string) (env('TEST_USER_PASSWORD', 'Prueba123!') ?? 'Prueba123!');

try {
    $pdo = db();
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No se pudo leer schema.sql');
    }

    $pdo->exec($schema);

    $botId = ensureUser($pdo, [
        'first_name' => 'ChatUbam',
        'last_name_paterno' => 'Bot',
        'last_name_materno' => 'Asistente',
        'phone' => '0000000000',
        'email' => 'bot@chatubam.local',
        'password' => generateRandomPassword(16),
        'is_bot' => 1,
        'auto_login_enabled' => 0,
    ]);

    $testUserId = ensureUser($pdo, [
        'first_name' => 'Usuario',
        'last_name_paterno' => 'Prueba',
        'last_name_materno' => 'ChatUbam',
        'phone' => '+52 000 000 0000',
        'email' => $testUserEmail,
        'password' => $testUserPassword,
        'is_bot' => 0,
        'auto_login_enabled' => 1,
    ]);

    $checkStmt = $pdo->prepare(
        'SELECT id FROM messages
         WHERE sender_id = :bot_id AND receiver_id = :user_id
         LIMIT 1'
    );
    $checkStmt->execute([
        'bot_id' => $botId,
        'user_id' => $testUserId,
    ]);

    if (!$checkStmt->fetch()) {
        $insertMsg = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, body, attachment_type, created_at)
             VALUES (:sender_id, :receiver_id, :body, :attachment_type, NOW())'
        );

        $insertMsg->execute([
            'sender_id' => $botId,
            'receiver_id' => $testUserId,
            'body' => 'Hola, soy ChatUbam Bot. Ya quedo listo tu usuario de prueba. Escribe "ayuda" para ver ejemplos.',
            'attachment_type' => 'none',
        ]);
    }

    $message = 'Instalacion completada. Usuario de prueba: ' . $testUserEmail . ' | Password: ' . $testUserPassword;
} catch (Throwable $error) {
    $status = 'error';
    $message = $error->getMessage();
}

function ensureUser(PDO $pdo, array $data): int
{
    $select = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $select->execute(['email' => $data['email']]);
    $existing = $select->fetch();

    if ($existing) {
        $update = $pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name_paterno = :last_name_paterno,
                 last_name_materno = :last_name_materno,
                 phone = :phone,
                 password_hash = :password_hash,
                 is_bot = :is_bot,
                 auto_login_enabled = :auto_login_enabled,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $update->execute([
            'first_name' => $data['first_name'],
            'last_name_paterno' => $data['last_name_paterno'],
            'last_name_materno' => $data['last_name_materno'],
            'phone' => $data['phone'],
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'is_bot' => $data['is_bot'],
            'auto_login_enabled' => $data['auto_login_enabled'],
            'id' => (int) $existing['id'],
        ]);

        return (int) $existing['id'];
    }

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
            :is_bot,
            :auto_login_enabled,
            NOW(),
            NOW()
        )'
    );

    $insert->execute([
        'first_name' => $data['first_name'],
        'last_name_paterno' => $data['last_name_paterno'],
        'last_name_materno' => $data['last_name_materno'],
        'phone' => $data['phone'],
        'email' => $data['email'],
        'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        'is_bot' => $data['is_bot'],
        'auto_login_enabled' => $data['auto_login_enabled'],
    ]);

    return (int) $pdo->lastInsertId();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup ChatUbam</title>
    <style>
        body { font-family: "Segoe UI", sans-serif; background: #f4f7f9; margin: 0; padding: 40px; }
        .card { max-width: 760px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .ok { color: #136f45; }
        .error { color: #a50000; }
        code { background: #f0f4f8; padding: 3px 7px; border-radius: 6px; }
        a { color: #136f45; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Setup ChatUbam</h1>
        <p class="<?= $status === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <p>Si todo salio bien, abre <a href="index.php">index.php</a> para iniciar sesion.</p>
    </div>
</body>
</html>
