<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ChatService.php';
require_once __DIR__ . '/../lib/Chatbot.php';
require_once __DIR__ . '/../lib/WebAuthn.php';
require_once __DIR__ . '/../lib/SmtpMailer.php';
require_once __DIR__ . '/../config/mail.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
