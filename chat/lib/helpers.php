<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

function boolEnv(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireMethod(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        jsonResponse(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
    }
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function normalizeEmail(string $email): string
{
    return mb_strtolower(trim($email));
}

function sanitizePhone(string $phone): string
{
    return preg_replace('/[^0-9+\-()\s]/', '', trim($phone)) ?? '';
}

function generateRandomPassword(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*';
    $max = strlen($chars) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }

    return $password;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder > 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return (string) base64_decode(strtr($data, '-_', '+/'));
}

function ensureUploadsPath(): void
{
    if (!is_dir(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0775, true);
    }
}

function formatFullName(array $user): string
{
    $parts = [
        trim((string) ($user['first_name'] ?? '')),
        trim((string) ($user['last_name_paterno'] ?? '')),
        trim((string) ($user['last_name_materno'] ?? '')),
    ];

    $parts = array_values(array_filter($parts, static fn ($part): bool => $part !== ''));
    return $parts !== [] ? implode(' ', $parts) : (string) ($user['email'] ?? 'Usuario');
}

function nowIso(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}
