(function () {
    const toastEl = document.getElementById('toast');

    function showToast(message, type = 'success') {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.className = `toast show ${type}`;
        window.clearTimeout(showToast._timer);
        showToast._timer = window.setTimeout(() => {
            toastEl.className = 'toast';
        }, 3200);
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({ ok: false, message: 'Respuesta invalida del servidor.' }));
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Error de solicitud.');
        }

        return data;
    }

    function setupTabs() {
        const tabButtons = document.querySelectorAll('[data-tab-target]');
        const panels = document.querySelectorAll('.form-panel');

        function activateTab(panelId) {
            tabButtons.forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.tabTarget === panelId);
            });
            panels.forEach((panel) => {
                panel.classList.toggle('active', panel.id === panelId);
            });
        }

        tabButtons.forEach((btn) => {
            btn.addEventListener('click', () => activateTab(btn.dataset.tabTarget));
        });

        activateTab('panel-login');
    }

    function setupLoginForm() {
        const form = document.getElementById('login-form');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(form);
            const payload = {
                email: String(formData.get('email') || '').trim(),
                password: String(formData.get('password') || ''),
            };

            try {
                const result = await postJson('api/login.php', payload);
                showToast(result.message || 'Sesion iniciada.', 'success');
                window.location.href = 'dashboard.php';
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    function setupRegisterForm() {
        const form = document.getElementById('register-form');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const payload = Object.fromEntries(new FormData(form).entries());
            payload.email = String(payload.email || '').trim();
            payload.phone = String(payload.phone || '').trim();

            try {
                const result = await postJson('api/register.php', payload);
                showToast(result.message || 'Registro exitoso.', 'success');

                const loginEmailInput = document.querySelector('#panel-login input[name="email"]');
                if (loginEmailInput) {
                    loginEmailInput.value = payload.email;
                }

                const loginTab = document.querySelector('[data-tab-target="panel-login"]');
                if (loginTab) {
                    loginTab.click();
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    function setupRecoverForm() {
        const form = document.getElementById('recover-form');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const payload = Object.fromEntries(new FormData(form).entries());
            payload.email = String(payload.email || '').trim();

            try {
                const result = await postJson('api/recover.php', payload);
                showToast(result.message, 'success');
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    function setupAutoLogin() {
        const button = document.getElementById('auto-login-btn');
        if (!button) return;

        button.addEventListener('click', async () => {
            try {
                const result = await postJson('api/auto_login_test.php', {});
                showToast(result.message || 'Entrando con usuario de prueba...', 'success');
                window.location.href = 'dashboard.php';
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    function setupBiometricLogin() {
        const button = document.getElementById('biometric-login-btn');
        const emailInput = document.getElementById('biometric-email');
        const supportNote = document.getElementById('biometric-support-note');

        if (!button || !emailInput) return;

        if (!window.ChatUbamWebAuthn || !window.ChatUbamWebAuthn.isSupported()) {
            button.disabled = true;
            button.title = 'WebAuthn no soportado en este navegador';
            if (supportNote) {
                supportNote.textContent = 'Este navegador no soporta biometria WebAuthn.';
            }
            return;
        }

        button.addEventListener('click', async () => {
            const email = String(emailInput.value || '').trim();
            if (!email) {
                showToast('Ingresa tu correo para usar biometria.', 'error');
                return;
            }

            try {
                const begin = await postJson('api/webauthn_begin_login.php', { email });
                const assertion = await window.ChatUbamWebAuthn.getAssertion(begin.options);
                const finish = await postJson('api/webauthn_finish_login.php', {
                    email,
                    ...assertion,
                });

                showToast(finish.message || 'Sesion biometrica iniciada.', 'success');
                window.location.href = 'dashboard.php';
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    setupTabs();
    setupLoginForm();
    setupRegisterForm();
    setupRecoverForm();
    setupAutoLogin();
    setupBiometricLogin();
})();
