window.APP_BASE = window.location.pathname.includes("/IA-Lovers/") ? "/IA-Lovers" : "";
window.API = `${window.APP_BASE}/api`;
window.PUBLIC_BASE = `${window.APP_BASE}/public`;
window.apiUrl = (path = "") => `${window.API}${path.startsWith("/") ? path : `/${path}`}`;
window.publicUrl = (path = "") => `${window.PUBLIC_BASE}${path.startsWith("/") ? path : `/${path}`}`;
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
        <a href="index.html" data-page="index">Inicio</a>
        <a href="explorar.html" data-page="explorar">Explorar</a>
    `;

    if (user) {
        centerHTML += `
            <a href="following.html" data-page="following">Siguiendo</a>
        `;
    }

    center.innerHTML = centerHTML;

    // ===== DERECHA =====
    let rightHTML = "";

    if (user) {
        rightHTML = `
            <a href="create.html" data-page="create">Publicar</a>
            <a href="notifications.html" class="icon-btn">
                <svg class="icon" viewBox="0 0 24 24" fill="none">
                    <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" 
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0" 
                        stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="profile.html" data-page="profile">${user.username}</a>
            <a href="#" id="logout">Salir</a>
        `;
    } else {
        const current = window.location.pathname.split("/").pop();

        rightHTML = `
            <a href="login.html?redirect=${current}">Login</a>
            <a href="register.html?redirect=${current}" class="btn-primary">Registro</a>
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
            window.location.href = "index.html";
        });
    }

});
