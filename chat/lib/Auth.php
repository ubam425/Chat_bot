<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function currentUser(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, first_name, last_name_paterno, last_name_materno, phone, email, avatar_path, is_bot FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function requireAuth(): array
{
    $user = currentUser();
    if ($user === null) {
        jsonResponse(['ok' => false, 'message' => 'No autorizado.'], 401);
    }

    return $user;
}

function loginUser(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_at'] = time();
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}
