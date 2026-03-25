window.APP_BASE ??= window.location.pathname.includes("/IA-Lovers/") ? "/IA-Lovers" : "";
window.API ??= `${window.APP_BASE}/api`;
window.PUBLIC_BASE ??= `${window.APP_BASE}/public`;
window.apiUrl ??= (path = "") => `${window.API}${path.startsWith("/") ? path : `/${path}`}`;
window.publicUrl ??= (path = "") => `${window.PUBLIC_BASE}${path.startsWith("/") ? path : `/${path}`}`;
window.token ??= localStorage.getItem("token");
window.user = JSON.parse(localStorage.getItem("user"));

let cursor = null;
let cursorLikes = null;
let loading = false;
let finished = false;
let observer = null;

/* ===== FORMAT DATE ===== */
function formatDate(dateString){
    if(!dateString) return "";

    const date = new Date(dateString);

    const day = String(date.getDate()).padStart(2,'0');
    const month = String(date.getMonth()+1).padStart(2,'0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2,'0');
    const minutes = String(date.getMinutes()).padStart(2,'0');

    return `${hours}:${minutes} - ${day}/${month}/${year}`;
}

/* ===== RENDER ===== */
function renderPosts(posts,containerId="posts"){
    const container = document.getElementById(containerId);
    container.innerHTML="";
    appendPosts(posts,containerId);
}

function appendPosts(posts,containerId="posts"){
    const container = document.getElementById(containerId);

    if(!posts || posts.length===0){
        if(container.innerHTML===""){
            container.innerHTML="<p>No hay publicaciones.</p>";
        }
        finished=true;
        return;
    }

    posts.forEach(post=>{

        const profileUrl = `${window.publicUrl("user.html")}?username=${encodeURIComponent(post.username ?? "")}`;

        const tagsHTML = post.tags
        ? post.tags.split(',').map(t=>`
            <span class="tag" onclick="goToTag('${encodeURIComponent(t.trim())}', event)">
                #${t.trim()}
            </span>
        `).join(' ')
        : '';

        container.innerHTML+=`
        <div class="post" id="post-${post.id}">

            <div class="post-menu">
                <button class="menu-btn" onclick="toggleMenu(event, ${post.id})">⋯</button>
                <div class="menu-dropdown" id="menu-${post.id}">
                    <div class="menu-item" id="copy-${post.id}" onclick="copyPostLink(${post.id}, event)">
                        📋 Compartir
                    </div>
                    ${
                        (window.user && window.user.username === post.username)
                        ? `<div class="menu-item delete" onclick="deletePost(${post.id})">🗑️ Eliminar</div>`
                        : ``
                    }
                </div>
            </div>

            <div class="post-header">
                <a href="${profileUrl}">
                    ${post.username ?? ''}
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
                ${post.title ?? ''}
            </div>

            <div class="post-tags">
                ${tagsHTML}
            </div>

            <div class="post-actions">

                <button 
                    class="like-btn ${post.liked_by_user==1?'liked':''}"
                    onclick="toggleLike(event,${post.id},this)">
                    ❤️ <span>${post.likes_count ?? 0}</span>
                </button>

                <button class="comment-count"
                        onclick="goToPost(${post.id})">
                    💬 ${post.comments_count ?? 0}
                </button>

            </div>

        </div>
        `;
    });
}

/* ===== INFINITE SCROLL ===== */
function initInfiniteScroll(fetchUrlBuilder,containerId="posts"){

    const sentinel = document.createElement("div");
    sentinel.id="scroll-sentinel";

    document.getElementById(containerId).after(sentinel);

    observer = new IntersectionObserver(entries=>{
        if(entries[0].isIntersecting){
            loadMore(fetchUrlBuilder,containerId);
        }
    },{
        rootMargin:"300px"
    });

    observer.observe(sentinel);

    loadMore(fetchUrlBuilder,containerId);
}

async function loadMore(fetchUrlBuilder,containerId){

    if(loading || finished) return;

    loading=true;

    let url = fetchUrlBuilder(cursor);

    const headers = token ? {"Authorization":"Bearer "+token}:{};

    const res = await fetch(url,{headers});
    const data = await res.json();

    appendPosts(data.posts,containerId);

    cursor = data.next_cursor;
    cursorLikes = data.next_cursor_likes ?? null;

    if(!cursor){
        finished=true;
        if(observer) observer.disconnect();
    }

    loading=false;
}

function resetAndLoad(fetchUrlBuilder, containerId="posts"){

    cursor = null;
    cursorLikes = null;
    loading = false;
    finished = false;

    const container = document.getElementById(containerId);
    container.innerHTML = "";

    if(observer){
        observer.disconnect();
    }

    initInfiniteScroll(fetchUrlBuilder, containerId);

    window.scrollTo({ top: 0, behavior: "smooth" });
}

/* ===== LIKE ===== */
function toggleLike(event,id,btn){
    event.stopPropagation();

    if(!token){
        window.location.href="login.html";
        return;
    }

    fetch(apiUrl("/posts/toggle-like"),{
        method:"POST",
        headers:{
            "Content-Type":"application/json",
            "Authorization":"Bearer "+token
        },
        body:JSON.stringify({post_id:id})
    })
    .then(r=>r.json())
    .then(data=>{
        const span = btn.querySelector("span");
        let count = parseInt(span.textContent);

        if(data.liked){
            btn.classList.add("liked");
            span.textContent = count+1;
        }else{
            btn.classList.remove("liked");
            span.textContent = count-1;
        }
    });
}

/* ===== MENU ===== */
function toggleMenu(event, id){
    event.stopPropagation();

    document.querySelectorAll(".menu-dropdown").forEach(m=>{
        if(m.id !== "menu-"+id) m.classList.remove("active");
    });

    document.getElementById("menu-"+id).classList.toggle("active");
}

document.addEventListener("click", ()=>{
    document.querySelectorAll(".menu-dropdown")
        .forEach(m=>m.classList.remove("active"));
});

/* ===== COPIAR LINK ===== */
function copyPostLink(id){
    event.stopPropagation();

    const url = `${window.location.origin}${window.publicUrl("post.html")}?id=${id}`;

    navigator.clipboard.writeText(url);

    const el = document.getElementById(`copy-${id}`);
    el.textContent = "✔ Copiado al portapapeles";
    el.style.color = "green";

    setTimeout(()=>{
        el.textContent = "📋 Compartir";
        el.style.color = "";
    },2000);
}

/* ===== ELIMINAR POST ===== */
function deletePost(id){

    const confirmDelete = confirm("¿Estás seguro de que deseas borrar esta publicación?\nEsta acción es irreversible.");

    if(!confirmDelete) return;

    fetch(apiUrl("/posts/delete"),{
        method:"POST",
        headers:{
            "Content-Type":"application/json",
            "Authorization":"Bearer "+token
        },
        body:JSON.stringify({post_id:id})
    })
    .then(r=>r.json())
    .then(data=>{
        if(data.success){
            const postEl = document.getElementById("post-"+id);
            if(postEl) postEl.remove();
        }else{
            alert("Error al eliminar");
        }
    });
}

/* ===== NAV ===== */
function goToPost(id){
    window.location.href="post.html?id="+id;
}

function goToTag(tag, event){
    event.stopPropagation();
    window.location.href = `${window.publicUrl("explorar.html")}?tag=${tag}`;
}
