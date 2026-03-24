function getRedirectUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get("redirect") || "index.html";
}

async function register(event) {
    event.preventDefault();

    const btn = event.target.querySelector("button");
    btn.disabled = true;

    const data = {
        username: document.getElementById("username").value.trim(),
        email: document.getElementById("email").value.trim(),
        password: document.getElementById("password").value
    };

    try {
        const res = await fetch("../api/register", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        });

        const json = await res.json();

        if (!res.ok) {
            throw new Error(json.error || "Error");
        }

        await autoLogin(data.email, data.password);

    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
}

async function login(event) {
    event.preventDefault();

    const btn = event.target.querySelector("button");
    btn.disabled = true;

    const data = {
        email: document.getElementById("email").value.trim(),
        password: document.getElementById("password").value
    };

    try {
        await autoLogin(data.email, data.password);
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
    }
}

async function autoLogin(email, password) {

    const res = await fetch("../api/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
    });

    const json = await res.json();

    if (!res.ok) {
        throw new Error(json.error || "Error");
    }

    localStorage.setItem("token", json.token);
    localStorage.setItem("user", JSON.stringify(json.user));

    window.location.href = getRedirectUrl();
}
