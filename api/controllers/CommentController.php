<?php

require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Storage.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Notification.php';

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

        $commentId = Comment::create($user['id'], $post_id, $content, $parent_id);

        $pdo = Database::getConnection();

        $postOwnerStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $postOwnerStmt->execute([$post_id]);
        $postOwnerId = $postOwnerStmt->fetchColumn();

        if ($postOwnerId) {
            Notification::create($postOwnerId, 'comment', $user['id'], $post_id);
        }

        if ($parent_id) {
            $parentOwnerStmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
            $parentOwnerStmt->execute([$parent_id]);
            $parentOwnerId = $parentOwnerStmt->fetchColumn();

            if ($parentOwnerId && (int) $parentOwnerId !== (int) $postOwnerId) {
                Notification::create($parentOwnerId, 'reply', $user['id'], $post_id);
            }
        }

        $stmt = $pdo->prepare("
            SELECT comments.*, users.username, users.avatar_path
            FROM comments
            JOIN users ON users.id = comments.user_id
            WHERE comments.id = ?
        ");
        $stmt->execute([$commentId]);
        $newComment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($newComment) {
            foreach (['id', 'post_id', 'user_id', 'parent_id'] as $numericKey) {
                if (isset($newComment[$numericKey]) && $newComment[$numericKey] !== null) {
                    $newComment[$numericKey] = (int) $newComment[$numericKey];
                }
            }

            $newComment['avatar_url'] = !empty($newComment['avatar_path'])
                ? Storage::publicUrl($newComment['user_id'], $newComment['avatar_path'])
                : null;

            if (isset($newComment['content']) && $newComment['content'] !== null) {
                $newComment['content'] = html_entity_decode($newComment['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $count = Comment::countByPost($post_id);

        Response::json([
            'comment' => $newComment,
            'comments_count' => (int) $count
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
