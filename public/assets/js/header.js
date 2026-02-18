document.addEventListener("DOMContentLoaded", () => {

    const nav = document.getElementById("nav-auth");
    const user = JSON.parse(localStorage.getItem("user"));

    let html = `
        <a href="index.html">Inicio</a>
        <a href="explorar.html">Explorar</a>
        <a href="cart.html">Carrito</a>
    `;

    if (user) {
        html += `
            <a href="following.html">Siguiendo</a>
            <a href="notifications.html">🔔</a>
            <a href="profile.html">${user.username}</a>
            <a href="#" id="logout">Salir</a>
        `;
    } else {
        const current = window.location.pathname.split("/").pop();
        html += `
            <a href="login.html?redirect=${current}">Login</a>
            <a href="register.html?redirect=${current}" class="btn-primary">Registro</a>
        `;
    }

    nav.innerHTML = html;

    if (user) {
        document.getElementById("logout").addEventListener("click", () => {
            localStorage.clear();
            window.location.href = "index.html";
        });
    }
});
