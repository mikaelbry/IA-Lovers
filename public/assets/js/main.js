document.addEventListener("DOMContentLoaded", async () => {

    const container = document.getElementById("posts");

    const res = await fetch("/IA-Lovers/api/index.php/posts/latest");
    const posts = await res.json();

    posts.forEach(post => {

        const div = document.createElement("div");
        div.className = "card";

        div.innerHTML = `
            <h3>${post.title}</h3>
            <p>${post.description ?? ''}</p>
            <p>Por: ${post.username}</p>
            <img src="${post.file_path}" width="300">
        `;

        container.appendChild(div);
    });

});
