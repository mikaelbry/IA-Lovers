const BASE_PATH = "/IA-Lovers/public/";

document.addEventListener("DOMContentLoaded", () => {

    const user = JSON.parse(localStorage.getItem("user"));
    const nav = document.getElementById("nav-auth");

    let links = `
        <a href="${BASE_PATH}index.html">Inicio</a>
        <a href="${BASE_PATH}explorar.html">Explorar</a>
    `;

    if (user) {
        links += `
            <a href="${BASE_PATH}profile.html">Perfil</a>
            <a href="#" id="logout">Cerrar sesión</a>
        `;
    } else {
        links += `
            <a href="${BASE_PATH}login.html">Login</a>
            <a href="${BASE_PATH}register.html">Registro</a>
        `;
    }

    nav.innerHTML = links;

    if (user) {
        document.getElementById("logout").addEventListener("click", () => {
            localStorage.clear();
            window.location.href = BASE_PATH + "index.html";
        });
    }
});
