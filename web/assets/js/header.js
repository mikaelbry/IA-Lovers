const headerPathname = window.location.pathname;
const headerWebIndex = headerPathname.indexOf("/web/");
const headerPublicIndex = headerPathname.indexOf("/public/");
const headerApiIndex = headerPathname.indexOf("/api/");
const AUTH_FLASH_KEY = "auth-flash";

window.APP_BASE = (() => {
    const path = window.location.pathname;

    if (headerWebIndex > 0) {
        return path.slice(0, headerWebIndex);
    }

    if (headerPublicIndex > 0) {
        return path.slice(0, headerPublicIndex);
    }

    if (headerApiIndex > 0) {
        return path.slice(0, headerApiIndex);
    }

    return "";
})();
window.API = `${window.APP_BASE}/api`;
window.WEB_BASE = headerWebIndex >= 0 || headerPublicIndex >= 0 ? `${window.APP_BASE}/web` : window.APP_BASE;
window.apiUrl = (path = "") => `${window.API}${path.startsWith("/") ? path : `/${path}`}`;
window.webUrl = (path = "") => `${window.WEB_BASE}${path.startsWith("/") ? path : `/${path}`}`;
window.publicUrl = window.webUrl;
window.token = localStorage.getItem("token");
window.user = JSON.parse(localStorage.getItem("user") || "null");

const authRedirectFallback = "index.html";
const blockedAuthRedirects = new Set(["login.html", "register.html", "forgot_password.html"]);

function sanitizeAuthRedirect(target) {
    if (!target || typeof target !== "string") {
        return authRedirectFallback;
    }

    const trimmed = target.trim();
    if (!trimmed) {
        return authRedirectFallback;
    }

    try {
        const base = `${window.location.origin}${window.WEB_BASE.endsWith("/") ? window.WEB_BASE : `${window.WEB_BASE}/`}`;
        const url = new URL(trimmed, base);

        if (url.origin !== window.location.origin) {
            return authRedirectFallback;
        }

        const page = url.pathname.split("/").pop() || authRedirectFallback;
        return blockedAuthRedirects.has(page) ? authRedirectFallback : `${page}${url.search}${url.hash}`;
    } catch (error) {
        const page = trimmed.split("?")[0].split("/").pop() || authRedirectFallback;
        return blockedAuthRedirects.has(page) ? authRedirectFallback : trimmed;
    }
}

function currentPageName() {
    return window.location.pathname.split("/").pop() || authRedirectFallback;
}

function currentRedirectTarget() {
    return sanitizeAuthRedirect(`${currentPageName()}${window.location.search}${window.location.hash}`);
}

function storeAuthFlash(message, type = "info") {
    if (!message) {
        sessionStorage.removeItem(AUTH_FLASH_KEY);
        return;
    }

    sessionStorage.setItem(AUTH_FLASH_KEY, JSON.stringify({ message, type }));
}

window.consumeAuthFlash = () => {
    const raw = sessionStorage.getItem(AUTH_FLASH_KEY);

    if (!raw) {
        return null;
    }

    sessionStorage.removeItem(AUTH_FLASH_KEY);

    try {
        return JSON.parse(raw);
    } catch (error) {
        return null;
    }
};

window.clearAuthSession = async ({
    redirectToLogin = false,
    flashMessage = "",
    flashType = "error",
    skipServerLogout = false,
} = {}) => {
    const storedToken = localStorage.getItem("token");

    if (storedToken && !skipServerLogout) {
        fetch(apiUrl("/logout"), {
            method: "POST",
            headers: {
                Authorization: "Bearer " + storedToken,
            },
            keepalive: true,
        }).catch(() => {});
    }

    localStorage.removeItem("token");
    localStorage.removeItem("user");
    window.token = null;
    window.user = null;

    if (flashMessage) {
        storeAuthFlash(flashMessage, flashType);
    }

    if (!redirectToLogin) {
        return;
    }

    if (blockedAuthRedirects.has(currentPageName())) {
        return;
    }

    const redirect = currentRedirectTarget();
    window.location.href = `${webUrl("login.html")}?redirect=${encodeURIComponent(redirect)}`;
};

window.performLogout = async () => {
    await window.clearAuthSession({
        redirectToLogin: false,
        flashMessage: "",
        skipServerLogout: false,
    });

    window.location.href = webUrl("index.html");
};

window.requestApiJson = async (url, options = {}) => {
    const response = await fetch(url, options);
    const contentType = response.headers.get("content-type") || "";
    const data = contentType.includes("application/json") ? await response.json() : {};

    if (response.status === 401 && localStorage.getItem("token")) {
        const message = data.error || "Tu sesion ha caducado. Inicia sesion de nuevo.";

        await window.clearAuthSession({
            redirectToLogin: true,
            flashMessage: message,
            flashType: "error",
            skipServerLogout: true,
        });

        const error = new Error(message);
        error.status = response.status;
        error.data = data;
        error.authRedirected = true;
        throw error;
    }

    if (!response.ok) {
        const error = new Error(data.error || "Error");
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
};

function escapeHeaderHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
    }[char]));
}

function relativeNotificationTime(dateString) {
    const date = new Date(dateString);
    const timestamp = date.getTime();

    if (!timestamp) {
        return "";
    }

    const seconds = Math.max(1, Math.floor((Date.now() - timestamp) / 1000));

    if (seconds < 60) {
        return "Ahora";
    }

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `${hours} h`;
    }

    const days = Math.floor(hours / 24);
    if (days < 7) {
        return `${days} d`;
    }

    return date.toLocaleDateString("es-ES", {
        day: "2-digit",
        month: "2-digit",
    });
}

function notificationText(notification) {
    const username = notification.from_username || "Alguien";

    const messages = {
        follow: `${username} ha empezado a seguirte.`,
        like: `${username} le ha dado me gusta a tu post.`,
        comment: `${username} ha comentado tu post.`,
        reply: `${username} ha respondido en un post.`,
    };

    return messages[notification.type] || `${username} tiene una novedad para ti.`;
}

function notificationPostHref(notification) {
    if (notification.post_id) {
        return webUrl(`post.html?id=${encodeURIComponent(notification.post_id)}`);
    }

    return null;
}

function notificationUserHref(notification) {
    if (notification.from_username) {
        return webUrl(`user.html?username=${encodeURIComponent(notification.from_username)}`);
    }

    return "#";
}

function renderNotificationAvatar(notification) {
    const username = notification.from_username || "I";

    if (notification.from_avatar_url) {
        return `<img src="${escapeHeaderHtml(notification.from_avatar_url)}" alt="" class="notification-avatar">`;
    }

    return `<span class="notification-avatar notification-avatar-initial">${escapeHeaderHtml(username.charAt(0).toUpperCase() || "I")}</span>`;
}

function renderNotificationPostThumb(notification) {
    const postHref = notificationPostHref(notification);

    if (!postHref || !notification.post_image_url) {
        return notification.post_id
            ? `<a class="notification-post-thumb notification-post-thumb-fallback" href="${postHref}" aria-label="Ver post"></a>`
            : "";
    }

    return `
        <a class="notification-post-thumb" href="${postHref}" aria-label="Ver post">
            <img src="${escapeHeaderHtml(notification.post_image_url)}" alt="">
        </a>
    `;
}

function setNotificationBadge(count) {
    const badge = document.getElementById("notificationsBadge");

    if (!badge) {
        return;
    }

    const unreadCount = Number(count || 0);
    badge.textContent = unreadCount > 9 ? "9+" : String(unreadCount);
    badge.classList.toggle("hidden", unreadCount <= 0);
}

function renderNotificationsList(notifications) {
    const list = document.getElementById("notificationsList");

    if (!list) {
        return;
    }

    if (!notifications.length) {
        list.innerHTML = `
            <div class="notification-empty">
                No tienes notificaciones nuevas.
            </div>
        `;
        return;
    }

    list.innerHTML = notifications.map((notification) => {
        const unreadClass = notification.is_read ? "" : " unread";
        const postClass = notification.post_id ? "" : " no-post";
        const userHref = notificationUserHref(notification);
        return `
            <article class="notification-item${unreadClass}${postClass}">
                <a class="notification-user-link" href="${userHref}" aria-label="Ver usuario">
                    ${renderNotificationAvatar(notification)}
                </a>
                <div class="notification-copy">
                    <strong>
                        <a href="${userHref}">${escapeHeaderHtml(notification.from_username || "Alguien")}</a>
                        <span>${escapeHeaderHtml(notificationText(notification).replace(notification.from_username || "Alguien", "").trim())}</span>
                    </strong>
                    ${notification.post_title ? `<small>${escapeHeaderHtml(notification.post_title)}</small>` : ""}
                    <em>${escapeHeaderHtml(relativeNotificationTime(notification.created_at))}</em>
                </div>
                ${renderNotificationPostThumb(notification)}
            </article>
        `;
    }).join("");
}

async function loadNotifications({ markRead = false } = {}) {
    const panel = document.getElementById("notificationsPanel");
    const list = document.getElementById("notificationsList");

    if (!panel || !list || !window.token) {
        return;
    }

    list.innerHTML = `<div class="notification-empty">Cargando...</div>`;

    try {
        const data = await window.requestApiJson(apiUrl("/notifications"), {
            headers: { Authorization: "Bearer " + window.token },
        });

        renderNotificationsList(data.notifications || []);
        setNotificationBadge(data.unread_count || 0);

        if (markRead && Number(data.unread_count || 0) > 0) {
            await window.requestApiJson(apiUrl("/notifications/read"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Authorization: "Bearer " + window.token,
                },
                body: JSON.stringify({}),
            });
            setNotificationBadge(0);
            panel.querySelectorAll(".notification-item.unread").forEach((item) => item.classList.remove("unread"));
        }
    } catch (error) {
        if (!error.authRedirected) {
            list.innerHTML = `<div class="notification-empty">No se pudieron cargar las notificaciones.</div>`;
        }
    }
}

async function loadUnreadNotificationCount() {
    if (!window.token) {
        return;
    }

    try {
        const data = await window.requestApiJson(apiUrl("/notifications/unread-count"), {
            headers: { Authorization: "Bearer " + window.token },
        });
        setNotificationBadge(data.unread_count || 0);
    } catch (error) {
        if (!error.authRedirected) {
            setNotificationBadge(0);
        }
    }
}

function bindNotificationsDropdown() {
    const button = document.getElementById("notificationsButton");
    const panel = document.getElementById("notificationsPanel");

    if (!button || !panel) {
        return;
    }

    button.addEventListener("click", async (event) => {
        event.preventDefault();
        event.stopPropagation();

        const isOpen = panel.classList.toggle("open");
        button.setAttribute("aria-expanded", isOpen ? "true" : "false");

        if (isOpen) {
            await loadNotifications({ markRead: true });
        }
    });

    document.addEventListener("click", (event) => {
        if (!panel.classList.contains("open")) {
            return;
        }

        if (panel.contains(event.target) || button.contains(event.target)) {
            return;
        }

        panel.classList.remove("open");
        button.setAttribute("aria-expanded", "false");
    });
}

async function validateStoredSession() {
    const storedToken = localStorage.getItem("token");

    if (!storedToken) {
        if (localStorage.getItem("user")) {
            localStorage.removeItem("user");
        }

        window.token = null;
        window.user = null;
        return;
    }

    window.token = storedToken;

    try {
        const session = await window.requestApiJson(apiUrl("/session"), {
            headers: {
                Authorization: "Bearer " + storedToken,
            },
        });

        if (session?.user) {
            window.user = session.user;
            localStorage.setItem("user", JSON.stringify(session.user));
        }
    } catch (error) {
        if (error.status !== 401) {
            console.error(error);
        }
    }
}

function renderNavbar() {
    const navbar = document.querySelector(".navbar");

    if (!navbar) {
        return;
    }

    const user = window.user;

    navbar.innerHTML = `
        <div class="nav-left">
            <div class="logo">IA-<span class="highlight">Lovers</span></div>
        </div>

        <div class="nav-center" id="nav-center"></div>

        <div class="nav-right" id="nav-right"></div>
    `;

    const center = document.getElementById("nav-center");
    const right = document.getElementById("nav-right");

    let centerHTML = `
        <a href="${webUrl("index.html")}" data-page="index">Inicio</a>
        <a href="${webUrl("explorar.html")}" data-page="explorar">Explorar</a>
    `;

    if (user) {
        centerHTML += `
            <a href="${webUrl("following.html")}" data-page="following">Siguiendo</a>
        `;
    }

    center.innerHTML = centerHTML;

    let rightHTML = "";

    if (user) {
        rightHTML = `
            <a href="${webUrl("create.html")}" data-page="create">Publicar</a>
            <div class="notifications-menu">
            <button type="button" id="notificationsButton" class="icon-btn notifications-button" aria-label="Notificaciones" aria-expanded="false" aria-haspopup="true">
                <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span id="notificationsBadge" class="notifications-badge hidden">0</span>
            </button>
            <section id="notificationsPanel" class="notifications-panel" aria-label="Notificaciones">
                <div class="notifications-panel-header">
                    <strong>Notificaciones</strong>
                </div>
                <div id="notificationsList" class="notifications-list">
                    <div class="notification-empty">Cargando...</div>
                </div>
            </section>
            </div>
            <div class="nav-user-menu">
                <a href="${webUrl("profile.html")}" data-page="profile" class="nav-user-trigger">
                    <span class="nav-user-label">${user.username}</span>
                    <svg class="nav-user-caret" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <div class="nav-user-dropdown">
                    <a href="${webUrl("profile.html")}" class="nav-user-option">Perfil</a>
                    <a href="${webUrl("profile_settings.html")}" class="nav-user-option">Ajustes</a>
                </div>
            </div>
            <a href="#" id="logout">Salir</a>
        `;
    } else {
        const redirect = currentRedirectTarget();

        rightHTML = `
            <a href="${webUrl("login.html")}?redirect=${encodeURIComponent(redirect)}">Login</a>
            <a href="${webUrl("register.html")}?redirect=${encodeURIComponent(redirect)}" class="btn-primary">Registro</a>
        `;
    }

    right.innerHTML = rightHTML;

    const map = {
        "index.html": "index",
        "explorar.html": "explorar",
        "following.html": "following",
        "create.html": "create",
        "profile.html": "profile",
        "profile_settings.html": "profile",
    };

    const currentKey = map[currentPageName()];

    if (currentKey) {
        const activeLink = document.querySelector(`[data-page="${currentKey}"]`);
        if (activeLink) {
            activeLink.classList.add("active");
        }
    }

    if (user) {
        const logoutLink = document.getElementById("logout");
        if (logoutLink) {
            logoutLink.addEventListener("click", (event) => {
                event.preventDefault();
                window.performLogout();
            });
        }

        bindNotificationsDropdown();
        loadUnreadNotificationCount();
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    await validateStoredSession();
    renderNavbar();
});
