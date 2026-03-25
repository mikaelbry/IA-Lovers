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
            Response::json(['error' => 'Datos invalidos'], 400);
        }

        $commentId = Comment::create($user['id'], $post_id, htmlspecialchars($content), $parent_id);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT comments.*, usuarios.username
            FROM comments
            JOIN usuarios ON usuarios.id = comments.user_id
            WHERE comments.id = ?
        ");
        $stmt->execute([$commentId]);
        $newComment = $stmt->fetch(PDO::FETCH_ASSOC);

        $count = Comment::countByPost($post_id);

        Response::json([
            'comment' => $newComment,
            'comments_count' => $count
        ]);
    }

    public static function delete() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $comment_id = $data['comment_id'] ?? null;

        if (!$comment_id) {
            Response::json(['error' => 'ID requerido'], 400);
        }

        Comment::delete($comment_id, $user['id']);

        Response::json(['deleted' => true]);
    }
}
