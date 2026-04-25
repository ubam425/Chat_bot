(function () {
    function isSupported() {
        return typeof window.PublicKeyCredential !== 'undefined' && typeof navigator.credentials !== 'undefined';
    }

    function toBase64url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i += 1) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function fromBase64url(value) {
        const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
        const binary = atob(padded);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i += 1) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    async function createCredential(options) {
        if (!isSupported()) {
            throw new Error('El navegador no soporta WebAuthn.');
        }

        const publicKey = {
            ...options,
            challenge: fromBase64url(options.challenge),
            user: {
                ...options.user,
                id: fromBase64url(options.user.id),
            },
            excludeCredentials: (options.excludeCredentials || []).map((credential) => ({
                ...credential,
                id: fromBase64url(credential.id),
            })),
        };

        const credential = await navigator.credentials.create({ publicKey });
        if (!credential) {
            throw new Error('No se pudo crear la credencial biometrica.');
        }

        const response = credential.response;
        if (!response || typeof response.getPublicKey !== 'function') {
            throw new Error('Tu navegador no expone la llave publica necesaria. Usa Chrome/Edge actual.');
        }

        const publicKeyBuffer = response.getPublicKey();
        if (!publicKeyBuffer) {
            throw new Error('No fue posible obtener la llave publica de la biometria.');
        }

        return {
            credentialId: toBase64url(credential.rawId),
            clientDataJSON: toBase64url(response.clientDataJSON),
            publicKey: toBase64url(publicKeyBuffer),
            transports: typeof response.getTransports === 'function' ? response.getTransports() : [],
        };
    }

    async function getAssertion(options) {
        if (!isSupported()) {
            throw new Error('El navegador no soporta WebAuthn.');
        }

        const publicKey = {
            ...options,
            challenge: fromBase64url(options.challenge),
            allowCredentials: (options.allowCredentials || []).map((credential) => ({
                ...credential,
                id: fromBase64url(credential.id),
            })),
        };

        const assertion = await navigator.credentials.get({ publicKey });
        if (!assertion) {
            throw new Error('No se pudo validar biometria.');
        }

        const response = assertion.response;

        return {
            credentialId: toBase64url(assertion.rawId),
            clientDataJSON: toBase64url(response.clientDataJSON),
            authenticatorData: toBase64url(response.authenticatorData),
            signature: toBase64url(response.signature),
            userHandle: response.userHandle ? toBase64url(response.userHandle) : null,
        };
    }

    window.ChatUbamWebAuthn = {
        isSupported,
        createCredential,
        getAssertion,
    };
})();
