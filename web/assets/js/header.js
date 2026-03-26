const headerPathname = window.location.pathname;
const headerWebIndex = headerPathname.indexOf("/web/");
const headerPublicIndex = headerPathname.indexOf("/public/");
const headerApiIndex = headerPathname.indexOf("/api/");

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

document.addEventListener("DOMContentLoaded", () => {

    const navbar = document.querySelector(".navbar");
    const user = JSON.parse(localStorage.getItem("user"));

    navbar.innerHTML = `
        <div class="nav-left">
            <div class="logo">IA-<span class="highlight">Lovers</span></div>
        </div>

        <div class="nav-center" id="nav-center"></div>

        <div class="nav-right" id="nav-right"></div>
    `;

    const center = document.getElementById("nav-center");
    const right = document.getElementById("nav-right");

    // ===== CENTRO =====
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

    // ===== DERECHA =====
    let rightHTML = "";

    if (user) {
        rightHTML = `
            <a href="${webUrl("create.html")}" data-page="create">Publicar</a>
            <a href="notifications.html" class="icon-btn">
                <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" 
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0" 
                        stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="${webUrl("profile.html")}" data-page="profile">${user.username}</a>
            <a href="#" id="logout">Salir</a>
        `;
    } else {
        const current = window.location.pathname.split("/").pop();

        rightHTML = `
            <a href="${webUrl("login.html")}?redirect=${current}">Login</a>
            <a href="${webUrl("register.html")}?redirect=${current}" class="btn-primary">Registro</a>
        `;
    }

    right.innerHTML = rightHTML;

    // ===== ACTIVE LINK =====
    const currentPage = window.location.pathname.split("/").pop();

    const map = {
        "index.html": "index",
        "explorar.html": "explorar",
        "following.html": "following",
        "create.html": "create",
        "profile.html": "profile"
    };

    const currentKey = map[currentPage];

    if (currentKey) {
        const activeLink = document.querySelector(`[data-page="${currentKey}"]`);
        if (activeLink) activeLink.classList.add("active");
    }

    // ===== LOGOUT =====
    if (user) {
        document.getElementById("logout").addEventListener("click", () => {
            localStorage.clear();
            window.location.href = webUrl("index.html");
        });
    }

});
