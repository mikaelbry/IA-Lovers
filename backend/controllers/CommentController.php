<?php
/**
 * Controlador de comentarios en publicaciones.
 * Soporta creacion de comentarios y respuestas anidadas, y eliminacion
 * con soft-delete si tiene hijos.
 */
require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../services/Storage.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Notification.php';

class CommentController {
    /** Normaliza un comentario para respuesta JSON: convierte tipos, decodifica HTML y anonimiza si esta borrado. */
    public static function mapCommentResponse(array $comment) {
        $isDeleted = !empty($comment['deleted_at']);

        foreach (['id', 'post_id', 'user_id', 'parent_id'] as $numericKey) {
            if (isset($comment[$numericKey]) && $comment[$numericKey] !== null) {
                $comment[$numericKey] = (int) $comment[$numericKey];
            }
        }

        if (isset($comment['content']) && $comment['content'] !== null) {
            $comment['content'] = html_entity_decode($comment['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $comment['is_deleted'] = $isDeleted;

        if ($isDeleted) {
            $comment['user_id'] = null;
            $comment['username'] = 'Comentario borrado';
            $comment['avatar_path'] = null;
            $comment['avatar_url'] = null;
            $comment['content'] = '';
            return $comment;
        }

        $comment['avatar_url'] = !empty($comment['avatar_path'])
            ? Storage::publicUrl($comment['user_id'], $comment['avatar_path'])
            : null;

        return $comment;
    }

    /**
     * Crea un comentario o respuesta en una publicacion.
     * Valida que el post exista, que el comentario padre pertenezca al mismo
     * post y no este borrado. Genera notificaciones al dueno del post (comment)
     * y al dueno del comentario padre si es distinto (reply).
     */
    public static function create() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $post_id = $data['post_id'] ?? null;
        $content = trim($data['content'] ?? '');
        $parent_id = $data['parent_id'] ?? null;

        if (!$post_id || !$content) {
            Response::json(['error' => 'Datos inválidos'], 400);
        }

        $pdo = Database::getConnection();

        $postOwnerStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $postOwnerStmt->execute([$post_id]);
        $postOwnerId = $postOwnerStmt->fetchColumn();

        if (!$postOwnerId) {
            Response::json(['error' => 'Publicación no encontrada'], 404);
        }

        $parentOwnerId = null;

        if ($parent_id) {
            $parentStmt = $pdo->prepare("
                SELECT user_id, post_id, deleted_at
                FROM comments
                WHERE id = ?
            ");
            $parentStmt->execute([$parent_id]);
            $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent || (int) $parent['post_id'] !== (int) $post_id) {
                Response::json(['error' => 'Comentario padre no encontrado'], 404);
            }

            if (!empty($parent['deleted_at'])) {
                Response::json(['error' => 'No se puede responder a un comentario borrado'], 400);
            }

            $parentOwnerId = $parent['user_id'];
        }

        $commentId = Comment::create($user['id'], $post_id, $content, $parent_id);

        if ($postOwnerId) {
            Notification::create($postOwnerId, 'comment', $user['id'], $post_id);
        }

        if ($parentOwnerId && (int) $parentOwnerId !== (int) $postOwnerId) {
            Notification::create($parentOwnerId, 'reply', $user['id'], $post_id);
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
            $newComment = self::mapCommentResponse($newComment);
        }

        $count = Comment::countByPost($post_id);

        Response::json([
            'comment' => $newComment,
            'comments_count' => (int) $count
        ]);
    }

    /**
     * Elimina un comentario (solo el autor).
     * Soft-delete si tiene respuestas hijas, hard-delete si no.
     */
    public static function delete() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $comment_id = $data['comment_id'] ?? null;

        if (!$comment_id) {
            Response::json(['error' => 'ID requerido'], 400);
        }

        $result = Comment::delete($comment_id, $user['id']);

        if (!$result) {
            Response::json(['error' => 'Comentario no encontrado'], 404);
        }

        $count = Comment::countByPost($result['post_id']);

        Response::json([
            'deleted' => true,
            'mode' => $result['mode'],
            'comments_count' => (int) $count
        ]);
    }
}
