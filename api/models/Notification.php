<?php

require_once __DIR__ . '/../config/database.php';

class Notification {
    private const RETENTION_DAYS = 90;

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

    public static function deletePostActivity($postId) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE post_id = ?
            AND type IN ('like', 'comment', 'reply')
        ");

        return $stmt->execute([$postId]);
    }

    public static function purgeOld() {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL '" . self::RETENTION_DAYS . " days')
        ");
        $stmt->execute();
    }
}
