
function getRedirectUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get("redirect") || "index.html";
}

async function register(event) {
    event.preventDefault();

    const data = {
        username: document.getElementById("username").value,
        email: document.getElementById("email").value,
        password: document.getElementById("password").value
    };

    const res = await fetch("../api/index.php/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });

    const json = await res.json();

    if (res.ok) {
        // login automático tras registro
        await autoLogin(data.email, data.password);
    } else {
        alert(json.error || "Error");
    }
}

async function login(event) {
    event.preventDefault();

    const data = {
        email: document.getElementById("email").value,
        password: document.getElementById("password").value
    };

    await autoLogin(data.email, data.password);
}

async function autoLogin(email, password) {

    const res = await fetch("../api/index.php/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
    });

    const json = await res.json();

    if (!res.ok) {
        alert(json.error || "Error");
        return;
    }

    localStorage.setItem("token", json.token);
    localStorage.setItem("user", JSON.stringify(json.user));

    window.location.href = getRedirectUrl();
}
