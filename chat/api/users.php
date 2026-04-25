<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');
$user = requireAuth();
$search = (string) ($_GET['q'] ?? '');

try {
    $contacts = ChatService::getContactList((int) $user['id'], $search);

    jsonResponse([
        'ok' => true,
        'contacts' => $contacts,
    ]);
} catch (Throwable $error) {
    jsonResponse(['ok' => false, 'message' => 'No se pudieron cargar usuarios: ' . $error->getMessage()], 500);
}
