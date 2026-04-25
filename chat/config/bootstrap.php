<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separatorPos = strpos($trimmed, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $separatorPos));
            $value = trim(substr($trimmed, $separatorPos + 1));

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (str_ends_with($value, $quote)) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }

        return $default;
    }
}

loadEnvFile(BASE_PATH . DIRECTORY_SEPARATOR . '.env');

date_default_timezone_set((string) (env('APP_TIMEZONE', 'America/Mexico_City') ?? 'America/Mexico_City'));

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 12,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!defined('APP_NAME')) {
    define('APP_NAME', (string) (env('APP_NAME', 'ChatUbam') ?? 'ChatUbam'));
}

if (!defined('APP_URL')) {
    define('APP_URL', (string) (env('APP_URL', 'http://localhost/chat') ?? 'http://localhost/chat'));
}

if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
}

if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024);
}
