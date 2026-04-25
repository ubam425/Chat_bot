<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

final class WebAuthn
{
    public static function beginRegistration(int $userId, string $email, string $displayName): array
    {
        $challenge = base64url_encode(random_bytes(32));
        self::storeChallenge('register', $challenge, $userId, $email);

        $stmt = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $existing = $stmt->fetchAll();

        $excludeCredentials = array_map(
            static fn (array $row): array => [
                'type' => 'public-key',
                'id' => (string) $row['credential_id'],
            ],
            $existing
        );

        return [
            'rp' => [
                'name' => APP_NAME,
                'id' => self::rpId(),
            ],
            'challenge' => $challenge,
            'user' => [
                'id' => base64url_encode(pack('N', $userId)),
                'name' => $email,
                'displayName' => $displayName,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'required',
            ],
            'excludeCredentials' => $excludeCredentials,
        ];
    }

    public static function finishRegistration(int $userId, string $email, array $payload): bool
    {
        $credentialId = (string) ($payload['credentialId'] ?? '');
        $clientDataB64 = (string) ($payload['clientDataJSON'] ?? '');
        $publicKeyB64 = (string) ($payload['publicKey'] ?? '');

        if ($credentialId === '' || $clientDataB64 === '' || $publicKeyB64 === '') {
            return false;
        }

        $clientDataRaw = base64url_decode($clientDataB64);
        $clientData = json_decode($clientDataRaw, true);

        if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.create') {
            return false;
        }

        $challenge = (string) ($clientData['challenge'] ?? '');
        if ($challenge === '' || !self::consumeChallenge('register', $challenge, $userId, $email)) {
            return false;
        }

        $publicKeyBinary = base64url_decode($publicKeyB64);
        if ($publicKeyBinary === '') {
            return false;
        }

        $publicKeyPem = self::spkiToPem($publicKeyBinary);
        $transports = $payload['transports'] ?? null;
        $transportValue = is_array($transports) ? implode(',', $transports) : null;

        $sql = 'INSERT INTO webauthn_credentials (user_id, credential_id, public_key, sign_count, transports, created_at)
                VALUES (:user_id, :credential_id, :public_key, 0, :transports, NOW())
                ON DUPLICATE KEY UPDATE public_key = VALUES(public_key), transports = VALUES(transports), updated_at = NOW()';

        $stmt = db()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'credential_id' => $credentialId,
            'public_key' => $publicKeyPem,
            'transports' => $transportValue,
        ]);

        return true;
    }

    public static function beginLogin(string $email): array
    {
        $email = normalizeEmail($email);

        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = :email AND is_bot = 0 LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new RuntimeException('No existe una cuenta con ese correo.');
        }

        $credStmt = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE user_id = :user_id');
        $credStmt->execute(['user_id' => (int) $user['id']]);
        $credentials = $credStmt->fetchAll();

        if ($credentials === []) {
            throw new RuntimeException('Este usuario aun no tiene biometria registrada.');
        }

        $challenge = base64url_encode(random_bytes(32));
        self::storeChallenge('login', $challenge, (int) $user['id'], $email);

        $allowCredentials = array_map(
            static fn (array $row): array => [
                'type' => 'public-key',
                'id' => (string) $row['credential_id'],
                'transports' => ['internal', 'hybrid', 'usb', 'nfc', 'ble'],
            ],
            $credentials
        );

        return [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => self::rpId(),
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'required',
        ];
    }

    public static function finishLogin(string $email, array $payload): ?int
    {
        $email = normalizeEmail($email);
        $credentialId = (string) ($payload['credentialId'] ?? '');

        if ($credentialId === '') {
            return null;
        }

        $sql = 'SELECT u.id AS user_id, u.email, wc.public_key, wc.sign_count
                FROM users u
                INNER JOIN webauthn_credentials wc ON wc.user_id = u.id
                WHERE u.email = :email AND wc.credential_id = :credential_id
                LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([
            'email' => $email,
            'credential_id' => $credentialId,
        ]);

        $record = $stmt->fetch();
        if (!$record) {
            return null;
        }

        $clientDataRaw = base64url_decode((string) ($payload['clientDataJSON'] ?? ''));
        $authenticatorData = base64url_decode((string) ($payload['authenticatorData'] ?? ''));
        $signature = base64url_decode((string) ($payload['signature'] ?? ''));

        if ($clientDataRaw === '' || $authenticatorData === '' || $signature === '') {
            return null;
        }

        $clientData = json_decode($clientDataRaw, true);
        if (!is_array($clientData) || ($clientData['type'] ?? '') !== 'webauthn.get') {
            return null;
        }

        $challenge = (string) ($clientData['challenge'] ?? '');
        $userId = (int) $record['user_id'];

        if ($challenge === '' || !self::consumeChallenge('login', $challenge, $userId, $email)) {
            return null;
        }

        if (!self::validateAuthenticatorData($authenticatorData)) {
            return null;
        }

        $clientDataHash = hash('sha256', $clientDataRaw, true);
        $signedData = $authenticatorData . $clientDataHash;

        $publicKey = openssl_pkey_get_public((string) $record['public_key']);
        if ($publicKey === false) {
            return null;
        }

        $verify = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verify !== 1) {
            return null;
        }

        $newSignCount = self::extractSignCount($authenticatorData);
        $oldSignCount = (int) ($record['sign_count'] ?? 0);

        if ($newSignCount > $oldSignCount) {
            $update = db()->prepare('UPDATE webauthn_credentials SET sign_count = :sign_count, last_used_at = NOW(), updated_at = NOW() WHERE credential_id = :credential_id');
            $update->execute([
                'sign_count' => $newSignCount,
                'credential_id' => $credentialId,
            ]);
        } else {
            $touch = db()->prepare('UPDATE webauthn_credentials SET last_used_at = NOW(), updated_at = NOW() WHERE credential_id = :credential_id');
            $touch->execute(['credential_id' => $credentialId]);
        }

        return $userId;
    }

    private static function storeChallenge(string $type, string $challenge, ?int $userId, ?string $email): void
    {
        db()->prepare('DELETE FROM webauthn_challenges WHERE expires_at < NOW()')->execute();

        $stmt = db()->prepare(
            'INSERT INTO webauthn_challenges (user_id, email, challenge, type, expires_at, created_at)
             VALUES (:user_id, :email, :challenge, :type, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW())'
        );

        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':challenge', $challenge);
        $stmt->bindValue(':type', $type);
        $stmt->execute();
    }

    private static function consumeChallenge(string $type, string $challenge, ?int $userId, ?string $email): bool
    {
        $conditions = ['type = :type', 'challenge = :challenge', 'expires_at >= NOW()'];
        $params = [
            'type' => $type,
            'challenge' => $challenge,
        ];

        if ($userId !== null) {
            $conditions[] = 'user_id = :user_id';
            $params['user_id'] = $userId;
        }

        if ($email !== null) {
            $conditions[] = 'email = :email';
            $params['email'] = $email;
        }

        $sql = 'SELECT id FROM webauthn_challenges WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id DESC LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $delete = db()->prepare('DELETE FROM webauthn_challenges WHERE id = :id');
        $delete->execute(['id' => (int) $row['id']]);

        return true;
    }

    private static function validateAuthenticatorData(string $authenticatorData): bool
    {
        if (strlen($authenticatorData) < 37) {
            return false;
        }

        $rpIdHash = substr($authenticatorData, 0, 32);
        $expectedRpIdHash = hash('sha256', self::rpId(), true);

        if (!hash_equals($expectedRpIdHash, $rpIdHash)) {
            return false;
        }

        $flags = ord($authenticatorData[32]);
        $userPresent = ($flags & 0x01) === 0x01;

        return $userPresent;
    }

    private static function extractSignCount(string $authenticatorData): int
    {
        $data = unpack('Ncount', substr($authenticatorData, 33, 4));
        return (int) ($data['count'] ?? 0);
    }

    private static function rpId(): string
    {
        $host = parse_url(APP_URL, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return preg_replace('/:\\d+$/', '', $httpHost) ?? 'localhost';
    }

    private static function spkiToPem(string $spkiBinary): string
    {
        $base64 = chunk_split(base64_encode($spkiBinary), 64, "\n");
        return "-----BEGIN PUBLIC KEY-----\n" . $base64 . "-----END PUBLIC KEY-----\n";
    }
}
