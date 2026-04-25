<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

final class SmtpMailer
{
    private array $config;
    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $toEmail = normalizeEmail($toEmail);
        if ($toEmail === '' || empty($this->config['host'])) {
            return false;
        }

        try {
            $this->connect();
            $this->expect([220]);

            $this->command('EHLO chatubam.local', [250]);

            if (($this->config['secure'] ?? '') === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo activar STARTTLS.');
                }
                $this->command('EHLO chatubam.local', [250]);
            }

            $username = (string) ($this->config['username'] ?? '');
            $password = (string) ($this->config['password'] ?? '');

            if ($username !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($username), [334]);
                $this->command(base64_encode($password), [235]);
            }

            $fromEmail = (string) ($this->config['from_email'] ?? $username);
            $fromName = (string) ($this->config['from_name'] ?? APP_NAME);

            $this->command('MAIL FROM:<' . $this->sanitizeAddress($fromEmail) . '>', [250]);
            $this->command('RCPT TO:<' . $this->sanitizeAddress($toEmail) . '>', [250, 251]);
            $this->command('DATA', [354]);

            $payload = $this->buildMessage($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody, $textBody);
            $this->writeRaw($payload . "\r\n.\r\n");
            $this->expect([250]);

            $this->command('QUIT', [221]);
            $this->close();

            return true;
        } catch (Throwable $error) {
            $this->logError($error->getMessage());
            $this->close();
            return false;
        }
    }

    private function connect(): void
    {
        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 465);
        $secure = (string) ($this->config['secure'] ?? 'ssl');
        $timeout = (int) ($this->config['timeout'] ?? 20);

        if ($host === '') {
            throw new RuntimeException('SMTP host no configurado.');
        }

        $socketHost = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $errno = 0;
        $errstr = '';

        $this->socket = stream_socket_client($socketHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($this->socket)) {
            throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr);
        }

        stream_set_timeout($this->socket, $timeout);
    }

    private function command(string $command, array $expectedCodes): string
    {
        $this->writeRaw($command . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function writeRaw(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Socket SMTP no disponible.');
        }

        $written = fwrite($this->socket, $data);
        if ($written === false) {
            throw new RuntimeException('No se pudo escribir en el socket SMTP.');
        }
    }

    private function expect(array $codes): string
    {
        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('Respuesta SMTP inesperada: ' . $response);
        }

        return $response;
    }

    private function readResponse(): string
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('Socket SMTP cerrado.');
        }

        $response = '';

        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Servidor SMTP no respondio.');
        }

        return trim($response);
    }

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody
    ): string {
        $boundary = '=_ChatUbam_' . bin2hex(random_bytes(8));
        $encodedSubject = $this->encodeHeader($subject);
        $encodedFromName = $this->encodeHeader($fromName);
        $encodedToName = $this->encodeHeader($toName !== '' ? $toName : $toEmail);

        $plainText = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s O'),
            'From: ' . $encodedFromName . ' <' . $this->sanitizeAddress($fromEmail) . '>',
            'To: ' . $encodedToName . ' <' . $this->sanitizeAddress($toEmail) . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $plainText,
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $htmlBody,
            '--' . $boundary . '--',
            '',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function sanitizeAddress(string $email): string
    {
        return str_replace(["\r", "\n", '<', '>'], '', $email);
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    private function logError(string $message): void
    {
        $logDir = BASE_PATH . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $line = '[' . nowIso() . '] SMTP: ' . $message . PHP_EOL;
        file_put_contents($logDir . DIRECTORY_SEPARATOR . 'mail.log', $line, FILE_APPEND);
    }
}
