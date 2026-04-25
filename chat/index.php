<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/helpers.php';

if (currentUser() !== null) {
    header('Location: dashboard.php');
    exit;
}

$testEmail = (string) (env('TEST_USER_EMAIL', 'pruebas@chatubam.local') ?? 'pruebas@chatubam.local');
$testPassword = (string) (env('TEST_USER_PASSWORD', 'Prueba123!') ?? 'Prueba123!');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="welcome-panel">
            <div class="brand-pill">
                <span class="brand-dot"></span>
                <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <h1 class="welcome-title">Te damos la bienvenida a <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="welcome-copy">
                Simple, confiable y privado. Envia mensajes, comparte archivos, fotos, videos y usa acceso biometrico para iniciar sesion.
            </p>
            <button class="btn btn-primary" onclick="document.querySelector('[data-tab-target=\'panel-login\']').click();">
                Iniciar sesion
            </button>
        </section>

        <section class="auth-panel">
            <div class="tab-switcher">
                <button type="button" data-tab-target="panel-login" class="active">Login</button>
                <button type="button" data-tab-target="panel-register">Registro</button>
                <button type="button" data-tab-target="panel-recover">Recuperar</button>
            </div>

            <div id="panel-login" class="form-panel active">
                <form id="login-form" class="form-grid" autocomplete="on">
                    <div class="field">
                        <label for="login-email">Correo</label>
                        <input id="login-email" class="input" type="email" name="email" required placeholder="tu@correo.com">
                    </div>
                    <div class="field">
                        <label for="login-password">Contrasena</label>
                        <input id="login-password" class="input" type="password" name="password" required placeholder="********">
                    </div>
                    <div class="auth-actions">
                        <button class="btn btn-primary" type="submit">Entrar</button>
                        <button class="btn btn-secondary" type="button" id="auto-login-btn">Entrar con usuario de prueba</button>
                    </div>
                </form>

                <div class="auth-note" style="margin-top:12px;">
                    Usuario de prueba: <strong><?= htmlspecialchars($testEmail, ENT_QUOTES, 'UTF-8') ?></strong>
                    | Password: <strong><?= htmlspecialchars($testPassword, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <hr style="margin:18px 0;border:none;border-top:1px solid #dde5df;">

                <div class="form-grid">
                    <div class="field">
                        <label for="biometric-email">Login biometrico (correo + huella/rostro)</label>
                        <input id="biometric-email" class="input" type="email" placeholder="tu@correo.com">
                    </div>
                    <div class="auth-actions">
                        <button class="btn btn-secondary" id="biometric-login-btn" type="button">Entrar con biometria</button>
                    </div>
                    <small id="biometric-support-note" style="color:#5d6d66;">Disponible en navegadores compatibles con passkeys/WebAuthn.</small>
                </div>
            </div>

            <div id="panel-register" class="form-panel">
                <form id="register-form" class="form-grid" autocomplete="off">
                    <div class="form-grid two">
                        <div class="field">
                            <label for="register-first-name">Nombre(s)</label>
                            <input id="register-first-name" class="input" type="text" name="first_name" required>
                        </div>
                        <div class="field">
                            <label for="register-phone">Telefono</label>
                            <input id="register-phone" class="input" type="text" name="phone" required>
                        </div>
                    </div>

                    <div class="form-grid two">
                        <div class="field">
                            <label for="register-last-p">Apellido paterno</label>
                            <input id="register-last-p" class="input" type="text" name="last_name_paterno" required>
                        </div>
                        <div class="field">
                            <label for="register-last-m">Apellido materno</label>
                            <input id="register-last-m" class="input" type="text" name="last_name_materno" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="register-email">Correo</label>
                        <input id="register-email" class="input" type="email" name="email" required>
                    </div>

                    <div class="auth-note">
                        La contrasena se genera automaticamente y se envia al correo registrado.
                    </div>

                    <div class="auth-actions">
                        <button class="btn btn-primary" type="submit">Crear cuenta</button>
                    </div>
                </form>
            </div>

            <div id="panel-recover" class="form-panel">
                <form id="recover-form" class="form-grid" autocomplete="off">
                    <div class="field">
                        <label for="recover-email">Correo registrado</label>
                        <input id="recover-email" class="input" type="email" name="email" required>
                    </div>

                    <div class="auth-note">
                        Se generara una nueva contrasena y se enviara por correo.
                    </div>

                    <div class="auth-actions">
                        <button class="btn btn-primary" type="submit">Recuperar contrasena</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <div id="toast" class="toast"></div>
    <script src="assets/js/webauthn.js"></script>
    <script src="assets/js/auth.js"></script>
</body>
</html>
