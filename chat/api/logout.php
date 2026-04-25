<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/helpers.php';

requireMethod('POST');
logoutUser();

jsonResponse([
    'ok' => true,
    'message' => 'Sesion cerrada.',
]);
