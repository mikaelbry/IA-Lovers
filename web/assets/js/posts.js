const postsPathname = window.location.pathname;
const postsWebIndex = postsPathname.indexOf("/web/");
const postsPublicIndex = postsPathname.indexOf("/public/");
const postsApiIndex = postsPathname.indexOf("/api/");

window.APP_BASE ??= (() => {
    const path = window.location.pathname;

    if (postsWebIndex > 0) {
        return path.slice(0, postsWebIndex);
    }

    if (postsPublicIndex > 0) {
        return path.slice(0, postsPublicIndex);
    }

    if (postsApiIndex > 0) {
        return path.slice(0, postsApiIndex);
    }

    return "";
})();
window.API ??= `${window.APP_BASE}/api`;
window.WEB_BASE ??= (postsWebIndex >= 0 || postsPublicIndex >= 0) ? `${window.APP_BASE}/web` : window.APP_BASE;
window.apiUrl ??= (path = "") => `${window.API}${path.startsWith("/") ? path : `/${path}`}`;
window.webUrl ??= (path = "") => `${window.WEB_BASE}${path.startsWith("/") ? path : `/${path}`}`;
window.publicUrl ??= window.webUrl;
window.token ??= localStorage.getItem("token");
window.user = JSON.parse(localStorage.getItem("user"));

let cursor = null;
let cursorLikes = null;
let loading = false;
let finished = false;
let observer = null;

function formatDate(dateString) {
    if (!dateString) {
        return "";
    }

    const date = new Date(dateString);

    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");

    return `${hours}:${minutes} - ${day}/${month}/${year}`;
}

function renderPosts(posts, containerId = "posts") {
    const container = document.getElementById(containerId);
    container.innerHTML = "";
    appendPosts(posts, containerId);
}

function appendPosts(posts, containerId = "posts") {
    const container = document.getElementById(containerId);

    if (!posts || posts.length === 0) {
        if (container.innerHTML === "") {
            container.innerHTML = "<p>No hay publicaciones.</p>";
        }
        finished = true;
        return;
    }

    posts.forEach(post => {
        const profileUrl = `${window.publicUrl("user.html")}?username=${encodeURIComponent(post.username ?? "")}`;

        const tagsHTML = post.tags
            ? post.tags.split(",").map(t => `
                <span class="tag" onclick="goToTag('${encodeURIComponent(t.trim())}', event)">
                    #${t.trim()}
                </span>
            `).join(" ")
            : "";

        container.innerHTML += `
            <div class="post" id="post-${post.id}">
                <div class="post-menu">
                    <button type="button" class="menu-btn" onclick="toggleMenu(event, ${post.id})">...</button>
                    <div class="menu-dropdown" id="menu-${post.id}">
                        <div class="menu-item" id="copy-${post.id}" onclick="copyPostLink(${post.id}, event)">
                            Compartir
                        </div>
                        ${
                            window.user && window.user.username === post.username
                                ? `<div class="menu-item delete" onclick="deletePost(${post.id})">Eliminar</div>`
                                : ``
                        }
                    </div>
                </div>

                <div class="post-header">
                    <a href="${profileUrl}">
                        ${post.username ?? ""}
                    </a>
                </div>

                <div class="post-date">
                    ${formatDate(post.created_at)}
                </div>

                <img src="${post.file_path}"
                     loading="lazy"
                     class="post-image"
                     onclick="goToPost(${post.id})">

                <div class="post-title">
                    ${post.title ?? ""}
                </div>

                <div class="post-tags">
                    ${tagsHTML}
                </div>

                <div class="post-actions">
                    <button
                        type="button"
                        class="like-btn ${post.liked_by_user == 1 ? "liked" : ""}"
                        onclick="toggleLike(event, ${post.id}, this)">
                        ❤️ <span>${post.likes_count ?? 0}</span>
                    </button>

                    <button type="button" class="comment-count" onclick="goToPost(${post.id})">
                        💬 ${post.comments_count ?? 0}
                    </button>
                </div>
            </div>
        `;
    });
}

function initInfiniteScroll(fetchUrlBuilder, containerId = "posts") {
    const sentinel = document.createElement("div");
    sentinel.id = "scroll-sentinel";

    document.getElementById(containerId).after(sentinel);

    observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) {
            loadMore(fetchUrlBuilder, containerId);
        }
    }, {
        rootMargin: "300px"
    });

    observer.observe(sentinel);

    loadMore(fetchUrlBuilder, containerId);
}

async function loadMore(fetchUrlBuilder, containerId) {
    if (loading || finished) {
        return;
    }

    loading = true;

    const url = fetchUrlBuilder(cursor);
    const headers = token ? { Authorization: "Bearer " + token } : {};

    const res = await fetch(url, { headers });
    const data = await res.json();

    appendPosts(data.posts, containerId);

    cursor = data.next_cursor;
    cursorLikes = data.next_cursor_likes ?? null;

    if (!cursor) {
        finished = true;
        if (observer) {
            observer.disconnect();
        }
    }

    loading = false;
}

function resetAndLoad(fetchUrlBuilder, containerId = "posts") {
    cursor = null;
    cursorLikes = null;
    loading = false;
    finished = false;

    const container = document.getElementById(containerId);
    container.innerHTML = "";

    if (observer) {
        observer.disconnect();
    }

    initInfiniteScroll(fetchUrlBuilder, containerId);

    window.scrollTo({ top: 0, behavior: "smooth" });
}

function toggleLike(event, id, btn) {
    event.preventDefault();
    event.stopPropagation();

    if (!token) {
        window.location.href = "login.html";
        return;
    }

    fetch(apiUrl("/posts/toggle-like"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify({ post_id: id })
    })
        .then(r => r.json())
        .then(data => {
            if (typeof data.liked === "undefined") {
                throw new Error(data.error || "Error al dar like");
            }

            const span = btn.querySelector("span");
            const count = parseInt(span.textContent, 10);

            if (data.liked) {
                btn.classList.add("liked");
                span.textContent = count + 1;
            } else {
                btn.classList.remove("liked");
                span.textContent = count - 1;
            }
        })
        .catch(error => {
            alert(error.message);
        });
}

function toggleMenu(event, id) {
    event.preventDefault();
    event.stopPropagation();

    document.querySelectorAll(".menu-dropdown").forEach(menu => {
        if (menu.id !== "menu-" + id) {
            menu.classList.remove("active");
        }
    });

    document.getElementById("menu-" + id).classList.toggle("active");
}

document.addEventListener("click", () => {
    document.querySelectorAll(".menu-dropdown")
        .forEach(menu => menu.classList.remove("active"));
});

function copyPostLink(id, event) {
    event.preventDefault();
    event.stopPropagation();

    const url = `${window.location.origin}${window.publicUrl("post.html")}?id=${id}`;

    navigator.clipboard.writeText(url);

    const el = document.getElementById(`copy-${id}`);
    el.textContent = "Copiado";
    el.style.color = "green";

    setTimeout(() => {
        el.textContent = "Compartir";
        el.style.color = "";
    }, 2000);
}

function deletePost(id) {
    const confirmDelete = confirm("Estas seguro de que deseas borrar esta publicacion?\nEsta accion es irreversible.");

    if (!confirmDelete) {
        return;
    }

    fetch(apiUrl("/posts/delete"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify({ post_id: id })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const postEl = document.getElementById("post-" + id);
                if (postEl) {
                    postEl.remove();
                }
            } else {
                alert(data.error || "Error al eliminar");
            }
        });
}

function goToPost(id) {
    window.location.href = "post.html?id=" + id;
}

function goToTag(tag, event) {
    event.stopPropagation();
    window.location.href = `${window.publicUrl("explorar.html")}?tag=${tag}`;
}
