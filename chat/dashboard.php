<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/helpers.php';

$user = currentUser();
if ($user === null) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?> | Chats</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">
    <div class="chat-app">
        <aside class="sidebar" id="sidebar">
            <header class="sidebar-header">
                <div>
                    <h1 class="sidebar-title">Chats</h1>
                    <div id="sidebar-user-mini" class="user-mini"><?= htmlspecialchars(formatFullName($user), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button id="profile-btn" class="icon-btn" type="button" title="Perfil">...</button>
            </header>

            <div class="search-wrap">
                <input id="contact-search" class="search-input" type="search" placeholder="Buscar un chat o iniciar uno nuevo">
            </div>

            <div class="filters">
                <button type="button" class="filter-pill active" data-filter="all">Todos</button>
                <button type="button" class="filter-pill" data-filter="unread">No leidos</button>
                <button type="button" class="filter-pill" data-filter="bot">Bot</button>
            </div>

            <div class="contacts-list" id="contacts-list"></div>
        </aside>
        <div id="sidebar-backdrop" class="sidebar-backdrop hidden"></div>

        <section class="main-panel">
            <header class="chat-header">
                <div class="chat-header-left">
                    <button id="mobile-toggle" class="icon-btn mobile-toggle" type="button" title="Abrir chats">...</button>
                    <div>
                        <h2 class="chat-header-name" id="chat-header-name">Selecciona un chat</h2>
                        <div class="chat-header-sub" id="chat-header-sub">Todos los usuarios registrados aparecen aqui</div>
                    </div>
                </div>
                <div>
                    <button class="icon-btn" type="button" title="Info">i</button>
                </div>
            </header>

            <div class="chat-stage" id="chat-stage">
                <div id="empty-panel" class="empty-panel">
                    <div>
                        <h3>Bienvenido a <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h3>
                        <p>Selecciona un chat del panel izquierdo para comenzar.</p>
                        <div class="empty-grid">
                            <article class="empty-card">Enviar documento</article>
                            <article class="empty-card">Anadir contacto</article>
                            <article class="empty-card">Hablar con ChatUbam Bot</article>
                        </div>
                    </div>
                </div>
                <div class="messages" id="messages"></div>
            </div>

            <div class="chat-composer">
                <form id="composer-form" autocomplete="off">
                    <div class="composer-row">
                        <button id="attach-btn" class="icon-btn" type="button" title="Adjuntar">+</button>
                        <button class="icon-btn" type="button" title="Emoji">:)</button>
                        <input id="composer-input" type="text" placeholder="Escribe un mensaje">
                        <button class="send-btn" type="submit" title="Enviar">></button>
                    </div>
                    <input id="attachment-input" type="file" name="attachment" class="hidden" accept="*/*">
                    <div id="attach-status" class="attach-status"></div>
                </form>
            </div>

            <aside id="profile-panel" class="profile-panel hidden">
                <div class="profile-avatar-wrap">
                    <div id="profile-avatar" class="profile-avatar"></div>
                    <div class="profile-avatar-actions">
                        <input id="profile-avatar-input" type="file" class="hidden" accept="image/jpeg,image/png,image/webp,image/gif">
                        <button id="profile-avatar-btn" class="btn btn-secondary" type="button">Cambiar foto</button>
                        <button id="profile-avatar-remove-btn" class="btn btn-secondary" type="button">Quitar foto</button>
                    </div>
                </div>

                <h3 id="profile-name"><?= htmlspecialchars(formatFullName($user), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="profile-line" id="profile-email"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="profile-line" id="profile-phone"><?= htmlspecialchars((string) $user['phone'], ENT_QUOTES, 'UTF-8') ?></p>
                <p class="profile-line" id="profile-passkeys">Biometria registrada: consultando...</p>

                <form id="profile-form" class="profile-form" autocomplete="off">
                    <div class="profile-form-grid">
                        <div class="field">
                            <label for="profile-first-name">Nombre(s)</label>
                            <input id="profile-first-name" class="input" type="text" name="first_name" required>
                        </div>
                        <div class="field">
                            <label for="profile-phone-input">Telefono</label>
                            <input id="profile-phone-input" class="input" type="text" name="phone" required>
                        </div>
                    </div>

                    <div class="profile-form-grid">
                        <div class="field">
                            <label for="profile-last-p">Apellido paterno</label>
                            <input id="profile-last-p" class="input" type="text" name="last_name_paterno" required>
                        </div>
                        <div class="field">
                            <label for="profile-last-m">Apellido materno</label>
                            <input id="profile-last-m" class="input" type="text" name="last_name_materno" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="profile-email-input">Correo</label>
                        <input id="profile-email-input" class="input" type="email" name="email" required>
                    </div>

                    <button id="profile-save-btn" class="btn btn-primary profile-save-btn" type="submit">Guardar cambios</button>
                </form>

                <div class="profile-actions">
                    <button id="register-passkey-btn" class="btn btn-secondary" type="button">Registrar huella/rostro</button>
                    <button id="logout-btn" class="btn btn-danger" type="button">Cerrar sesion</button>
                </div>
            </aside>
        </section>
    </div>

    <div id="toast" class="toast"></div>
    <script src="assets/js/webauthn.js"></script>
    <script src="assets/js/chat.js"></script>
</body>
</html>
