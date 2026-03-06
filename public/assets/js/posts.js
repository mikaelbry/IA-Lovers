window.token = localStorage.getItem("token");

function formatDate(dateString) {

    if (!dateString) return "";

    const date = new Date(dateString);

    const day = String(date.getDate()).padStart(2,'0');
    const month = String(date.getMonth()+1).padStart(2,'0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2,'0');
    const minutes = String(date.getMinutes()).padStart(2,'0');

    return `${hours}:${minutes} - ${day}/${month}/${year}`;
}

function renderPosts(posts, containerId="posts") {

    const container = document.getElementById(containerId);

    if (!container) return;

    container.innerHTML = "";

    if (!Array.isArray(posts) || posts.length === 0) {
        container.innerHTML = "<p>No hay publicaciones.</p>";
        return;
    }

    posts.forEach(post => {

        const tagsHTML = post.tags
            ? post.tags.split(',').map(t => `<span class="tag">#${t}</span>`).join(' ')
            : '';

        container.innerHTML += `
        <div class="post">

            <div class="post-header">
                <a href="user.html?id=${post.user_id}" onclick="event.stopPropagation()">
                    ${post.username ?? ''}
                </a>
            </div>

            <div class="post-date">
                ${formatDate(post.created_at)}
            </div>

            <img src="${post.file_path}"
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
                    class="like-btn ${post.liked_by_user == 1 ? 'liked' : ''}"
                    onclick="toggleLike(event, ${post.id}, this)">
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

function toggleLike(event, id, btn) {

    event.stopPropagation();

    if (!token) {
        window.location.href = "login.html";
        return;
    }

    fetch("../api/index.php/posts/toggle-like",{
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
            span.textContent = count + 1;
        } else {
            btn.classList.remove("liked");
            span.textContent = count - 1;
        }

    });

}

function goToPost(id){
    window.location.href="post.html?id="+id;
}