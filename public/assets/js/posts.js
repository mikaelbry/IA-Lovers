window.API ??= "/IA-Lovers/api";
window.token ??= localStorage.getItem("token");

let cursor = null;
let cursorLikes = null;
let loading = false;
let finished = false;
let observer = null;

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

        const profileUrl = `/IA-Lovers/public/user.html?username=${encodeURIComponent(post.username ?? "")}`;

        const tagsHTML = post.tags
        ? post.tags.split(',').map(t=>`<span class="tag">#${t}</span>`).join(' ')
        : '';

        container.innerHTML+=`
        <div class="post">

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
        observer.disconnect();
    }

    loading=false;
}

function toggleLike(event,id,btn){

    event.stopPropagation();

    if(!token){
        window.location.href="login.html";
        return;
    }

    fetch(API + "/posts/toggle-like",{
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

function goToPost(id){
    window.location.href="post.html?id="+id;
}
