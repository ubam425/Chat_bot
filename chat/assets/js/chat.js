(function () {
    const state = {
        me: null,
        contacts: [],
        activeContactId: null,
        activeFilter: 'all',
        lastMessageId: 0,
        knownMessageIds: new Set(),
        contactsTimer: null,
        messagesTimer: null,
    };

    const els = {
        toast: document.getElementById('toast'),
        contactsList: document.getElementById('contacts-list'),
        searchInput: document.getElementById('contact-search'),
        chatHeaderName: document.getElementById('chat-header-name'),
        chatHeaderSub: document.getElementById('chat-header-sub'),
        messages: document.getElementById('messages'),
        emptyPanel: document.getElementById('empty-panel'),
        composerForm: document.getElementById('composer-form'),
        composerInput: document.getElementById('composer-input'),
        attachmentInput: document.getElementById('attachment-input'),
        attachBtn: document.getElementById('attach-btn'),
        attachStatus: document.getElementById('attach-status'),
        profileBtn: document.getElementById('profile-btn'),
        profilePanel: document.getElementById('profile-panel'),
        profileName: document.getElementById('profile-name'),
        profileEmail: document.getElementById('profile-email'),
        profilePhone: document.getElementById('profile-phone'),
        profileAvatar: document.getElementById('profile-avatar'),
        profileForm: document.getElementById('profile-form'),
        profileFirstNameInput: document.getElementById('profile-first-name'),
        profileLastPInput: document.getElementById('profile-last-p'),
        profileLastMInput: document.getElementById('profile-last-m'),
        profilePhoneInput: document.getElementById('profile-phone-input'),
        profileEmailInput: document.getElementById('profile-email-input'),
        profileAvatarInput: document.getElementById('profile-avatar-input'),
        profileAvatarBtn: document.getElementById('profile-avatar-btn'),
        profileAvatarRemoveBtn: document.getElementById('profile-avatar-remove-btn'),
        profilePasskeys: document.getElementById('profile-passkeys'),
        registerPasskeyBtn: document.getElementById('register-passkey-btn'),
        logoutBtn: document.getElementById('logout-btn'),
        sidebar: document.getElementById('sidebar'),
        sidebarBackdrop: document.getElementById('sidebar-backdrop'),
        mobileToggle: document.getElementById('mobile-toggle'),
        filters: document.querySelectorAll('.filter-pill[data-filter]'),
    };

    function isMobileLayout() {
        return window.matchMedia('(max-width: 1100px)').matches;
    }

    function openSidebar() {
        if (!els.sidebar) return;
        els.sidebar.classList.add('show');
        document.body.classList.add('sidebar-open');
        if (els.sidebarBackdrop) {
            els.sidebarBackdrop.classList.remove('hidden');
        }
    }

    function closeSidebar() {
        if (!els.sidebar) return;
        els.sidebar.classList.remove('show');
        document.body.classList.remove('sidebar-open');
        if (els.sidebarBackdrop) {
            els.sidebarBackdrop.classList.add('hidden');
        }
    }

    function showToast(message, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = message;
        els.toast.className = `toast show ${type}`;
        clearTimeout(showToast._timer);
        showToast._timer = setTimeout(() => {
            els.toast.className = 'toast';
        }, 3000);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function request(url, options = {}) {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({ ok: false, message: 'Respuesta invalida.' }));
        if (response.status === 401) {
            window.location.href = 'index.php';
            throw new Error('Sesion expirada.');
        }
        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Solicitud fallida.');
        }
        return data;
    }

    async function getJson(url) {
        return request(url);
    }

    async function postJson(url, payload) {
        return request(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    }

    function initials(name) {
        const words = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!words.length) return 'U';
        return (words[0][0] + (words[1] ? words[1][0] : '')).toUpperCase();
    }

    function avatarMarkup(name, avatarUrl, className = 'avatar') {
        if (avatarUrl) {
            return `<div class="${className}"><img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(name)}"></div>`;
        }
        return `<div class="${className}">${escapeHtml(initials(name))}</div>`;
    }

    function syncProfileView() {
        if (!state.me) return;

        if (els.profileName) els.profileName.textContent = state.me.name;
        if (els.profileEmail) els.profileEmail.textContent = state.me.email;
        if (els.profilePhone) els.profilePhone.textContent = state.me.phone;

        if (els.profileAvatar) {
            if (state.me.avatar_url) {
                els.profileAvatar.innerHTML = `<img src="${escapeHtml(state.me.avatar_url)}" alt="Foto de perfil">`;
            } else {
                els.profileAvatar.innerHTML = '';
                els.profileAvatar.textContent = initials(state.me.name);
            }
        }

        if (els.profileAvatarRemoveBtn) {
            els.profileAvatarRemoveBtn.disabled = !state.me.avatar_url;
        }

        if (els.profileFirstNameInput) els.profileFirstNameInput.value = state.me.first_name || '';
        if (els.profileLastPInput) els.profileLastPInput.value = state.me.last_name_paterno || '';
        if (els.profileLastMInput) els.profileLastMInput.value = state.me.last_name_materno || '';
        if (els.profilePhoneInput) els.profilePhoneInput.value = state.me.phone || '';
        if (els.profileEmailInput) els.profileEmailInput.value = state.me.email || '';
    }

    function formatHour(value) {
        if (!value) return '';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return '';
        return parsed.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    }

    function formatContactPreview(contact) {
        return contact.last_message || 'Sin mensajes';
    }

    function getFilteredContacts() {
        if (state.activeFilter === 'unread') {
            return state.contacts.filter((contact) => Number(contact.unread_count) > 0);
        }

        if (state.activeFilter === 'bot') {
            return state.contacts.filter((contact) => contact.is_bot === true);
        }

        return state.contacts;
    }

    function renderContacts() {
        if (!els.contactsList) return;

        const filtered = getFilteredContacts();

        if (!filtered.length) {
            els.contactsList.innerHTML = '<p style="padding:16px;color:#5d6d66;">No hay usuarios para mostrar.</p>';
            return;
        }

        const html = filtered.map((contact) => {
            const active = contact.id === state.activeContactId ? 'active' : '';
            const avatarClass = contact.is_bot ? 'avatar bot' : 'avatar';
            const unread = Number(contact.unread_count || 0);
            const unreadBadge = unread > 0 ? `<span class="unread-badge">${unread}</span>` : '';
            const avatar = avatarMarkup(contact.name, contact.avatar_url || null, avatarClass);

            return `
                <article class="contact-item ${active}" data-contact-id="${contact.id}">
                    ${avatar}
                    <div class="contact-main">
                        <h4 class="contact-name">${escapeHtml(contact.name)}</h4>
                        <div class="contact-preview">${escapeHtml(formatContactPreview(contact))}</div>
                    </div>
                    <div class="contact-meta">
                        <span>${escapeHtml(formatHour(contact.last_message_at))}</span>
                        ${unreadBadge}
                    </div>
                </article>
            `;
        }).join('');

        els.contactsList.innerHTML = html;

        els.contactsList.querySelectorAll('.contact-item').forEach((item) => {
            item.addEventListener('click', () => {
                const contactId = Number(item.dataset.contactId);
                selectContact(contactId);
                if (isMobileLayout()) {
                    closeSidebar();
                }
            });
        });
    }

    function setHeader(contact) {
        if (!contact) {
            els.chatHeaderName.textContent = 'Selecciona un chat';
            els.chatHeaderSub.textContent = 'Todos los usuarios registrados aparecen aqui';
            return;
        }

        els.chatHeaderName.textContent = contact.name;
        els.chatHeaderSub.textContent = contact.is_bot ? 'ChatUbam Bot' : contact.email;
    }

    function renderEmptyState(show) {
        if (els.emptyPanel) {
            els.emptyPanel.classList.toggle('hidden', !show);
        }

        if (els.composerForm) {
            const disable = show;
            const controls = els.composerForm.querySelectorAll('input,button');
            controls.forEach((control) => {
                control.disabled = disable;
            });
        }
    }

    function attachmentMarkup(message) {
        if (!message.attachment_type || message.attachment_type === 'none' || !message.attachment_url) {
            return '';
        }

        const url = escapeHtml(message.attachment_url);
        const originalName = escapeHtml(message.original_name || 'archivo');

        if (message.attachment_type === 'image') {
            return `<img src="${url}" alt="Imagen adjunta" loading="lazy">`;
        }

        if (message.attachment_type === 'video') {
            return `<video src="${url}" controls preload="metadata"></video>`;
        }

        return `<a class="message-file" href="${url}" target="_blank" rel="noopener" download="${originalName}">Archivo: ${originalName}</a>`;
    }

    function messageMarkup(message) {
        const outgoing = Number(message.sender_id) === Number(state.me.id);
        const cls = outgoing ? 'outgoing' : 'incoming';
        const textHtml = message.body ? `<div class="message-text">${escapeHtml(message.body)}</div>` : '';

        return `
            <article class="message ${cls}" data-message-id="${message.id}">
                ${textHtml}
                ${attachmentMarkup(message)}
                <div class="message-meta">${escapeHtml(formatHour(message.created_at))}</div>
            </article>
        `;
    }

    function appendMessages(messages, { replace = false } = {}) {
        if (!els.messages) return;

        if (replace) {
            els.messages.innerHTML = '';
            state.knownMessageIds.clear();
        }

        const shouldAutoScroll = replace || isNearBottom();

        messages.forEach((message) => {
            if (state.knownMessageIds.has(message.id)) {
                return;
            }

            state.knownMessageIds.add(message.id);
            els.messages.insertAdjacentHTML('beforeend', messageMarkup(message));
            state.lastMessageId = Math.max(state.lastMessageId, Number(message.id));
        });

        if (shouldAutoScroll) {
            scrollToBottom();
        }
    }

    function isNearBottom() {
        const stage = document.getElementById('chat-stage');
        if (!stage) return true;
        const distance = stage.scrollHeight - stage.scrollTop - stage.clientHeight;
        return distance < 150;
    }

    function scrollToBottom() {
        const stage = document.getElementById('chat-stage');
        if (!stage) return;
        stage.scrollTop = stage.scrollHeight;
    }

    async function loadCurrentUser() {
        const data = await getJson('api/me.php');
        state.me = data.user;
        syncProfileView();

        if (els.profilePasskeys) {
            els.profilePasskeys.textContent = data.passkeys > 0
                ? `Biometria registrada: ${data.passkeys} credencial(es)`
                : 'Biometria registrada: ninguna';
        }

        const mini = document.getElementById('sidebar-user-mini');
        if (mini) mini.textContent = `${data.user.name} - ${data.user.email}`;
    }

    async function loadContacts() {
        const searchTerm = encodeURIComponent(els.searchInput ? els.searchInput.value.trim() : '');
        const data = await getJson(`api/users.php?q=${searchTerm}`);
        state.contacts = data.contacts || [];

        if (!state.activeContactId && state.contacts.length > 0) {
            state.activeContactId = Number(state.contacts[0].id);
            state.lastMessageId = 0;
            state.knownMessageIds.clear();
            setHeader(state.contacts[0]);
            renderEmptyState(false);
            await loadMessages(true);
        } else {
            const activeContact = state.contacts.find((contact) => Number(contact.id) === Number(state.activeContactId));
            if (activeContact) {
                setHeader(activeContact);
            }
        }

        renderContacts();
    }

    async function loadMessages(replace = false) {
        if (!state.activeContactId) {
            renderEmptyState(true);
            return;
        }

        const afterId = replace ? 0 : state.lastMessageId;
        const params = new URLSearchParams({
            contact_id: String(state.activeContactId),
            after_id: String(afterId),
            limit: replace ? '160' : '120',
        });

        const data = await getJson(`api/messages.php?${params.toString()}`);
        appendMessages(data.messages || [], { replace });
    }

    async function selectContact(contactId) {
        if (Number(contactId) === Number(state.activeContactId) && state.lastMessageId > 0) {
            return;
        }

        state.activeContactId = Number(contactId);
        state.lastMessageId = 0;
        state.knownMessageIds.clear();

        const contact = state.contacts.find((item) => Number(item.id) === Number(contactId));
        setHeader(contact || null);
        renderEmptyState(false);

        try {
            await loadMessages(true);
            await loadContacts();
        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    async function sendMessage(event) {
        event.preventDefault();
        if (!state.activeContactId) {
            showToast('Selecciona un contacto primero.', 'error');
            return;
        }

        const text = String(els.composerInput.value || '').trim();
        const file = els.attachmentInput.files && els.attachmentInput.files[0] ? els.attachmentInput.files[0] : null;

        if (!text && !file) {
            return;
        }

        const payload = new FormData();
        payload.append('receiver_id', String(state.activeContactId));
        payload.append('message', text);
        if (file) {
            payload.append('attachment', file);
        }

        try {
            const result = await request('api/send_message.php', {
                method: 'POST',
                body: payload,
            });

            if (result.message) {
                appendMessages([result.message], { replace: false });
            }

            if (result.bot_reply) {
                appendMessages([result.bot_reply], { replace: false });
            }

            els.composerInput.value = '';
            els.attachmentInput.value = '';
            els.attachStatus.textContent = '';
            await loadContacts();
        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    function setupComposer() {
        if (els.composerForm) {
            els.composerForm.addEventListener('submit', sendMessage);
        }

        if (els.attachBtn && els.attachmentInput) {
            els.attachBtn.addEventListener('click', () => els.attachmentInput.click());
            els.attachmentInput.addEventListener('change', () => {
                const file = els.attachmentInput.files && els.attachmentInput.files[0] ? els.attachmentInput.files[0] : null;
                els.attachStatus.textContent = file ? `Adjunto: ${file.name}` : '';
            });
        }
    }

    function setupProfilePanel() {
        if (!els.profileBtn || !els.profilePanel) return;

        els.profileBtn.addEventListener('click', () => {
            els.profilePanel.classList.toggle('hidden');
        });

        document.addEventListener('click', (event) => {
            if (els.profilePanel.classList.contains('hidden')) return;
            const target = event.target;
            if (!(target instanceof Node)) return;
            if (!els.profilePanel.contains(target) && !els.profileBtn.contains(target)) {
                els.profilePanel.classList.add('hidden');
            }
        });
    }

    async function saveProfile({ removeAvatar = false } = {}) {
        if (!els.profileForm) return;

        const formData = new FormData();
        formData.append('first_name', String(els.profileFirstNameInput?.value || '').trim());
        formData.append('last_name_paterno', String(els.profileLastPInput?.value || '').trim());
        formData.append('last_name_materno', String(els.profileLastMInput?.value || '').trim());
        formData.append('phone', String(els.profilePhoneInput?.value || '').trim());
        formData.append('email', String(els.profileEmailInput?.value || '').trim());

        const avatarFile = els.profileAvatarInput?.files?.[0] || null;
        if (avatarFile) {
            formData.append('avatar', avatarFile);
        }

        if (removeAvatar) {
            formData.append('remove_avatar', '1');
        }

        const result = await request('api/profile_update.php', {
            method: 'POST',
            body: formData,
        });

        state.me = result.user;
        syncProfileView();

        if (els.profileAvatarInput) {
            els.profileAvatarInput.value = '';
        }

        const mini = document.getElementById('sidebar-user-mini');
        if (mini) mini.textContent = `${state.me.name} - ${state.me.email}`;

        showToast(result.message || 'Perfil actualizado.', 'success');
        await loadContacts();
    }

    function setupProfileEditor() {
        if (els.profileForm) {
            els.profileForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    await saveProfile({ removeAvatar: false });
                } catch (error) {
                    showToast(error.message, 'error');
                }
            });
        }

        if (els.profileAvatarBtn && els.profileAvatarInput) {
            els.profileAvatarBtn.addEventListener('click', () => {
                els.profileAvatarInput.click();
            });

            els.profileAvatarInput.addEventListener('change', async () => {
                const hasFile = Boolean(els.profileAvatarInput.files && els.profileAvatarInput.files[0]);
                if (!hasFile) return;

                try {
                    await saveProfile({ removeAvatar: false });
                } catch (error) {
                    showToast(error.message, 'error');
                }
            });
        }

        if (els.profileAvatarRemoveBtn) {
            els.profileAvatarRemoveBtn.addEventListener('click', async () => {
                try {
                    await saveProfile({ removeAvatar: true });
                } catch (error) {
                    showToast(error.message, 'error');
                }
            });
        }
    }

    function setupFilters() {
        els.filters.forEach((filter) => {
            filter.addEventListener('click', () => {
                state.activeFilter = filter.dataset.filter || 'all';
                els.filters.forEach((item) => item.classList.toggle('active', item === filter));
                renderContacts();
            });
        });
    }

    function setupSearch() {
        if (!els.searchInput) return;

        let timer = null;
        els.searchInput.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                try {
                    await loadContacts();
                    renderContacts();
                } catch (error) {
                    showToast(error.message, 'error');
                }
            }, 280);
        });
    }

    function setupBiometricRegistration() {
        if (!els.registerPasskeyBtn) return;

        if (!window.ChatUbamWebAuthn || !window.ChatUbamWebAuthn.isSupported()) {
            els.registerPasskeyBtn.disabled = true;
            els.registerPasskeyBtn.title = 'WebAuthn no soportado por este navegador';
            return;
        }

        els.registerPasskeyBtn.addEventListener('click', async () => {
            try {
                const begin = await postJson('api/webauthn_begin_register.php', {});
                const credential = await window.ChatUbamWebAuthn.createCredential(begin.options);
                const finish = await postJson('api/webauthn_finish_register.php', credential);
                showToast(finish.message || 'Biometria registrada.', 'success');
                const me = await getJson('api/me.php');
                if (els.profilePasskeys) {
                    els.profilePasskeys.textContent = me.passkeys > 0
                        ? `Biometria registrada: ${me.passkeys} credencial(es)`
                        : 'Biometria registrada: ninguna';
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    function setupLogout() {
        if (!els.logoutBtn) return;
        els.logoutBtn.addEventListener('click', async () => {
            try {
                await postJson('api/logout.php', {});
                window.location.href = 'index.php';
            } catch (error) {
                showToast(error.message, 'error');
            }
        });
    }

    function setupMobileToggle() {
        if (!els.mobileToggle || !els.sidebar) return;

        els.mobileToggle.addEventListener('click', () => {
            if (els.sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        if (els.sidebarBackdrop) {
            els.sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        window.addEventListener('resize', () => {
            if (!isMobileLayout()) {
                closeSidebar();
            }
        });
    }

    async function bootstrap() {
        try {
            await loadCurrentUser();
            await loadContacts();
            setupComposer();
            setupProfilePanel();
            setupProfileEditor();
            setupFilters();
            setupSearch();
            setupBiometricRegistration();
            setupLogout();
            setupMobileToggle();

            renderContacts();

            state.contactsTimer = setInterval(() => {
                loadContacts().catch((error) => showToast(error.message, 'error'));
            }, 3500);

            state.messagesTimer = setInterval(() => {
                if (!state.activeContactId) return;
                loadMessages(false).catch((error) => showToast(error.message, 'error'));
            }, 1800);
        } catch (error) {
            showToast(error.message, 'error');
            if (String(error.message).toLowerCase().includes('autorizado')) {
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 800);
            }
        }
    }

    bootstrap();
})();
