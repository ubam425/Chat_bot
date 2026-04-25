<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');
$user = requireAuth();

$stmt = db()->prepare('SELECT COUNT(*) AS total FROM webauthn_credentials WHERE user_id = :user_id');
$stmt->execute(['user_id' => (int) $user['id']]);
$row = $stmt->fetch();

jsonResponse([
    'ok' => true,
    'user' => [
        'id' => (int) $user['id'],
        'name' => formatFullName($user),
        'first_name' => (string) $user['first_name'],
        'last_name_paterno' => (string) $user['last_name_paterno'],
        'last_name_materno' => (string) $user['last_name_materno'],
        'phone' => (string) $user['phone'],
        'email' => (string) $user['email'],
        'avatar_url' => !empty($user['avatar_path']) ? str_replace('\\', '/', (string) $user['avatar_path']) : null,
    ],
    'passkeys' => (int) ($row['total'] ?? 0),
]);
