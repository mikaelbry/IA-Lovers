const token = localStorage.getItem("token");
const settingsForm = document.getElementById("settingsForm");
const settingsNav = document.getElementById("settingsNav");
const panelTitle = document.getElementById("panelTitle");
const panelBody = document.getElementById("panelBody");
const settingsActions = document.getElementById("settingsActions");
const panelButtons = document.getElementById("panelButtons");
const saveButton = document.getElementById("saveButton");
const cancelButton = document.getElementById("cancelButton");
const statusEl = document.getElementById("formStatus");
const summaryAvatar = document.getElementById("summaryAvatar");

const state = {
    user: null,
    postsCount: 0,
    followersCount: 0,
    activeSection: "account",
    avatarPreview: null,
    avatarFile: null,
    usernameCheck: {
        available: false,
        checking: false,
        message: "",
        type: "",
        requestId: 0,
        timer: null
    },
    emailChange: {
        pending: false,
        newEmail: "",
        maskedEmail: "",
        code: "",
        resendCooldownSeconds: 30,
        resendAvailableAt: 0,
        resendTimer: null
    },
    deleteFlow: {
        confirmStep: false,
        password: ""
    }
};

if (!token) {
    window.location.href = publicUrl("login.html");
}

async function requestJson(url, options = {}) {
    const response = await fetch(url, options);
    const contentType = response.headers.get("content-type") || "";
    const data = contentType.includes("application/json") ? await response.json() : {};

    if (!response.ok) {
        const error = new Error(data.error || "Error");
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    }[char]));
}

function formatCreatedAt(dateString) {
    if (!dateString) {
        return "Fecha no disponible";
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return "Fecha no disponible";
    }

    return date.toLocaleDateString("es-ES", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
    });
}

function setStatus(message = "", type = "") {
    statusEl.textContent = message;
    statusEl.className = `settings-status${type ? ` ${type}` : ""}`;
}

function getSaveLabel() {
    const labels = {
        avatar: "Guardar avatar",
        username: "Cambiar nombre de usuario",
        email: state.emailChange.pending ? "Confirmar codigo" : "Enviar codigo",
        password: "Cambiar contrasena",
        delete: state.deleteFlow.confirmStep ? "Borrar cuenta definitivamente" : "Continuar"
    };

    return labels[state.activeSection] || "Guardar cambios";
}

function setLoading(isLoading) {
    saveButton.disabled = isLoading || isPrimaryDisabled();
    cancelButton.disabled = isLoading;
    saveButton.textContent = isLoading ? "Procesando..." : getSaveLabel();
}

function updateSummary() {
    document.getElementById("profileName").textContent = state.user.username;
    document.getElementById("profileEmail").textContent = state.user.email;
    document.getElementById("createdAtText").textContent = formatCreatedAt(state.user.created_at);
    summaryAvatar.src = state.avatarPreview || state.user.avatar_url || "assets/images/logo.jpg";
}

function syncStoredUser() {
    const storedUser = JSON.parse(localStorage.getItem("user") || "{}");
    storedUser.username = state.user.username;
    storedUser.avatar_url = state.user.avatar_url || null;
    localStorage.setItem("user", JSON.stringify(storedUser));

    const navLabel = document.querySelector(".nav-user-label");
    if (navLabel) {
        navLabel.textContent = state.user.username;
    }
}

function resetAvatarDraft() {
    if (state.avatarPreview && state.avatarPreview.startsWith("blob:")) {
        URL.revokeObjectURL(state.avatarPreview);
    }

    state.avatarPreview = null;
    state.avatarFile = null;
    updateSummary();
}

function resetEmailChangeState() {
    clearEmailChangeResendTimer();
    state.emailChange.pending = false;
    state.emailChange.newEmail = "";
    state.emailChange.maskedEmail = "";
    state.emailChange.code = "";
    state.emailChange.resendAvailableAt = 0;
}

function getEmailResendButton() {
    return document.getElementById("emailResendButton");
}

function clearEmailChangeResendTimer() {
    if (state.emailChange.resendTimer) {
        window.clearInterval(state.emailChange.resendTimer);
        state.emailChange.resendTimer = null;
    }
}

function updateEmailResendButton() {
    const button = getEmailResendButton();

    if (!button) {
        return;
    }

    const remainingMs = state.emailChange.resendAvailableAt - Date.now();

    if (remainingMs > 0) {
        const remainingSeconds = Math.ceil(remainingMs / 1000);
        button.disabled = true;
        button.textContent = `Reenviar codigo (${remainingSeconds}s)`;
        return;
    }

    button.disabled = false;
    button.textContent = "Reenviar codigo";
    clearEmailChangeResendTimer();
}

function startEmailChangeResendCooldown(seconds = state.emailChange.resendCooldownSeconds) {
    state.emailChange.resendCooldownSeconds = seconds;
    state.emailChange.resendAvailableAt = Date.now() + (seconds * 1000);
    clearEmailChangeResendTimer();
    updateEmailResendButton();
    state.emailChange.resendTimer = window.setInterval(updateEmailResendButton, 1000);
}

function getSections() {
    return {
        account: {
            title: "Informacion de cuenta",
            showActions: false,
            render: () => `
                <div class="settings-info-grid">
                    <div class="settings-info-item">
                        <span class="settings-info-label">Nombre de usuario</span>
                        <span class="settings-info-value">${escapeHtml(state.user.username)}</span>
                    </div>
                    <div class="settings-info-item">
                        <span class="settings-info-label">Correo</span>
                        <span class="settings-info-value">${escapeHtml(state.user.email)}</span>
                    </div>
                    <div class="settings-info-item">
                        <span class="settings-info-label">Seguidores</span>
                        <span class="settings-info-value">${state.followersCount}</span>
                    </div>
                    <div class="settings-info-item">
                        <span class="settings-info-label">Posts</span>
                        <span class="settings-info-value">${state.postsCount}</span>
                    </div>
                    <div class="settings-info-item settings-info-item-full">
                        <span class="settings-info-label">Cuenta creada el</span>
                        <span class="settings-info-value">${formatCreatedAt(state.user.created_at)}</span>
                    </div>
                </div>
            `,
            onShow: () => setStatus(""),
            onCancel: async () => {}
        },
        avatar: {
            title: "Cambiar avatar",
            showActions: true,
            render: () => `
                <div class="settings-avatar-panel">
                    <img src="${state.avatarPreview || state.user.avatar_url || "assets/images/logo.jpg"}" alt="Preview del avatar" class="settings-avatar-preview" id="avatarPreviewImage">
                    <div class="settings-field settings-field-full">
                        <label for="avatarInput">Nueva imagen</label>
                        <input type="file" id="avatarInput" class="settings-file-input" accept="image/png,image/jpeg,image/webp">
                    </div>
                </div>
            `,
            onShow: () => setStatus(""),
            onCancel: async () => {
                resetAvatarDraft();
                goToAccountSection();
            }
        },
        username: {
            title: "Cambiar nombre de usuario",
            showActions: true,
            render: () => `
                <div class="settings-form-grid">
                    <div class="settings-field settings-field-full">
                        <label for="currentUsernameInput">Nombre de usuario actual</label>
                        <input type="text" id="currentUsernameInput" value="${escapeHtml(state.user.username)}" readonly>
                    </div>
                    <div class="settings-field settings-field-full">
                        <label for="usernameInput">Nuevo nombre de usuario</label>
                        <input type="text" id="usernameInput" value="" autocomplete="username" required>
                        <div id="usernameAvailability" class="settings-availability"></div>
                    </div>
                    <div class="settings-field settings-field-full">
                        <label for="usernamePasswordInput">Contrasena actual</label>
                        <input type="password" id="usernamePasswordInput" autocomplete="current-password" required>
                    </div>
                </div>
            `,
            onShow: () => setStatus(""),
            onCancel: async () => goToAccountSection()
        },
        email: {
            title: "Cambiar correo",
            showActions: true,
            render: () => `
                <div class="settings-form-grid">
                    <div class="settings-field settings-field-full">
                        <label for="currentEmailInput">Correo actual</label>
                        <input type="email" id="currentEmailInput" value="${escapeHtml(state.user.email)}" readonly>
                    </div>
                    <div class="settings-field settings-field-full">
                        <label for="newEmailInput">Nuevo correo</label>
                        <input type="email" id="newEmailInput" value="${escapeHtml(state.emailChange.newEmail)}" autocomplete="email" ${state.emailChange.pending ? "readonly" : "required"}>
                    </div>
                    ${state.emailChange.pending ? `
                        <div class="settings-field settings-field-full">
                            <label for="emailCodeInput">Codigo de verificacion</label>
                            <input type="text" id="emailCodeInput" value="${escapeHtml(state.emailChange.code)}" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Codigo de 6 digitos" required>
                        </div>
                        <div class="settings-inline-actions settings-field-full">
                            <button type="button" id="emailResendButton" class="settings-link-btn" onclick="resendEmailChangeCode()">Reenviar codigo</button>
                        </div>
                    ` : `
                        <div class="settings-field settings-field-full">
                            <label for="emailPasswordInput">Contrasena actual</label>
                            <input type="password" id="emailPasswordInput" autocomplete="current-password" required>
                        </div>
                    `}
                </div>
            `,
            onShow: () => setStatus(""),
            onCancel: async () => {
                await cancelEmailChangeFlow({ goToAccount: true, silent: true });
            }
        },
        password: {
            title: "Cambiar contrasena",
            showActions: true,
            render: () => `
                <div class="settings-form-grid">
                    <div class="settings-field settings-field-full">
                        <label for="currentPasswordInput">Contrasena actual</label>
                        <input type="password" id="currentPasswordInput" autocomplete="current-password" required>
                    </div>
                    <div class="settings-field settings-field-full">
                        <label for="passwordInput">Nueva contrasena</label>
                        <input type="password" id="passwordInput" autocomplete="new-password" required>
                    </div>
                    <div class="settings-field settings-field-full">
                        <label for="passwordConfirmInput">Confirmar contrasena</label>
                        <input type="password" id="passwordConfirmInput" autocomplete="new-password" required>
                    </div>
                </div>
            `,
            onShow: () => setStatus(""),
            onCancel: async () => goToAccountSection()
        },
        delete: {
            title: "Borrar la cuenta",
            showActions: true,
            render: () => `
                <div class="settings-form-grid">
                    <div class="settings-info-item settings-info-item-full settings-danger-box">
                        <span class="settings-info-label">Atencion</span>
                        <span class="settings-info-value">Esta accion es irreversible</span>
                    </div>
                    <div class="settings-field settings-field-full">
                        <label for="deletePasswordInput">Contrasena actual</label>
                        <input type="password" id="deletePasswordInput" autocomplete="current-password" value="${escapeHtml(state.deleteFlow.password)}" required>
                    </div>
                    ${state.deleteFlow.confirmStep ? `
                        <div class="settings-field settings-field-full">
                            <label for="deleteConfirmInput">Escribe exactamente ELIMINAR MI CUENTA</label>
                            <input type="text" id="deleteConfirmInput" placeholder="ELIMINAR MI CUENTA" autocomplete="off" required>
                        </div>
                    ` : ""}
                </div>
            `,
            onShow: () => setStatus(""),
            onCancel: async () => {
                state.deleteFlow.confirmStep = false;
                state.deleteFlow.password = "";
                goToAccountSection();
            }
        }
    };
}

function goToAccountSection(message = "", type = "") {
    state.activeSection = "account";
    renderActiveSection();
    setStatus(message, type);
}

function setUsernameAvailability(message, type = "") {
    state.usernameCheck.message = message;
    state.usernameCheck.type = type;

    const availability = document.getElementById("usernameAvailability");
    if (availability) {
        availability.className = `settings-availability${type ? ` ${type}` : ""}`;
        availability.textContent = message;
    }
}

async function checkUsernameAvailability(username) {
    const requestId = ++state.usernameCheck.requestId;
    state.usernameCheck.checking = true;
    state.usernameCheck.available = false;
    setUsernameAvailability("Comprobando disponibilidad...", "checking");
    updatePrimaryState();

    try {
        const response = await requestJson(`${apiUrl("/users/check-username")}?username=${encodeURIComponent(username)}`, {
            headers: { Authorization: "Bearer " + token }
        });

        if (requestId !== state.usernameCheck.requestId) {
            return;
        }

        state.usernameCheck.checking = false;
        state.usernameCheck.available = response.available;
        setUsernameAvailability(
            response.available ? "Este nombre de usuario esta libre." : "Este nombre de usuario ya esta cogido.",
            response.available ? "success" : "error"
        );
    } catch (error) {
        if (requestId !== state.usernameCheck.requestId) {
            return;
        }

        state.usernameCheck.checking = false;
        state.usernameCheck.available = false;
        setUsernameAvailability(error.message, "error");
    }

    updatePrimaryState();
}

async function cancelEmailChangeFlow({ goToAccount = false, silent = false } = {}) {
    if (state.emailChange.pending) {
        try {
            await requestJson(apiUrl("/user/email-change/cancel"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Authorization: "Bearer " + token
                },
                body: JSON.stringify({})
            });
        } catch (error) {
        }
    }

    resetEmailChangeState();

    if (goToAccount) {
        goToAccountSection();
    } else if (!silent && state.activeSection === "email") {
        renderActiveSection();
        setStatus("");
    }
}

async function resendEmailChangeCode() {
    if (!state.emailChange.pending) {
        return;
    }

    const button = getEmailResendButton();

    if (button && button.disabled) {
        return;
    }

    setStatus("Reenviando codigo...", "info");

    if (button) {
        button.disabled = true;
    }

    try {
        const response = await requestJson(apiUrl("/user/email-change/resend"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Authorization: "Bearer " + token
            },
            body: JSON.stringify({})
        });

        state.emailChange.maskedEmail = response.masked_email || state.emailChange.maskedEmail;
        startEmailChangeResendCooldown(Number(response.resend_cooldown) || state.emailChange.resendCooldownSeconds);
        setStatus(`Hemos reenviado un codigo a ${state.emailChange.maskedEmail}.`, "success");
    } catch (error) {
        if (error.data && Number(error.data.retry_after) > 0) {
            startEmailChangeResendCooldown(Number(error.data.retry_after));
        } else {
            updateEmailResendButton();
        }
        setStatus(error.message, "error");
    }
}

function bindSectionEvents() {
    if (state.activeSection === "avatar") {
        const avatarInput = document.getElementById("avatarInput");

        if (avatarInput) {
            avatarInput.addEventListener("change", (event) => {
                const file = event.target.files && event.target.files[0];

                if (!file) {
                    state.avatarFile = null;
                    updatePrimaryState();
                    return;
                }

                if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
                    avatarInput.value = "";
                    state.avatarFile = null;
                    setStatus("El avatar debe ser JPG, PNG o WEBP.", "error");
                    updatePrimaryState();
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    avatarInput.value = "";
                    state.avatarFile = null;
                    setStatus("El avatar no puede superar los 2 MB.", "error");
                    updatePrimaryState();
                    return;
                }

                if (state.avatarPreview && state.avatarPreview.startsWith("blob:")) {
                    URL.revokeObjectURL(state.avatarPreview);
                }

                state.avatarFile = file;
                state.avatarPreview = URL.createObjectURL(file);
                summaryAvatar.src = state.avatarPreview;

                const previewImage = document.getElementById("avatarPreviewImage");
                if (previewImage) {
                    previewImage.src = state.avatarPreview;
                }

                setStatus("Avatar listo para guardar.", "success");
                updatePrimaryState();
            });
        }
    }

    if (state.activeSection === "username") {
        const usernameInput = document.getElementById("usernameInput");
        const passwordInput = document.getElementById("usernamePasswordInput");

        const evaluateUsername = () => {
            clearTimeout(state.usernameCheck.timer);

            const value = usernameInput.value.trim();

            if (!value) {
                state.usernameCheck.checking = false;
                state.usernameCheck.available = false;
                setUsernameAvailability("Introduce un nombre de usuario nuevo.");
                updatePrimaryState();
                return;
            }

            if (value === state.user.username) {
                state.usernameCheck.checking = false;
                state.usernameCheck.available = false;
                setUsernameAvailability("Introduce un nombre de usuario distinto al actual.");
                updatePrimaryState();
                return;
            }

            state.usernameCheck.timer = window.setTimeout(() => {
                checkUsernameAvailability(value);
            }, 300);
        };

        usernameInput.addEventListener("input", evaluateUsername);
        passwordInput.addEventListener("input", updatePrimaryState);
        evaluateUsername();
    }

    if (state.activeSection === "email") {
        const newEmailInput = document.getElementById("newEmailInput");

        if (newEmailInput) {
            newEmailInput.addEventListener("input", updatePrimaryState);
        }

        if (state.emailChange.pending) {
            const emailCodeInput = document.getElementById("emailCodeInput");

            if (emailCodeInput) {
                emailCodeInput.addEventListener("input", () => {
                    state.emailChange.code = emailCodeInput.value.trim();
                    updatePrimaryState();
                });
            }

            updateEmailResendButton();
        } else {
            const emailPasswordInput = document.getElementById("emailPasswordInput");

            if (emailPasswordInput) {
                emailPasswordInput.addEventListener("input", updatePrimaryState);
            }
        }
    }

    if (state.activeSection === "password") {
        const currentPasswordInput = document.getElementById("currentPasswordInput");
        const passwordInput = document.getElementById("passwordInput");
        const passwordConfirmInput = document.getElementById("passwordConfirmInput");

        if (currentPasswordInput) {
            currentPasswordInput.addEventListener("input", updatePrimaryState);
        }

        if (passwordInput) {
            passwordInput.addEventListener("input", updatePrimaryState);
        }

        if (passwordConfirmInput) {
            passwordConfirmInput.addEventListener("input", updatePrimaryState);
        }
    }

    if (state.activeSection === "delete") {
        const deletePasswordInput = document.getElementById("deletePasswordInput");
        const deleteConfirmInput = document.getElementById("deleteConfirmInput");

        if (deletePasswordInput) {
            deletePasswordInput.addEventListener("input", () => {
                state.deleteFlow.password = deletePasswordInput.value;
                updatePrimaryState();
            });
        }

        if (deleteConfirmInput) {
            deleteConfirmInput.addEventListener("input", updatePrimaryState);
        }
    }
}

function isPrimaryDisabled() {
    if (state.activeSection === "avatar") {
        return !state.avatarFile;
    }

    if (state.activeSection === "username") {
        const passwordInput = document.getElementById("usernamePasswordInput");
        return state.usernameCheck.checking || !state.usernameCheck.available || !passwordInput || passwordInput.value.trim() === "";
    }

    if (state.activeSection === "email") {
        const newEmailInput = document.getElementById("newEmailInput");
        const newEmail = newEmailInput ? newEmailInput.value.trim() : "";

        if (state.emailChange.pending) {
            const codeInput = document.getElementById("emailCodeInput");
            const code = codeInput ? codeInput.value.trim() : state.emailChange.code;
            return !/^\d{6}$/.test(code);
        }

        const emailPasswordInput = document.getElementById("emailPasswordInput");
        const password = emailPasswordInput ? emailPasswordInput.value.trim() : "";
        return newEmail === "" || newEmail === state.user.email || password === "";
    }

    if (state.activeSection === "password") {
        const currentPasswordInput = document.getElementById("currentPasswordInput");
        const passwordInput = document.getElementById("passwordInput");
        const passwordConfirmInput = document.getElementById("passwordConfirmInput");

        return !currentPasswordInput
            || currentPasswordInput.value.trim() === ""
            || !passwordInput
            || passwordInput.value.trim() === ""
            || !passwordConfirmInput
            || passwordConfirmInput.value.trim() === "";
    }

    if (state.activeSection === "delete") {
        const hasPassword = state.deleteFlow.password.trim() !== "";

        if (!state.deleteFlow.confirmStep) {
            return !hasPassword;
        }

        const confirmInput = document.getElementById("deleteConfirmInput");
        return !hasPassword || !confirmInput || confirmInput.value.trim() !== "ELIMINAR MI CUENTA";
    }

    return false;
}

function updatePrimaryState() {
    saveButton.textContent = getSaveLabel();
    saveButton.disabled = isPrimaryDisabled();
}

function renderActiveSection() {
    const sections = getSections();
    const section = sections[state.activeSection];

    clearTimeout(state.usernameCheck.timer);
    state.usernameCheck.requestId += 1;
    state.usernameCheck.checking = false;

    document.querySelectorAll(".settings-nav-button").forEach((button) => {
        button.classList.toggle("active", button.dataset.section === state.activeSection);
    });

    panelTitle.textContent = section.title;
    panelBody.innerHTML = section.render();
    settingsActions.classList.toggle("hidden", !section.showActions);
    panelButtons.classList.toggle("hidden", !section.showActions);
    cancelButton.textContent = "Cancelar";
    bindSectionEvents();
    section.onShow();
    updatePrimaryState();
}

async function updateProfile(payload) {
    await requestJson(apiUrl("/user/update"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify(payload)
    });
}

async function uploadAvatar(file) {
    const formData = new FormData();
    formData.append("avatar", file);

    return await requestJson(apiUrl("/user/avatar"), {
        method: "POST",
        headers: {
            Authorization: "Bearer " + token
        },
        body: formData
    });
}

async function startEmailChange(payload) {
    return await requestJson(apiUrl("/user/email-change/start"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify(payload)
    });
}

async function verifyEmailChange(payload) {
    return await requestJson(apiUrl("/user/email-change/verify"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify(payload)
    });
}

async function deleteAccount(payload) {
    await requestJson(apiUrl("/user/delete"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify(payload)
    });
}

async function handleSectionSubmit() {
    if (state.activeSection === "avatar") {
        if (!state.avatarFile) {
            throw new Error("Primero selecciona una imagen para el avatar.");
        }

        const response = await uploadAvatar(state.avatarFile);

        resetAvatarDraft();
        state.user.avatar_path = response.avatar_path;
        state.user.avatar_url = response.avatar_url;
        syncStoredUser();
        updateSummary();
        renderActiveSection();
        setStatus("Avatar actualizado correctamente.", "success");
        return;
    }

    if (state.activeSection === "username") {
        const username = document.getElementById("usernameInput").value.trim();
        const currentPassword = document.getElementById("usernamePasswordInput").value.trim();

        if (!state.usernameCheck.available) {
            throw new Error("El nuevo nombre de usuario no esta disponible.");
        }

        if (!currentPassword) {
            throw new Error("Debes introducir tu contrasena actual.");
        }

        await updateProfile({
            username,
            email: state.user.email,
            current_password: currentPassword
        });

        state.user.username = username;
        syncStoredUser();
        updateSummary();
        renderActiveSection();
        setStatus("Nombre de usuario actualizado correctamente.", "success");
        return;
    }

    if (state.activeSection === "email") {
        if (!state.emailChange.pending) {
            const newEmail = document.getElementById("newEmailInput").value.trim();
            const currentPassword = document.getElementById("emailPasswordInput").value.trim();

            if (!newEmail) {
                throw new Error("Debes introducir un nuevo correo.");
            }

            if (!currentPassword) {
                throw new Error("Debes introducir tu contrasena actual.");
            }

            const response = await startEmailChange({
                new_email: newEmail,
                current_password: currentPassword
            });

            state.emailChange.pending = true;
            state.emailChange.newEmail = response.new_email || newEmail;
            state.emailChange.maskedEmail = response.masked_email || newEmail;
            state.emailChange.code = "";
            startEmailChangeResendCooldown(Number(response.resend_cooldown) || state.emailChange.resendCooldownSeconds);
            renderActiveSection();
            setStatus(`Hemos enviado un codigo a ${state.emailChange.maskedEmail}.`, "success");
            return;
        }

        const code = document.getElementById("emailCodeInput").value.trim();

        if (!/^\d{6}$/.test(code)) {
            throw new Error("El codigo debe tener 6 digitos.");
        }

        try {
            const response = await verifyEmailChange({ code });
            state.user.email = response.email;
            resetEmailChangeState();
            updateSummary();
            goToAccountSection("Correo actualizado correctamente.", "success");
        } catch (error) {
            if ([404, 410, 429].includes(error.status)) {
                resetEmailChangeState();
                renderActiveSection();
            }
            throw error;
        }

        return;
    }

    if (state.activeSection === "password") {
        const currentPassword = document.getElementById("currentPasswordInput").value.trim();
        const password = document.getElementById("passwordInput").value.trim();
        const confirm = document.getElementById("passwordConfirmInput").value.trim();

        if (!currentPassword) {
            throw new Error("Debes introducir tu contrasena actual.");
        }

        if (password.length < 8 || !/[A-Za-z]/.test(password) || !/\d/.test(password)) {
            throw new Error("La contrasena debe tener al menos 8 caracteres e incluir letras y numeros.");
        }

        if (password !== confirm) {
            throw new Error("Las contrasenas no coinciden.");
        }

        await updateProfile({
            username: state.user.username,
            email: state.user.email,
            password,
            current_password: currentPassword
        });
        renderActiveSection();
        setStatus("Contrasena actualizada correctamente.", "success");
        return;
    }

    if (state.activeSection === "delete") {
        if (!state.deleteFlow.confirmStep) {
            if (!state.deleteFlow.password.trim()) {
                throw new Error("Debes introducir tu contrasena para continuar.");
            }

            state.deleteFlow.confirmStep = true;
            renderActiveSection();
            setStatus("Escribe ELIMINAR MI CUENTA para confirmar.", "error");
            return;
        }

        const confirmInput = document.getElementById("deleteConfirmInput");

        if (!confirmInput || confirmInput.value.trim() !== "ELIMINAR MI CUENTA") {
            throw new Error("La confirmacion final no coincide.");
        }

        await deleteAccount({
            current_password: state.deleteFlow.password,
            confirm_text: confirmInput.value.trim()
        });

        localStorage.clear();
        window.location.href = publicUrl("index.html");
    }
}

settingsNav.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-section]");

    if (!button) {
        return;
    }

    if (state.activeSection === "avatar" && button.dataset.section !== "avatar") {
        resetAvatarDraft();
    }

    if (state.activeSection === "email" && button.dataset.section !== "email") {
        await cancelEmailChangeFlow({ goToAccount: false, silent: true });
    }

    if (state.activeSection === "delete" && button.dataset.section !== "delete") {
        state.deleteFlow.confirmStep = false;
        state.deleteFlow.password = "";
    }

    state.activeSection = button.dataset.section;
    renderActiveSection();
});

cancelButton.addEventListener("click", async () => {
    const sections = getSections();
    await sections[state.activeSection].onCancel();
});

settingsForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const sections = getSections();

    if (!sections[state.activeSection].showActions) {
        return;
    }

    setLoading(true);

    try {
        await handleSectionSubmit();
    } catch (error) {
        setStatus(error.message, "error");
    } finally {
        setLoading(false);
    }
});

async function loadSettings() {
    const data = await requestJson(apiUrl("/users/settings-summary"), {
        headers: { Authorization: "Bearer " + token }
    });

    state.user = data.user;
    state.postsCount = Number(data.posts_count || 0);
    state.followersCount = Number(data.followers || 0);

    updateSummary();
    renderActiveSection();
}

loadSettings().catch((error) => {
    setStatus(error.message, "error");
});
