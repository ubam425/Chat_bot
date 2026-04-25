<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');
$user = requireAuth();

$contactId = (int) ($_GET['contact_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);
$limit = (int) ($_GET['limit'] ?? 80);

if ($contactId <= 0) {
    jsonResponse(['ok' => false, 'message' => 'Falta contact_id.'], 422);
}

if ($contactId === (int) $user['id']) {
    jsonResponse(['ok' => false, 'message' => 'No puedes abrir chat contigo mismo.'], 422);
}

$exists = db()->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
$exists->execute(['id' => $contactId]);
if (!$exists->fetch()) {
    jsonResponse(['ok' => false, 'message' => 'Contacto no encontrado.'], 404);
}

try {
    $messages = ChatService::getConversation((int) $user['id'], $contactId, max(0, $afterId), $limit);
    $latestId = $afterId;

    if ($messages !== []) {
        $last = end($messages);
        $latestId = max($latestId, (int) ($last['id'] ?? 0));
    }

    jsonResponse([
        'ok' => true,
        'messages' => $messages,
        'latest_id' => $latestId,
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'No se pudo cargar la conversacion: ' . $error->getMessage()], 500);
}
