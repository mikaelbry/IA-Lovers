<?php

require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';

class PostController {

    public static function create() {
        $user = Middleware::auth();


        if (!isset($_FILES['image'])) {
            Response::json(['error' => 'Imagen requerida'], 400);
        }

        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'Error al subir archivo'], 400);
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            Response::json(['error' => 'Archivo demasiado grande (max 2MB)'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed)) {
            Response::json(['error' => 'Formato no permitido'], 400);
        }

        $extension = match($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp'
        };

        $uploadDir = __DIR__ . '/../../storage/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . $extension;
        $targetPath = $uploadDir . $filename;

        move_uploaded_file($file['tmp_name'], $targetPath);

        $file_path = '/IA-Lovers/storage/uploads/' . $filename;

        Post::create(
            $user['id'],
            htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            $file_path
        );

        Response::json(['message' => 'Post creado']);
    }

    public static function toggleLike() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);
        $post_id = $data['post_id'];

        $pdo = Database::getConnection();

        $check = $pdo->prepare("
            SELECT id FROM likes WHERE user_id = ? AND post_id = ?
        ");
        $check->execute([$user['id'], $post_id]);

        if ($check->fetch()) {

            $pdo->prepare("
                DELETE FROM likes WHERE user_id = ? AND post_id = ?
            ")->execute([$user['id'], $post_id]);

            Response::json(['liked' => false]);
        }

        $pdo->prepare("
            INSERT INTO likes (user_id, post_id)
            VALUES (?, ?)
        ")->execute([$user['id'], $post_id]);

        Response::json(['liked' => true]);
    }



    public static function latest() {
        $posts = Post::getLatest();
        Response::json($posts);
    }

    public static function show() {

        $post_id = $_GET['id'] ?? null;

        if (!$post_id) {
            Response::json(['error' => 'ID requerido'], 400);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                posts.*,
                usuarios.username,
                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count
            FROM posts
            JOIN usuarios ON usuarios.id = posts.user_id
            WHERE posts.id = ?
        ");

        $stmt->execute([$post_id]);

        $post = $stmt->fetch();

        if (!$post) {
            Response::json(['error' => 'Post no encontrado'], 404);
        }

        require_once __DIR__ . '/../models/Comment.php';

        $comments = Comment::getByPost($post_id);

        Response::json([
            'post' => $post,
            'comments' => $comments
        ]);
    }
}
