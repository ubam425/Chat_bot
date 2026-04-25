<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function mailConfig(): array
{
    return [
        'host' => (string) (env('SMTP_HOST', '') ?? ''),
        'port' => (int) (env('SMTP_PORT', '465') ?? 465),
        'secure' => strtolower((string) (env('SMTP_SECURE', 'ssl') ?? 'ssl')),
        'username' => (string) (env('SMTP_USER', '') ?? ''),
        'password' => (string) (env('SMTP_PASS', '') ?? ''),
        'from_email' => (string) (env('SMTP_FROM_EMAIL', env('SMTP_USER', 'no-reply@example.com')) ?? 'no-reply@example.com'),
        'from_name' => (string) (env('SMTP_FROM_NAME', APP_NAME) ?? APP_NAME),
        'timeout' => 20,
    ];
}
