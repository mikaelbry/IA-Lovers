const API_BASE = "/IA-Lovers/api/index.php";


async function register(event) {
    event.preventDefault();

    const data = {
        username: document.getElementById("username").value,
        email: document.getElementById("email").value,
        password: document.getElementById("password").value
    };

    try {
        const res = await fetch(API_BASE + "/register", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        alert(json.message || json.error);

    } catch (error) {
        console.error(error);
        alert("Error de conexión con la API");
    }
}

async function login(event) {
    event.preventDefault();

    const data = {
        email: document.getElementById("email").value,
        password: document.getElementById("password").value
    };

    try {
        const res = await fetch(API_BASE + "/login", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        if (json.token) {
            localStorage.setItem("token", json.token);
            localStorage.setItem("user", JSON.stringify(json.user));
            window.location.href = "index.html";
        } else {
            alert(json.error);
        }

    } catch (error) {
        console.error(error);
        alert("Error de conexión con la API");
    }
}
