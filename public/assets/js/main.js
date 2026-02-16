document.addEventListener("DOMContentLoaded", async () => {

    try {
        const response = await fetch("/api/posts/latest");
        const posts = await response.json();

        const container = document.getElementById("posts");

        posts.forEach(post => {
            const div = document.createElement("div");

            div.innerHTML = `
                <h3>${post.title}</h3>
                <p>${post.description ?? ''}</p>
                <p>Por: ${post.username}</p>
                <p>Likes: ${post.likes_count}</p>
                <hr>
            `;

            container.appendChild(div);
        });

    } catch (error) {
        console.error("Error cargando posts:", error);
    }

});
