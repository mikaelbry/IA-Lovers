<?php
/**
 * Modelo de acceso a datos de notificaciones.
 * Las notificaciones se purgan automaticamente tras 90 dias.
 */
require_once __DIR__ . '/../config/Database.php';

class Notification {
    /** Dias que se conservan las notificaciones antes de purgarse. */
    private const RETENTION_DAYS = 90;

    /**
     * Crea una notificacion. Tipos validos: follow, like, comment, reply.
     * No crea notificacion si el usuario es el mismo que el actor.
     */
    public static function create($userId, $type, $fromUserId, $postId = null) {
        if (!$userId || !$fromUserId || (int) $userId === (int) $fromUserId) {
            return false;
        }

        $allowedTypes = ['follow', 'like', 'comment', 'reply'];
        if (!in_array($type, $allowedTypes, true)) {
            return false;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, from_user_id, post_id, is_read, created_at)
            VALUES (?, ?, ?, ?, false, CURRENT_TIMESTAMP)
        ");

        return $stmt->execute([
            $userId,
            $type,
            $fromUserId,
            $postId,
        ]);
    }

    /** Elimina una notificacion de like especifica (al quitar un like). */
    public static function deleteLike($userId, $fromUserId, $postId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = ?
            AND from_user_id = ?
            AND post_id = ?
            AND type = 'like'
        ");

        return $stmt->execute([$userId, $fromUserId, $postId]);
    }

    /** Elimina todas las notificaciones relacionadas a un post (al borrarlo). */
    public static function deletePostActivity($postId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE post_id = ?
            AND type IN ('like', 'comment', 'reply')
        ");

        return $stmt->execute([$postId]);
    }

    /** Elimina notificaciones con mas de RETENTION_DAYS dias de antiguedad. */
    public static function purgeOld() {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL '" . self::RETENTION_DAYS . " days')
        ");
        $stmt->execute();
    }
}
