<?php

require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../config/database.php';

class CommentController {

    public static function create() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $post_id = $data['post_id'] ?? null;
        $content = trim($data['content'] ?? '');
        $parent_id = $data['parent_id'] ?? null;

        if (!$post_id || !$content) {
            Response::json(['error' => 'Datos inválidos'], 400);
        }

        Comment::create($user['id'], $post_id, htmlspecialchars($content), $parent_id);

        // NOTIFICACIÓN AL AUTOR DEL POST
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post_owner = $stmt->fetchColumn();

        if ($post_owner && $post_owner != $user['id']) {
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, from_user_id, post_id)
                VALUES (?, 'comment', ?, ?)
            ")->execute([$post_owner, $user['id'], $post_id]);
        }

        Response::json(['message' => 'Comentario añadido']);
    }

    public static function delete() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $comment_id = $data['comment_id'] ?? null;

        if (!$comment_id) {
            Response::json(['error' => 'ID requerido'], 400);
        }

        Comment::delete($comment_id, $user['id']);

        Response::json(['message' => 'Comentario eliminado']);
    }
}