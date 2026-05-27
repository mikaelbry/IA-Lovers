<?php
/**
 * Modelo de acceso a datos de comentarios.
 */
require_once __DIR__ . '/../config/Database.php';

class Comment {

    /** Inserta un comentario y devuelve su ID. */
    public static function create($user_id, $post_id, $content, $parent_id = null) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO comments (user_id, post_id, content, parent_id)
            VALUES (?, ?, ?, ?)
            RETURNING id
        ");

        $stmt->execute([$user_id, $post_id, $content, $parent_id]);

        return $stmt->fetchColumn();
    }

    /**
     * Elimina un comentario (solo si es del usuario).
     * Si tiene hijos, soft-delete. Si no, hard-delete.
     * Usa FOR UPDATE para evitar condiciones de carrera.
     * Devuelve el modo (soft/hard) y el post_id.
     */
    public static function delete($comment_id, $user_id) {

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                SELECT id, post_id
                FROM comments
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$comment_id, $user_id]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$comment) {
                $pdo->rollBack();
                return null;
            }

            $childrenStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM comments
                WHERE parent_id = ?
            ");
            $childrenStmt->execute([$comment_id]);
            $hasChildren = ((int) $childrenStmt->fetchColumn()) > 0;

            if ($hasChildren) {
                $deleteStmt = $pdo->prepare("
                    UPDATE comments
                    SET deleted_at = CURRENT_TIMESTAMP,
                        content = ''
                    WHERE id = ?
                ");
                $deleteStmt->execute([$comment_id]);
                $mode = 'soft';
            } else {
                $deleteStmt = $pdo->prepare("
                    DELETE FROM comments
                    WHERE id = ?
                ");
                $deleteStmt->execute([$comment_id]);
                $mode = 'hard';
            }

            $pdo->commit();

            return [
                'mode' => $mode,
                'post_id' => (int) $comment['post_id'],
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Obtiene todos los comentarios de un post ordenados por fecha. */
    public static function getByPost($post_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT comments.*, users.username
            FROM comments
            JOIN users ON users.id = comments.user_id
            WHERE post_id = ?
            ORDER BY created_at ASC
        ");

        $stmt->execute([$post_id]);

        return $stmt->fetchAll();
    }

    /** Cuenta los comentarios de un post. */
    public static function countByPost($post_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM comments WHERE post_id = ?
        ");

        $stmt->execute([$post_id]);

        return $stmt->fetchColumn();
    }
}
