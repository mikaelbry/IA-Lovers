const AUTH_PAGES = new Set(['login.html', 'register.html']);

function normalizeRedirect(target) {
    const fallback = 'index.html';

    if (!target || typeof target !== 'string') {
        return fallback;
    }

    const trimmed = target.trim();
    if (!trimmed) {
        return fallback;
    }

    try {
        const base = window.WEB_BASE || window.location.origin;
        const url = new URL(trimmed, `${window.location.origin}${base.endsWith('/') ? base : `${base}/`}`);

        if (url.origin !== window.location.origin) {
            return fallback;
        }

        const page = url.pathname.split('/').pop() || fallback;
        return AUTH_PAGES.has(page) ? fallback : `${page}${url.search}${url.hash}`;
    } catch (error) {
        const page = trimmed.split('?')[0].split('/').pop() || fallback;
        return AUTH_PAGES.has(page) ? fallback : trimmed;
    }
}

function getRedirectUrl() {
    const params = new URLSearchParams(window.location.search);
    return normalizeRedirect(params.get('redirect'));
}

function getApiUrl(path) {
    const base = window.API || `${window.location.origin}/api`;
    return `${base}${path}`;
}

function getStatusElement(form) {
    return form.querySelector('.auth-status');
}

function setStatus(form, message = '', type = '') {
    const status = getStatusElement(form);
    if (!status) {
        return;
    }

    status.textContent = message;
    status.className = `auth-status${type ? ` ${type}` : ''}`;
}

function getAltchaWidget(form) {
    return form.querySelector('altcha-widget');
}

async function waitForAltchaDefinition(timeoutMs = 8000) {
    if (customElements.get('altcha-widget')) {
        return;
    }

    await Promise.race([
        customElements.whenDefined('altcha-widget'),
        new Promise((_, reject) => {
            window.setTimeout(() => reject(new Error('El script de ALTCHA no se ha cargado correctamente.')), timeoutMs);
        })
    ]);
}

function resetAltcha(form, message = '') {
    const widget = getAltchaWidget(form);

    if (widget && typeof widget.reset === 'function') {
        widget.reset();
    }

    if (message) {
        setStatus(form, message, 'info');
    }
}

function getAltchaPayload(form) {
    return new FormData(form).get('altcha') || '';
}

function isAltchaReady(widget, form) {
    if (!widget) {
        return false;
    }

    if (typeof widget.state === 'string' && widget.state.toLowerCase() === 'verified') {
        return Boolean(getAltchaPayload(form));
    }

    return Boolean(getAltchaPayload(form));
}

async function waitForAltchaVerification(widget, form) {
    return await new Promise((resolve, reject) => {
        const timeoutId = window.setTimeout(() => {
            cleanup();
            reject(new Error('ALTCHA ha tardado demasiado. Intentalo de nuevo.'));
        }, 15000);

        const onStateChange = (event) => {
            const state = String(event?.detail?.state || widget.state || '').toLowerCase();

            if (state === 'verified' && getAltchaPayload(form)) {
                cleanup();
                resolve(getAltchaPayload(form));
                return;
            }

            if (state === 'error' || state === 'expired' || state === 'failed') {
                cleanup();
                reject(new Error('No se pudo completar ALTCHA. Vuelve a verificar que eres humano.'));
            }
        };

        const cleanup = () => {
            clearTimeout(timeoutId);
            widget.removeEventListener('statechange', onStateChange);
        };

        widget.addEventListener('statechange', onStateChange);

        try {
            widget.verify();
        } catch (error) {
            cleanup();
            reject(new Error('No se pudo iniciar ALTCHA.'));
        }
    });
}

async function ensureAltcha(form) {
    if (!window.isSecureContext) {
        throw new Error('ALTCHA requiere un contexto seguro: abre la web en http://localhost o en HTTPS.');
    }

    await waitForAltchaDefinition();

    const widget = getAltchaWidget(form);

    if (!widget) {
        throw new Error('No se encontro el widget de ALTCHA.');
    }

    if (typeof widget.verify !== 'function') {
        throw new Error('ALTCHA no se ha inicializado correctamente.');
    }

    if (isAltchaReady(widget, form)) {
        return getAltchaPayload(form);
    }

    setStatus(form, 'Verificando que eres humano...', 'info');
    return await waitForAltchaVerification(widget, form);
}

function setButtonLoading(button, isLoading, idleText) {
    button.disabled = isLoading;
    button.textContent = isLoading ? 'Verificando...' : idleText;
}

async function register(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password-confirm').value;

    if (password !== passwordConfirm) {
        setStatus(form, 'Las contrasenas no coinciden.', 'error');
        return;
    }

    setStatus(form, '');
    setButtonLoading(btn, true, 'Registrarse');

    try {
        const altcha = await ensureAltcha(form);

        const data = {
            username: document.getElementById('username').value.trim(),
            email: document.getElementById('email').value.trim(),
            password,
            password_confirmation: passwordConfirm,
            altcha
        };

        const res = await fetch(getApiUrl('/register'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        if (!res.ok) {
            throw new Error(json.error || 'Error');
        }

        const next = `login.html?registered=1&email=${encodeURIComponent(data.email)}&redirect=${encodeURIComponent(getRedirectUrl())}`;
        window.location.href = next;
    } catch (err) {
        resetAltcha(form, 'Completa una nueva verificacion ALTCHA para volver a intentarlo.');
        setStatus(form, err.message, 'error');
        setButtonLoading(btn, false, 'Registrarse');
    }
}

async function login(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const data = {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value
    };

    setStatus(form, '');
    setButtonLoading(btn, true, 'Entrar');

    try {
        const altcha = await ensureAltcha(form);
        await autoLogin(data.email, data.password, altcha);
    } catch (err) {
        resetAltcha(form, 'Completa una nueva verificacion ALTCHA para volver a intentarlo.');
        setStatus(form, err.message, 'error');
        setButtonLoading(btn, false, 'Entrar');
    }
}

async function autoLogin(email, password, altchaPayload = '') {
    const res = await fetch(getApiUrl('/login'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, altcha: altchaPayload })
    });

    const json = await res.json();

    if (!res.ok) {
        throw new Error(json.error || 'Error');
    }

    localStorage.setItem('token', json.token);
    localStorage.setItem('user', JSON.stringify(json.user));

    window.location.href = getRedirectUrl();
}

function wireAuthLinks() {
    const redirect = getRedirectUrl();
    const registerLink = document.getElementById('register-link');
    const loginLink = document.getElementById('login-link');

    if (registerLink) {
        registerLink.href = `register.html?redirect=${encodeURIComponent(redirect)}`;
    }

    if (loginLink) {
        loginLink.href = `login.html?redirect=${encodeURIComponent(redirect)}`;
    }
}

async function bindAltchaStatus() {
    await waitForAltchaDefinition();

    document.querySelectorAll('.auth-form').forEach((form) => {
        const widget = getAltchaWidget(form);
        if (!widget) {
            return;
        }

        widget.addEventListener('statechange', (event) => {
            const state = String(event?.detail?.state || widget.state || '').toLowerCase();

            if (state === 'verified') {
                setStatus(form, 'Verificacion completada. Ya puedes enviar el formulario.', 'success');
                return;
            }

            if (state === 'verifying') {
                setStatus(form, 'Verificando que eres humano...', 'info');
                return;
            }

            if (state === 'expired') {
                setStatus(form, 'La verificacion ha caducado. Vuelve a intentarlo.', 'error');
                return;
            }

            if (state === 'error') {
                setStatus(form, 'ALTCHA ha encontrado un problema. Revisa la consola del navegador.', 'error');
            }
        });
    });
}

function restoreAuthContext() {
    const params = new URLSearchParams(window.location.search);
    const email = params.get('email');
    const registered = params.get('registered');
    const loginForm = document.querySelector('form.auth-form[data-mode="login"]');

    if (email) {
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.value = email;
        }
    }

    if (registered === '1' && loginForm) {
        setStatus(loginForm, 'Cuenta creada correctamente. Completa ALTCHA e inicia sesion.', 'success');
    }
}

async function initializeAuth() {
    wireAuthLinks();
    restoreAuthContext();

    if (!window.isSecureContext) {
        document.querySelectorAll('.auth-form').forEach((form) => {
            setStatus(form, 'ALTCHA requiere abrir la web en http://localhost o en HTTPS.', 'error');
        });
        return;
    }

    try {
        await bindAltchaStatus();
    } catch (error) {
        document.querySelectorAll('.auth-form').forEach((form) => {
            setStatus(form, error.message, 'error');
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initializeAuth();
});
