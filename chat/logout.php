<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Auth.php';

logoutUser();
header('Location: index.php');
exit;
