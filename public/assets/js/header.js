document.addEventListener("DOMContentLoaded", () => {

    const user = JSON.parse(localStorage.getItem("user"));

    const nav = document.getElementById("nav-auth");

    if (user) {
        nav.innerHTML = `
            <span>Hola, ${user.username}</span>
            <a href="#" id="logout">Cerrar sesión</a>
        `;

        document.getElementById("logout").addEventListener("click", () => {
            localStorage.removeItem("token");
            localStorage.removeItem("user");
            location.reload();
        });

    } else {
        nav.innerHTML = `
            <a href="login.html">Iniciar sesión</a>
            <a href="register.html" class="btn-primary">Registro</a>
        `;
    }

});
