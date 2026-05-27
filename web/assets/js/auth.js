/**
 * Flujos de autenticación de la web.
 *
 * Gestiona login con CAPTCHA, registro con verificación por correo,
 * recuperación de contraseña, reenvío/cancelación de códigos y restauración
 * de contexto entre páginas de autenticación.
 */
const AUTH_PAGES = new Set(['login.html', 'register.html', 'forgot_password.html']);
const PENDING_REGISTRATION_KEY = 'pending-registration-flow';
const PENDING_PASSWORD_RESET_KEY = 'pending-password-reset-flow';

// Estado efímero del registro pendiente en la pestaña actual.
const registerState = {
    flowToken: null,
    email: null,
    maskedEmail: null,
    completed: false,
    resendCooldownSeconds: 30,
    resendAvailableAt: 0,
    resendTimer: null,
};

// Estado efímero de la recuperación de contraseña pendiente.
const passwordResetState = {
    flowToken: null,
    maskedEmail: null,
    completed: false,
    resendCooldownSeconds: 30,
    resendAvailableAt: 0,
    resendTimer: null,
};

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const wrapper = button.closest('.password-wrapper');

    if (input.type === 'password') {
        input.type = 'text';
        wrapper.classList.add('show-password');
    } else {
        input.type = 'password';
        wrapper.classList.remove('show-password');
    }
}

/** Normaliza redirects para impedir saltos externos o bucles entre páginas de auth. */
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
    const base = window.API || `${window.location.origin}/backend`;
    return `${base}${path}`;
}

function isRegisterPage() {
    return Boolean(document.getElementById('registerStartForm'));
}

function isPasswordResetPage() {
    return Boolean(document.getElementById('passwordResetStartForm'));
}

function getStatusElement(form) {
    return form ? form.querySelector('.auth-status') : null;
}

function setStatus(form, message = '', type = '') {
    const status = getStatusElement(form);
    if (!status) {
        return;
    }

    status.textContent = message;
    status.className = `auth-status${type ? ` ${type}` : ''}`;
}

function clearStatus(form) {
    setStatus(form, '');
}

function getAltchaWidget(form) {
    return form ? form.querySelector('altcha-widget') : null;
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
                reject(new Error('No se pudo completar el CAPTCHA. Vuelve a verificar que eres humano.'));
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

/** Garantiza que ALTCHA está cargado y devuelve el payload verificado del formulario. */
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

function setButtonLoading(button, isLoading, idleText, loadingText = 'Verificando...') {
    if (!button) {
        return;
    }

    button.disabled = isLoading;
    button.textContent = isLoading ? loadingText : idleText;
}

function formatCooldown(seconds) {
    const totalSeconds = Math.max(0, Math.ceil(Number(seconds) || 0));
    const minutes = Math.floor(totalSeconds / 60);
    const remainingSeconds = totalSeconds % 60;

    if (minutes <= 0) {
        return `${remainingSeconds}s`;
    }

    return `${minutes}min ${remainingSeconds}s`;
}

function passwordResetLockMessage(seconds) {
    return `Demasiados intentos. Espera ${formatCooldown(seconds)} antes de pedir otro codigo.`;
}

function getRegisterResendButton() {
    return document.getElementById('resendCodeButton');
}

function clearRegisterResendTimer() {
    if (registerState.resendTimer) {
        window.clearInterval(registerState.resendTimer);
        registerState.resendTimer = null;
    }
}

function updateRegisterResendButton() {
    const button = getRegisterResendButton();

    if (!button) {
        return;
    }

    const remainingMs = registerState.resendAvailableAt - Date.now();

    if (remainingMs > 0) {
        const remainingSeconds = Math.ceil(remainingMs / 1000);
        button.disabled = true;
        button.textContent = `Reenviar codigo (${remainingSeconds}s)`;
        return;
    }

    button.disabled = false;
    button.textContent = 'Reenviar codigo';
    clearRegisterResendTimer();
}

/** Bloquea temporalmente el reenvío de código de registro tras una solicitud. */
function startRegisterResendCooldown(seconds = registerState.resendCooldownSeconds) {
    registerState.resendCooldownSeconds = seconds;
    registerState.resendAvailableAt = Date.now() + (seconds * 1000);
    clearRegisterResendTimer();
    updateRegisterResendButton();
    registerState.resendTimer = window.setInterval(updateRegisterResendButton, 1000);
}

function getPasswordResetResendButton() {
    return document.getElementById('passwordResetResendButton');
}

function clearPasswordResetResendTimer() {
    if (passwordResetState.resendTimer) {
        window.clearInterval(passwordResetState.resendTimer);
        passwordResetState.resendTimer = null;
    }
}

function updatePasswordResetResendButton() {
    const button = getPasswordResetResendButton();

    if (!button) {
        return;
    }

    const remainingMs = passwordResetState.resendAvailableAt - Date.now();

    if (remainingMs > 0) {
        const remainingSeconds = Math.ceil(remainingMs / 1000);
        button.disabled = true;
        button.textContent = `Reenviar codigo (${remainingSeconds}s)`;
        return;
    }

    button.disabled = false;
    button.textContent = 'Reenviar codigo';
    clearPasswordResetResendTimer();
}

/** Bloquea temporalmente el reenvío de código de recuperación. */
function startPasswordResetResendCooldown(seconds = passwordResetState.resendCooldownSeconds) {
    passwordResetState.resendCooldownSeconds = seconds;
    passwordResetState.resendAvailableAt = Date.now() + (seconds * 1000);
    clearPasswordResetResendTimer();
    updatePasswordResetResendButton();
    passwordResetState.resendTimer = window.setInterval(updatePasswordResetResendButton, 1000);
}

/** Persiste en sessionStorage los datos mínimos del registro pendiente. */
function savePendingRegistration(flowToken, email, maskedEmail, resendCooldownSeconds = registerState.resendCooldownSeconds) {
    registerState.flowToken = flowToken;
    registerState.email = email;
    registerState.maskedEmail = maskedEmail;
    registerState.completed = false;
    registerState.resendCooldownSeconds = resendCooldownSeconds;

    sessionStorage.setItem(PENDING_REGISTRATION_KEY, JSON.stringify({
        flowToken,
        email,
        maskedEmail,
    }));
}

/** Limpia el registro pendiente y sus temporizadores. */
function clearPendingRegistrationState() {
    registerState.flowToken = null;
    registerState.email = null;
    registerState.maskedEmail = null;
    registerState.completed = false;
    registerState.resendAvailableAt = 0;
    clearRegisterResendTimer();
    sessionStorage.removeItem(PENDING_REGISTRATION_KEY);
    updateRegisterResendButton();
}

/** Persiste en sessionStorage los datos mínimos de la recuperación pendiente. */
function savePendingPasswordReset(flowToken, maskedEmail, resendCooldownSeconds = passwordResetState.resendCooldownSeconds) {
    passwordResetState.flowToken = flowToken;
    passwordResetState.maskedEmail = maskedEmail;
    passwordResetState.completed = false;
    passwordResetState.resendCooldownSeconds = resendCooldownSeconds;

    sessionStorage.setItem(PENDING_PASSWORD_RESET_KEY, JSON.stringify({
        flowToken,
        maskedEmail,
    }));
}

/** Limpia la recuperación pendiente y sus temporizadores. */
function clearPendingPasswordResetState() {
    passwordResetState.flowToken = null;
    passwordResetState.maskedEmail = null;
    passwordResetState.completed = false;
    passwordResetState.resendAvailableAt = 0;
    clearPasswordResetResendTimer();
    sessionStorage.removeItem(PENDING_PASSWORD_RESET_KEY);
    updatePasswordResetResendButton();
}

function setRegisterStep(step) {
    const dataStep = document.getElementById('registerDataStep');
    const verifyStep = document.getElementById('registerVerifyStep');

    if (!dataStep || !verifyStep) {
        return;
    }

    const showVerify = step === 'verify';
    dataStep.classList.toggle('hidden', showVerify);
    dataStep.classList.toggle('auth-step-active', !showVerify);
    dataStep.setAttribute('aria-hidden', showVerify ? 'true' : 'false');

    verifyStep.classList.toggle('hidden', !showVerify);
    verifyStep.classList.toggle('auth-step-active', showVerify);
    verifyStep.setAttribute('aria-hidden', showVerify ? 'false' : 'true');
}

function setPasswordResetStep(step) {
    const dataStep = document.getElementById('passwordResetDataStep');
    const verifyStep = document.getElementById('passwordResetVerifyStep');

    if (!dataStep || !verifyStep) {
        return;
    }

    const showVerify = step === 'verify';
    dataStep.classList.toggle('hidden', showVerify);
    dataStep.classList.toggle('auth-step-active', !showVerify);
    dataStep.setAttribute('aria-hidden', showVerify ? 'true' : 'false');

    verifyStep.classList.toggle('hidden', !showVerify);
    verifyStep.classList.toggle('auth-step-active', showVerify);
    verifyStep.setAttribute('aria-hidden', showVerify ? 'false' : 'true');
}

function updateVerifyEmailText(maskedEmail) {
    const target = document.getElementById('verifyEmailText');
    if (target) {
        target.textContent = `Introduce el codigo de 6 digitos enviado a ${maskedEmail}.`;
    }
}

function updatePasswordResetEmailText(maskedEmail) {
    const target = document.getElementById('passwordResetEmailText');
    if (target) {
        target.textContent = `Introduce el codigo de 6 digitos enviado a ${maskedEmail} y crea una nueva contraseña.`;
    }
}

function getCancelBeaconPayload() {
    if (!registerState.flowToken) {
        return null;
    }

    return JSON.stringify({ flow_token: registerState.flowToken });
}

function getPasswordResetCancelBeaconPayload() {
    if (!passwordResetState.flowToken) {
        return null;
    }

    return JSON.stringify({ flow_token: passwordResetState.flowToken });
}

/** Cancela el registro pendiente en backend y limpia la interfaz local. */
async function cancelPendingRegistration(options = {}) {
    const { useBeacon = false, silent = false, resetForm = false } = options;
    const payload = getCancelBeaconPayload();

    if (!payload) {
        if (resetForm) {
            setRegisterStep('form');
        }
        return;
    }

    const endpoint = getApiUrl('/register/cancel');

    try {
        if (useBeacon && navigator.sendBeacon) {
            const sent = navigator.sendBeacon(endpoint, new Blob([payload], { type: 'application/json' }));

            if (!sent) {
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                    keepalive: true,
                }).catch(() => {});
            }
        } else {
            await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
            });
        }
    } catch (error) {
    } finally {
        clearPendingRegistrationState();

        if (resetForm) {
            const startForm = document.getElementById('registerStartForm');
            const verifyForm = document.getElementById('registerVerifyForm');
            if (startForm) {
                resetAltcha(startForm);
                clearStatus(startForm);
            }
            if (verifyForm) {
                clearStatus(verifyForm);
                verifyForm.reset();
            }
            setRegisterStep('form');
        }

        if (!silent) {
            const startForm = document.getElementById('registerStartForm');
            if (startForm) {
                setStatus(startForm, 'El registro pendiente ha sido cancelado.', 'info');
            }
        }
    }
}

/** Cancela la recuperación pendiente en backend y limpia la interfaz local. */
async function cancelPendingPasswordReset(options = {}) {
    const { useBeacon = false, silent = false, resetForm = false } = options;
    const payload = getPasswordResetCancelBeaconPayload();

    if (!payload) {
        if (resetForm) {
            setPasswordResetStep('form');
        }
        return;
    }

    const endpoint = getApiUrl('/password-reset/cancel');

    try {
        if (useBeacon && navigator.sendBeacon) {
            const sent = navigator.sendBeacon(endpoint, new Blob([payload], { type: 'application/json' }));

            if (!sent) {
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                    keepalive: true,
                }).catch(() => {});
            }
        } else {
            await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
            });
        }
    } catch (error) {
    } finally {
        clearPendingPasswordResetState();

        if (resetForm) {
            const startForm = document.getElementById('passwordResetStartForm');
            const verifyForm = document.getElementById('passwordResetVerifyForm');
            if (startForm) {
                resetAltcha(startForm);
                clearStatus(startForm);
            }
            if (verifyForm) {
                clearStatus(verifyForm);
                verifyForm.reset();
            }
            setPasswordResetStep('form');
        }

        if (!silent) {
            const startForm = document.getElementById('passwordResetStartForm');
            if (startForm) {
                setStatus(startForm, 'La recuperacion pendiente ha sido cancelada.', 'info');
            }
        }
    }
}

/** Envía los datos iniciales de registro y cambia al paso de verificación. */
async function register(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password-confirm').value;

    if (password !== passwordConfirm) {
        setStatus(form, 'Las contraseñas no coinciden.', 'error');
        return;
    }

    clearStatus(form);
    setButtonLoading(btn, true, 'Continuar');

    try {
        const altcha = await ensureAltcha(form);

        const data = {
            username: document.getElementById('username').value.trim(),
            email: document.getElementById('email').value.trim(),
            password,
            password_confirmation: passwordConfirm,
            altcha,
        };

        const res = await fetch(getApiUrl('/register/start'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });

        const json = await res.json();

        if (!res.ok) {
            throw new Error(json.error || 'Error');
        }

        savePendingRegistration(
            json.flow_token,
            json.email,
            json.masked_email || json.email,
            Number(json.resend_cooldown) || registerState.resendCooldownSeconds
        );
        updateVerifyEmailText(json.masked_email || json.email);
        document.getElementById('verification-code').value = '';
        clearStatus(document.getElementById('registerVerifyForm'));
        setRegisterStep('verify');
        startRegisterResendCooldown(Number(json.resend_cooldown) || registerState.resendCooldownSeconds);
        setButtonLoading(btn, false, 'Continuar');
        setStatus(document.getElementById('registerVerifyForm'), 'Te hemos enviado un codigo al correo indicado.', 'success');
    } catch (err) {
        resetAltcha(form, 'Completa una nueva verificacion CAPTCHA para volver a intentarlo.');
        setStatus(form, err.message, 'error');
        setButtonLoading(btn, false, 'Continuar');
    }
}

/** Verifica el código de registro y finaliza la creación de cuenta. */
async function verifyRegistration(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const code = document.getElementById('verification-code').value.trim();

    if (!registerState.flowToken) {
        setStatus(form, 'El registro pendiente ya no esta disponible. Empieza de nuevo.', 'error');
        setRegisterStep('form');
        return;
    }

    clearStatus(form);
    setButtonLoading(btn, true, 'Crear cuenta', 'Comprobando...');

    try {
        const res = await fetch(getApiUrl('/register/verify'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                flow_token: registerState.flowToken,
                code,
            }),
        });

        const json = await res.json();

        if (!res.ok) {
            throw new Error(json.error || 'Error');
        }

        registerState.completed = true;
        const email = registerState.email;
        clearPendingRegistrationState();
        window.location.href = `login.html?registered=1&email=${encodeURIComponent(email)}&redirect=${encodeURIComponent(getRedirectUrl())}`;
    } catch (err) {
        setStatus(form, err.message, 'error');
        setButtonLoading(btn, false, 'Crear cuenta');
    }
}

/** Solicita un nuevo código para el registro pendiente. */
async function resendRegistrationCode() {
    const form = document.getElementById('registerVerifyForm');
    const button = getRegisterResendButton();

    if (!form || !registerState.flowToken || (button && button.disabled)) {
        return;
    }

    setStatus(form, 'Reenviando codigo...', 'info');
    if (button) {
        button.disabled = true;
    }

    try {
        const res = await fetch(getApiUrl('/register/resend'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ flow_token: registerState.flowToken }),
        });

        const json = await res.json();

        if (!res.ok) {
            const error = new Error(json.error || 'Error');
            error.retryAfter = Number(json.retry_after) || 0;
            throw error;
        }

        savePendingRegistration(
            json.flow_token,
            registerState.email,
            json.masked_email || registerState.maskedEmail || registerState.email,
            Number(json.resend_cooldown) || registerState.resendCooldownSeconds
        );
        updateVerifyEmailText(json.masked_email || registerState.maskedEmail || registerState.email);
        startRegisterResendCooldown(Number(json.resend_cooldown) || registerState.resendCooldownSeconds);
        setStatus(form, json.message || 'Hemos reenviado un nuevo codigo.', 'success');
    } catch (err) {
        if (err.retryAfter > 0) {
            startRegisterResendCooldown(err.retryAfter);
        } else {
            updateRegisterResendButton();
        }
        setStatus(form, err.message, 'error');
        return;
    }

    updateRegisterResendButton();
}

async function cancelRegistrationFlow() {
    await cancelPendingRegistration({ resetForm: true });
}

/** Inicia la recuperación de contraseña y muestra el paso de código. */
async function startPasswordReset(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const email = document.getElementById('reset-email').value.trim();

    clearStatus(form);
    setButtonLoading(btn, true, 'Enviar codigo');

    try {
        const altcha = await ensureAltcha(form);

        const res = await fetch(getApiUrl('/password-reset/start'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, altcha }),
        });

        const json = await res.json();

        if (!res.ok) {
            const error = new Error(json.error || 'Error');
            error.status = res.status;
            error.retryAfter = Number(json.retry_after) || 0;
            error.locked = Boolean(json.locked);
            throw error;
        }

        if (!json.flow_token) {
            setStatus(form, json.message || 'Si existe una cuenta con ese correo, recibiras un codigo.', 'success');
            resetAltcha(form);
            setButtonLoading(btn, false, 'Enviar codigo');
            return;
        }

        savePendingPasswordReset(
            json.flow_token,
            json.masked_email || email,
            Number(json.resend_cooldown) || passwordResetState.resendCooldownSeconds
        );
        updatePasswordResetEmailText(json.masked_email || email);
        document.getElementById('password-reset-code').value = '';
        document.getElementById('reset-password').value = '';
        document.getElementById('reset-password-confirm').value = '';
        clearStatus(document.getElementById('passwordResetVerifyForm'));
        setPasswordResetStep('verify');
        startPasswordResetResendCooldown(Number(json.resend_cooldown) || passwordResetState.resendCooldownSeconds);
        setButtonLoading(btn, false, 'Enviar codigo');
        setStatus(document.getElementById('passwordResetVerifyForm'), 'Te hemos enviado un codigo al correo de la cuenta.', 'success');
    } catch (err) {
        if (err.locked && err.retryAfter > 0) {
            clearPendingPasswordResetState();
            setPasswordResetStep('form');
            setStatus(form, passwordResetLockMessage(err.retryAfter), 'error');
            resetAltcha(form);
            setButtonLoading(btn, false, 'Enviar codigo');
            return;
        }

        resetAltcha(form, 'Completa una nueva verificacion CAPTCHA para volver a intentarlo.');
        setStatus(form, err.message, 'error');
        setButtonLoading(btn, false, 'Enviar codigo');
    }
}

/** Valida el código de recuperación y guarda la nueva contraseña. */
async function completePasswordReset(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const code = document.getElementById('password-reset-code').value.trim();
    const password = document.getElementById('reset-password').value;
    const passwordConfirm = document.getElementById('reset-password-confirm').value;

    if (!passwordResetState.flowToken) {
        setStatus(form, 'La recuperacion pendiente ya no esta disponible. Empieza de nuevo.', 'error');
        setPasswordResetStep('form');
        return;
    }

    if (password !== passwordConfirm) {
        setStatus(form, 'Las contraseñas no coinciden.', 'error');
        return;
    }

    clearStatus(form);
    setButtonLoading(btn, true, 'Cambiar contraseña', 'Comprobando...');

    try {
        const res = await fetch(getApiUrl('/password-reset/complete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                flow_token: passwordResetState.flowToken,
                code,
                password,
                password_confirmation: passwordConfirm,
            }),
        });

        const json = await res.json();

        if (!res.ok) {
            const error = new Error(json.error || 'Error');
            error.status = res.status;
            error.retryAfter = Number(json.retry_after) || 0;
            error.locked = Boolean(json.locked);
            throw error;
        }

        passwordResetState.completed = true;
        clearPendingPasswordResetState();
        if (typeof window.clearAuthSession === 'function') {
            await window.clearAuthSession({ skipServerLogout: true });
        }
        window.location.href = `login.html?reset=1&email=${encodeURIComponent(document.getElementById('reset-email').value.trim())}&redirect=${encodeURIComponent(getRedirectUrl())}`;
    } catch (err) {
        if (err.locked && err.retryAfter > 0) {
            clearPendingPasswordResetState();
            setPasswordResetStep('form');
            const startForm = document.getElementById('passwordResetStartForm');
            if (startForm) {
                setStatus(startForm, 'Has agotado los intentos. Vuelve a pedir un codigo después de 15 minutos.', 'error');
            }
            setButtonLoading(btn, false, 'Cambiar contraseña');
            return;
        }

        setStatus(form, err.message, 'error');
        setButtonLoading(btn, false, 'Cambiar contraseña');
    }
}

/** Solicita un nuevo código de recuperación respetando bloqueos y cooldown. */
async function resendPasswordResetCode() {
    const form = document.getElementById('passwordResetVerifyForm');
    const button = getPasswordResetResendButton();

    if (!form || !passwordResetState.flowToken || (button && button.disabled)) {
        return;
    }

    setStatus(form, 'Reenviando codigo...', 'info');
    if (button) {
        button.disabled = true;
    }

    try {
        const res = await fetch(getApiUrl('/password-reset/resend'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ flow_token: passwordResetState.flowToken }),
        });

        const json = await res.json();

        if (!res.ok) {
            const error = new Error(json.error || 'Error');
            error.status = res.status;
            error.retryAfter = Number(json.retry_after) || 0;
            error.locked = Boolean(json.locked);
            throw error;
        }

        savePendingPasswordReset(
            json.flow_token,
            json.masked_email || passwordResetState.maskedEmail,
            Number(json.resend_cooldown) || passwordResetState.resendCooldownSeconds
        );
        updatePasswordResetEmailText(json.masked_email || passwordResetState.maskedEmail);
        startPasswordResetResendCooldown(Number(json.resend_cooldown) || passwordResetState.resendCooldownSeconds);
        setStatus(form, json.message || 'Hemos reenviado un nuevo codigo.', 'success');
    } catch (err) {
        if (err.locked && err.retryAfter > 0) {
            clearPendingPasswordResetState();
            setPasswordResetStep('form');
            const startForm = document.getElementById('passwordResetStartForm');
            if (startForm) {
                setStatus(startForm, 'Has agotado los intentos. Vuelve a pedir un codigo después de 15 minutos.', 'error');
            }
        } else if (err.retryAfter > 0) {
            startPasswordResetResendCooldown(err.retryAfter);
            setStatus(form, `Espera ${formatCooldown(err.retryAfter)} antes de pedir otro codigo.`, 'error');
        } else {
            updatePasswordResetResendButton();
            setStatus(form, err.message, 'error');
        }
        return;
    }

    updatePasswordResetResendButton();
}

async function cancelPasswordResetFlow() {
    await cancelPendingPasswordReset({ resetForm: true });
}

/** Valida ALTCHA e inicia sesión guardando token y usuario en localStorage. */
async function login(event) {
    event.preventDefault();

    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const data = {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value,
    };

    clearStatus(form);
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

/** Ejecuta el login contra la API y redirige al destino solicitado. */
async function autoLogin(email, password, altchaPayload = '') {
    const res = await fetch(getApiUrl('/login'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, altcha: altchaPayload }),
    });

    const json = await res.json();

    if (!res.ok) {
        throw new Error(json.error || 'Error');
    }

    localStorage.setItem('token', json.token);
    localStorage.setItem('user', JSON.stringify(json.user));
    window.token = json.token;
    window.user = json.user;

    window.location.href = getRedirectUrl();
}

/** Sincroniza enlaces entre login, registro y recuperación conservando redirect. */
function wireAuthLinks() {
    const redirect = getRedirectUrl();
    const registerLink = document.getElementById('register-link');
    const loginLink = document.getElementById('login-link');
    const forgotPasswordLink = document.getElementById('forgot-password-link');

    if (registerLink) {
        registerLink.href = `register.html?redirect=${encodeURIComponent(redirect)}`;
    }

    if (loginLink) {
        loginLink.href = `login.html?redirect=${encodeURIComponent(redirect)}`;
    }

    if (forgotPasswordLink) {
        const buildForgotHref = () => {
            const emailInput = document.getElementById('email');
            const emailParam = emailInput && emailInput.value.trim()
                ? `&email=${encodeURIComponent(emailInput.value.trim())}`
                : '';
            return `forgot_password.html?redirect=${encodeURIComponent(redirect)}${emailParam}`;
        };

        forgotPasswordLink.href = buildForgotHref();
        forgotPasswordLink.addEventListener('click', () => {
            forgotPasswordLink.href = buildForgotHref();
        });
    }
}

/** Traduce cambios de estado de ALTCHA a mensajes visibles en cada formulario. */
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
                setStatus(form, 'Verificacion completada. Ya puedes continuar.', 'success');
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

/** Restaura email, mensajes flash y avisos tras registro o recuperación. */
function restoreAuthContext() {
    const params = new URLSearchParams(window.location.search);
    const email = params.get('email');
    const registered = params.get('registered');
    const reset = params.get('reset');
    const loginForm = document.querySelector('form.auth-form[data-mode="login"]');
    const flash = typeof window.consumeAuthFlash === 'function'
        ? window.consumeAuthFlash()
        : null;

    if (email) {
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.value = email;
        }
    }

    if (flash && loginForm) {
        setStatus(loginForm, flash.message || 'Tu sesion ha caducado. Inicia sesion de nuevo.', flash.type || 'error');
        return;
    }

    if (registered === '1' && loginForm) {
        setStatus(loginForm, 'Cuenta creada correctamente. Completa el CAPTCHA e inicia sesion.', 'success');
    }

    if (reset === '1' && loginForm) {
        setStatus(loginForm, 'Contraseña actualizada correctamente. Completa el CAPTCHA e inicia sesion.', 'success');
    }
}

function clearOrphanPendingRegistration() {
    if (!isRegisterPage()) {
        return;
    }

    clearPendingRegistrationState();
}

function clearOrphanPendingPasswordReset() {
    if (!isPasswordResetPage()) {
        return;
    }

    clearPendingPasswordResetState();
}

function bindRegisterPageLifecycle() {
    if (!isRegisterPage()) {
        return;
    }

    window.addEventListener('pagehide', () => {
        if (registerState.flowToken && !registerState.completed) {
            cancelPendingRegistration({ useBeacon: true, silent: true });
        }
    });
}

function bindPasswordResetPageLifecycle() {
    if (!isPasswordResetPage()) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const email = params.get('email');
    const resetEmailInput = document.getElementById('reset-email');
    if (email && resetEmailInput) {
        resetEmailInput.value = email;
    }

    window.addEventListener('pagehide', () => {
        if (passwordResetState.flowToken && !passwordResetState.completed) {
            cancelPendingPasswordReset({ useBeacon: true, silent: true });
        }
    });
}

/** Inicializa la página de autenticación actual y sus ciclos de vida. */
async function initializeAuth() {
    wireAuthLinks();
    restoreAuthContext();
    clearOrphanPendingRegistration();
    clearOrphanPendingPasswordReset();
    bindRegisterPageLifecycle();
    bindPasswordResetPageLifecycle();
    updateRegisterResendButton();
    updatePasswordResetResendButton();

    if (!window.isSecureContext) {
        document.querySelectorAll('.auth-form').forEach((form) => {
            if (getAltchaWidget(form)) {
                setStatus(form, 'ALTCHA requiere abrir la web en http://localhost o en HTTPS.', 'error');
            }
        });
        return;
    }

    try {
        await bindAltchaStatus();
    } catch (error) {
        document.querySelectorAll('.auth-form').forEach((form) => {
            if (getAltchaWidget(form)) {
                setStatus(form, error.message, 'error');
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initializeAuth();
});
