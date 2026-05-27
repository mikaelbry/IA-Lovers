/**
 * Utilidades compartidas para feeds y tarjetas de publicaciones.
 *
 * Centraliza paginación, renderizado de posts, acciones de me gusta,
 * eliminación, enlaces de perfil, navegación a etiquetas y scroll infinito.
 */
const postsPathname = window.location.pathname;
const postsWebIndex = postsPathname.indexOf("/web/");
const postsPublicIndex = postsPathname.indexOf("/public/");
const postsApiIndex = postsPathname.indexOf("/backend/");

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
window.API ??= `${window.APP_BASE}/backend`;
window.WEB_BASE ??= (postsWebIndex >= 0 || postsPublicIndex >= 0) ? `${window.APP_BASE}/web` : window.APP_BASE;
window.apiUrl ??= (path = "") => `${window.API}${path.startsWith("/") ? path : `/${path}`}`;
window.webUrl ??= (path = "") => `${window.WEB_BASE}${path.startsWith("/") ? path : `/${path}`}`;
window.publicUrl ??= window.webUrl;
window.token ??= localStorage.getItem("token");
window.user = JSON.parse(localStorage.getItem("user"));

async function postsRequestJson(url, options = {}) {
    return await window.requestApiJson(url, options);
}

// Estado compartido de paginación para la página que usa este módulo.
let cursor = null;
let cursorLikes = null;
let loading = false;
let finished = false;
let observer = null;

/** Formatea una fecha de publicación para mostrarla en tarjetas. */
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

/** Escapa contenido procedente de la API antes de componer HTML. */
function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    }[char]));
}

/** Decide si el enlace del autor apunta al perfil propio o a un perfil público. */
function getPostProfileUrl(post) {
    if (window.user && window.user.username === post.username) {
        return window.publicUrl("profile.html");
    }

    return `${window.publicUrl("user.html")}?username=${encodeURIComponent(post.username ?? "")}`;
}

/** Obtiene una inicial segura para avatares sin imagen. */
function getInitial(value) {
    return escapeHtml(String(value || "I").trim().charAt(0).toUpperCase() || "I");
}

/** Renderiza avatar remoto o inicial para cabeceras de publicación. */
function renderUserAvatar(user, className) {
    const username = user?.username ?? "";

    if (user?.avatar_url) {
        return `<img src="${escapeHtml(user.avatar_url)}" alt="Avatar de ${escapeHtml(username)}" class="${className}">`;
    }

    return `<span class="${className} avatar-initial" aria-label="Avatar de ${escapeHtml(username)}">${getInitial(username)}</span>`;
}

/** Devuelve el SVG doble usado para estado normal y activo del botón de like. */
function renderLikeIcon() {
    return `
        <svg class="post-action-icon like-icon-outline" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"></path>
        </svg>
        <svg class="post-action-icon like-icon-filled" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="m12 21.35-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
        </svg>
    `;
}

/** Devuelve el SVG del contador de comentarios. */
function renderCommentIcon() {
    return `
        <svg class="post-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"></path>
        </svg>
    `;
}

/** Reemplaza todo el contenido de un feed por una lista de publicaciones. */
function renderPosts(posts, containerId = "posts") {
    const container = document.getElementById(containerId);
    container.innerHTML = "";
    appendPosts(posts, containerId);
}

/** Añade nuevas publicaciones al contenedor respetando el estado de paginación. */
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
        const profileUrl = getPostProfileUrl(post);

        const tagsHTML = post.tags
            ? post.tags.split(",").map(t => `
                <span class="tag" onclick="goToTag('${encodeURIComponent(t.trim())}', event)">
                    #${escapeHtml(t.trim())}
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
                                ? `<div class="menu-item delete" onclick="openDeletePostDialog(${post.id}, event)">Eliminar</div>`
                                : ``
                        }
                    </div>
                </div>

                <div class="post-header">
                    <a href="${profileUrl}" class="post-author-link">
                        ${renderUserAvatar(post, "post-avatar")}
                        <span>${escapeHtml(post.username ?? "")}</span>
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
                    ${escapeHtml(post.title ?? "")}
                </div>

                <div class="post-tags">
                    ${tagsHTML}
                </div>

                <div class="post-actions">
                    <button
                        type="button"
                        class="like-btn ${post.liked_by_user == 1 ? "liked" : ""}"
                        onclick="toggleLike(event, ${post.id}, this)">
                        ${renderLikeIcon()} <span>${post.likes_count ?? 0}</span>
                    </button>

                    <button type="button" class="comment-count" onclick="goToPost(${post.id})">
                        ${renderCommentIcon()} <span>${post.comments_count ?? 0}</span>
                    </button>
                </div>
            </div>
        `;
    });
}

/** Inicializa el sentinel que dispara la carga de más publicaciones. */
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

/** Solicita la siguiente página del feed y actualiza cursores. */
async function loadMore(fetchUrlBuilder, containerId) {
    if (loading || finished) {
        return;
    }

    loading = true;

    try {
        const url = fetchUrlBuilder(cursor);
        const headers = token ? { Authorization: "Bearer " + token } : {};

        const data = await postsRequestJson(url, { headers });

        appendPosts(data.posts, containerId);

        cursor = data.next_cursor;
        cursorLikes = data.next_cursor_likes ?? null;

        if (!cursor) {
            finished = true;
            if (observer) {
                observer.disconnect();
            }
        }
    } catch (error) {
        if (!error.authRedirected) {
            console.error(error);
        }
    } finally {
        loading = false;
    }
}

/** Reinicia cursores y vuelve a cargar el feed desde la primera página. */
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

/** Alterna un like de forma optimista y revierte si la API falla. */
function toggleLike(event, id, btn) {
    event.preventDefault();
    event.stopPropagation();

    if (!token) {
        window.location.href = "login.html";
        return;
    }

    const span = btn.querySelector("span");
    const previousLiked = btn.classList.contains("liked");
    const previousCount = parseInt(span.textContent, 10) || 0;
    const nextLiked = !previousLiked;
    const nextCount = Math.max(0, previousCount + (nextLiked ? 1 : -1));

    btn.classList.toggle("liked", nextLiked);
    span.textContent = nextCount;

    postsRequestJson(apiUrl("/posts/toggle-like"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify({ post_id: id })
    })
        .then(data => {
            if (typeof data.liked === "undefined") {
                throw new Error(data.error || "Error al dar like");
            }

            if (data.liked !== nextLiked) {
                btn.classList.toggle("liked", data.liked);
                span.textContent = Math.max(0, previousCount + (data.liked ? 1 : -1));
            }
        })
        .catch(error => {
            btn.classList.toggle("liked", previousLiked);
            span.textContent = previousCount;

            if (error.authRedirected) {
                return;
            }
            alert(error.message);
        });
}

/** Abre o cierra el menú contextual de una publicación. */
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

/** Copia el enlace público del post al portapapeles. */
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

/** Muestra el diálogo de confirmación para borrar una publicación propia. */
function openDeletePostDialog(id, event) {
    event?.preventDefault();
    event?.stopPropagation();
    document.querySelectorAll(".menu-dropdown")
        .forEach(menu => menu.classList.remove("active"));

    const existingDialog = document.getElementById("deletePostDialog");
    if (existingDialog) {
        existingDialog.remove();
    }

    const dialog = document.createElement("div");
    dialog.id = "deletePostDialog";
    dialog.className = "post-delete-dialog-backdrop";
    dialog.innerHTML = `
        <div class="post-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="deletePostTitle">
            <h3 id="deletePostTitle">Eliminar publicacion</h3>
            <p>Estas seguro de que deseas borrar esta publicacion? Esta accion es irreversible.</p>
            <div class="post-delete-dialog-actions">
                <button type="button" class="post-delete-cancel" onclick="closeDeletePostDialog()">Cancelar</button>
                <button type="button" class="post-delete-confirm" onclick="deletePost(${id})">Eliminar</button>
            </div>
        </div>
    `;

    dialog.addEventListener("click", event => {
        if (event.target === dialog) {
            closeDeletePostDialog();
        }
    });

    document.body.appendChild(dialog);
}

/** Cierra el diálogo de borrado de publicación si está presente. */
function closeDeletePostDialog() {
    document.getElementById("deletePostDialog")?.remove();
}

/** Elimina una publicación en backend y la retira del DOM. */
function deletePost(id) {
    postsRequestJson(apiUrl("/posts/delete"), {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: "Bearer " + token
        },
        body: JSON.stringify({ post_id: id })
    })
        .then(data => {
            if (data.success) {
                closeDeletePostDialog();
                const postEl = document.getElementById("post-" + id);
                if (postEl) {
                    postEl.remove();
                }
            } else {
                alert(data.error || "Error al eliminar");
            }
        })
        .catch(error => {
            if (error.authRedirected) {
                return;
            }
            alert(error.message);
        });
}

/** Navega al detalle público de una publicación. */
function goToPost(id) {
    window.location.href = "post.html?id=" + id;
}

/** Navega a Explorar filtrando por una etiqueta concreta. */
function goToTag(tag, event) {
    event.stopPropagation();
    window.location.href = `${window.publicUrl("explore.html")}?q=${tag}`;
}
