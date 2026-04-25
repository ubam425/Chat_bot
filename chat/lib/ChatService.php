<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

final class ChatService
{
    public static function getUserByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => normalizeEmail($email)]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function getContactList(int $currentUserId, string $search = ''): array
    {
        $searchValue = '%' . trim($search) . '%';

        $sql = 'SELECT
                    u.id,
                    u.first_name,
                    u.last_name_paterno,
                    u.last_name_materno,
                    u.email,
                    u.phone,
                    u.avatar_path,
                    u.is_bot,
                    (
                        SELECT m.body
                        FROM messages m
                        WHERE ((m.sender_id = u.id AND m.receiver_id = :uid1) OR (m.sender_id = :uid2 AND m.receiver_id = u.id))
                        ORDER BY m.id DESC
                        LIMIT 1
                    ) AS last_body,
                    (
                        SELECT m.attachment_type
                        FROM messages m
                        WHERE ((m.sender_id = u.id AND m.receiver_id = :uid3) OR (m.sender_id = :uid4 AND m.receiver_id = u.id))
                        ORDER BY m.id DESC
                        LIMIT 1
                    ) AS last_attachment_type,
                    (
                        SELECT m.created_at
                        FROM messages m
                        WHERE ((m.sender_id = u.id AND m.receiver_id = :uid5) OR (m.sender_id = :uid6 AND m.receiver_id = u.id))
                        ORDER BY m.id DESC
                        LIMIT 1
                    ) AS last_message_at,
                    (
                        SELECT COUNT(*)
                        FROM messages m
                        WHERE m.sender_id = u.id AND m.receiver_id = :uid7 AND m.read_at IS NULL
                    ) AS unread_count
                FROM users u
                WHERE u.id <> :uid8
                    AND (
                        :search_empty = 1
                        OR u.first_name LIKE :search1
                        OR u.last_name_paterno LIKE :search2
                        OR u.last_name_materno LIKE :search3
                        OR u.email LIKE :search4
                    )
                ORDER BY COALESCE(last_message_at, u.created_at) DESC, u.first_name ASC';

        $stmt = db()->prepare($sql);
        $stmt->execute([
            'uid1' => $currentUserId,
            'uid2' => $currentUserId,
            'uid3' => $currentUserId,
            'uid4' => $currentUserId,
            'uid5' => $currentUserId,
            'uid6' => $currentUserId,
            'uid7' => $currentUserId,
            'uid8' => $currentUserId,
            'search_empty' => trim($search) === '' ? 1 : 0,
            'search1' => $searchValue,
            'search2' => $searchValue,
            'search3' => $searchValue,
            'search4' => $searchValue,
        ]);

        $rows = $stmt->fetchAll();
        if ($rows === false) {
            return [];
        }

        return array_map(static function (array $row): array {
            $preview = trim((string) ($row['last_body'] ?? ''));

            if ($preview === '') {
                $type = (string) ($row['last_attachment_type'] ?? 'none');
                $preview = match ($type) {
                    'image' => 'Imagen',
                    'video' => 'Video',
                    'file' => 'Archivo',
                    default => 'Sin mensajes',
                };
            }

            return [
                'id' => (int) $row['id'],
                'name' => formatFullName($row),
                'email' => (string) $row['email'],
                'phone' => (string) $row['phone'],
                'avatar_url' => !empty($row['avatar_path']) ? str_replace('\\', '/', (string) $row['avatar_path']) : null,
                'is_bot' => (int) $row['is_bot'] === 1,
                'last_message' => $preview,
                'last_message_at' => $row['last_message_at'],
                'unread_count' => (int) ($row['unread_count'] ?? 0),
            ];
        }, $rows);
    }

    public static function getConversation(int $currentUserId, int $contactId, int $afterId = 0, int $limit = 80): array
    {
        $sql = 'SELECT
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.body,
                    m.attachment_path,
                    m.attachment_type,
                    m.original_name,
                    m.mime_type,
                    m.attachment_size,
                    m.created_at,
                    s.first_name AS sender_first_name,
                    s.last_name_paterno AS sender_last_name_paterno,
                    s.last_name_materno AS sender_last_name_materno
                FROM messages m
                INNER JOIN users s ON s.id = m.sender_id
                WHERE ((m.sender_id = :me1 AND m.receiver_id = :contact1)
                    OR (m.sender_id = :contact2 AND m.receiver_id = :me2))
                    AND m.id > :after_id
                ORDER BY m.id ASC
                LIMIT :limit';

        $stmt = db()->prepare($sql);
        $stmt->bindValue(':me1', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':contact1', $contactId, PDO::PARAM_INT);
        $stmt->bindValue(':contact2', $contactId, PDO::PARAM_INT);
        $stmt->bindValue(':me2', $currentUserId, PDO::PARAM_INT);
        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $messages = $stmt->fetchAll();
        if ($messages === false) {
            $messages = [];
        }

        self::markAsRead($currentUserId, $contactId);

        return array_map(static fn (array $message): array => self::formatMessage($message), $messages);
    }

    public static function createMessage(
        int $senderId,
        int $receiverId,
        ?string $body,
        ?string $attachmentPath = null,
        string $attachmentType = 'none',
        ?string $originalName = null,
        ?string $mimeType = null,
        ?int $attachmentSize = null
    ): int {
        $stmt = db()->prepare(
            'INSERT INTO messages (
                sender_id,
                receiver_id,
                body,
                attachment_path,
                attachment_type,
                original_name,
                mime_type,
                attachment_size,
                created_at
            ) VALUES (
                :sender_id,
                :receiver_id,
                :body,
                :attachment_path,
                :attachment_type,
                :original_name,
                :mime_type,
                :attachment_size,
                NOW()
            )'
        );

        $trimmedBody = $body !== null ? trim($body) : null;
        $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindValue(':receiver_id', $receiverId, PDO::PARAM_INT);
        $stmt->bindValue(':body', $trimmedBody !== '' ? $trimmedBody : null, $trimmedBody !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':attachment_path', $attachmentPath, $attachmentPath === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':attachment_type', $attachmentType);
        $stmt->bindValue(':original_name', $originalName, $originalName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':mime_type', $mimeType, $mimeType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':attachment_size', $attachmentSize, $attachmentSize === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return (int) db()->lastInsertId();
    }

    public static function getMessageById(int $messageId): ?array
    {
        $stmt = db()->prepare(
            'SELECT
                m.id,
                m.sender_id,
                m.receiver_id,
                m.body,
                m.attachment_path,
                m.attachment_type,
                m.original_name,
                m.mime_type,
                m.attachment_size,
                m.created_at,
                s.first_name AS sender_first_name,
                s.last_name_paterno AS sender_last_name_paterno,
                s.last_name_materno AS sender_last_name_materno
             FROM messages m
             INNER JOIN users s ON s.id = m.sender_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $messageId]);
        $row = $stmt->fetch();

        return $row ? self::formatMessage($row) : null;
    }

    public static function findBotId(): ?int
    {
        $stmt = db()->query('SELECT id FROM users WHERE is_bot = 1 ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    }

    private static function markAsRead(int $currentUserId, int $contactId): void
    {
        $stmt = db()->prepare(
            'UPDATE messages
             SET read_at = NOW()
             WHERE sender_id = :contact_id
               AND receiver_id = :current_user_id
               AND read_at IS NULL'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'current_user_id' => $currentUserId,
        ]);
    }

    private static function formatMessage(array $message): array
    {
        $senderName = trim(
            implode(' ', array_filter([
                (string) ($message['sender_first_name'] ?? ''),
                (string) ($message['sender_last_name_paterno'] ?? ''),
                (string) ($message['sender_last_name_materno'] ?? ''),
            ]))
        );

        $attachmentPath = $message['attachment_path'] ?? null;
        $attachmentUrl = null;

        if (is_string($attachmentPath) && $attachmentPath !== '') {
            $attachmentUrl = str_replace('\\', '/', $attachmentPath);
        }

        return [
            'id' => (int) $message['id'],
            'sender_id' => (int) $message['sender_id'],
            'receiver_id' => (int) $message['receiver_id'],
            'sender_name' => $senderName,
            'body' => (string) ($message['body'] ?? ''),
            'attachment_type' => (string) ($message['attachment_type'] ?? 'none'),
            'attachment_url' => $attachmentUrl,
            'original_name' => $message['original_name'] ?? null,
            'mime_type' => $message['mime_type'] ?? null,
            'attachment_size' => $message['attachment_size'] !== null ? (int) $message['attachment_size'] : null,
            'created_at' => (string) $message['created_at'],
        ];
    }
}
