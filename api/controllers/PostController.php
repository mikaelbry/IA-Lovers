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
            Response::json(['error' => 'Archivo demasiado grande'], 400);
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
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = bin2hex(random_bytes(16)) . $extension;
        $targetPath = $uploadDir . $filename;

        move_uploaded_file($file['tmp_name'], $targetPath);

        $file_path = '/IA-Lovers/storage/uploads/' . $filename;

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, title, description, file_path, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $user['id'],
            htmlspecialchars($_POST['title'] ?? ''),
            htmlspecialchars($_POST['description'] ?? ''),
            $file_path
        ]);

        $postId = $pdo->lastInsertId();

        $tags = json_decode($_POST['tags'] ?? '[]', true);

        if ($tags) {
            $stmtTag = $pdo->prepare("
                INSERT INTO post_tags (post_id, tag_id)
                VALUES (?, ?)
            ");

            foreach ($tags as $tagId) {
                $stmtTag->execute([$postId, $tagId]);
            }
        }

        $pdo->commit();

        Response::json(['message' => 'Post creado']);
    }
    public static function toggleLike() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);
        $post_id = $data['post_id'] ?? null;

        if (!$post_id) {
            Response::json(['error' => 'ID requerido'], 400);
        }

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

        $pdo = Database::getConnection();
        $user_id = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            try {
                $user = Middleware::auth();
                $user_id = $user['id'];
            } catch (Exception $e) {
                $user_id = null;
            }
        }

        $title = $_GET['title'] ?? '';
        $tag = $_GET['tag'] ?? '';
        $order = $_GET['order'] ?? 'recent';

        $params = [$user_id];
        $where = [];

        if ($title !== '') {
            $where[] = "posts.title LIKE ?";
            $params[] = "%$title%";
        }

        if ($tag !== '') {
            $where[] = "EXISTS (
                SELECT 1 FROM post_tags
                JOIN tags ON tags.id = post_tags.tag_id
                WHERE post_tags.post_id = posts.id
                AND tags.name LIKE ?
            )";
            $params[] = "%$tag%";
        }

        $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

        $orderSQL = match ($order) {
            'oldest' => 'posts.created_at ASC',
            'likes' => 'likes_count DESC',
            default => 'posts.created_at DESC'
        };

        $sql = "
            SELECT 
                posts.*,
                usuarios.username,

                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as likes_count,

                (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count,

                EXISTS(
                    SELECT 1 FROM likes 
                    WHERE likes.post_id = posts.id 
                    AND likes.user_id = ?
                ) as liked_by_user,

                (
                    SELECT GROUP_CONCAT(tags.name SEPARATOR ',')
                    FROM post_tags
                    JOIN tags ON tags.id = post_tags.tag_id
                    WHERE post_tags.post_id = posts.id
                ) as tags

            FROM posts
            JOIN usuarios ON usuarios.id = posts.user_id
            $whereSQL
            ORDER BY $orderSQL
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    public static function show() {

        $post_id = $_GET['id'] ?? null;

        if (!$post_id) {
            Response::json(['error' => 'ID requerido'], 400);
        }

        $pdo = Database::getConnection();
        $user_id = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            try {
                $user = Middleware::auth();
                $user_id = $user['id'];
            } catch (Exception $e) {
                $user_id = null;
            }
        }

        $stmt = $pdo->prepare("
            SELECT 
                posts.*,
                usuarios.username,

                (SELECT COUNT(*) 
                FROM likes 
                WHERE likes.post_id = posts.id) AS likes_count,

                (SELECT COUNT(*) 
                FROM comments 
                WHERE comments.post_id = posts.id) AS comments_count,

                EXISTS(
                    SELECT 1 
                    FROM likes 
                    WHERE likes.post_id = posts.id 
                    AND likes.user_id = ?
                ) AS liked_by_user,

                (
                    SELECT GROUP_CONCAT(tags.name SEPARATOR ',')
                    FROM post_tags
                    JOIN tags ON tags.id = post_tags.tag_id
                    WHERE post_tags.post_id = posts.id
                ) AS tags

            FROM posts
            JOIN usuarios ON usuarios.id = posts.user_id
            WHERE posts.id = ?
        ");

        $stmt->execute([$user_id, $post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            Response::json(['error' => 'Post no encontrado'], 404);
        }

        $comments = Comment::getByPost($post_id);

        Response::json([
            'post' => $post,
            'comments' => $comments
        ]);
    }
}