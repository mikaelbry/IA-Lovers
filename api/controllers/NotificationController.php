<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Storage.php';
require_once __DIR__ . '/../models/Notification.php';

class NotificationController {

    public static function get() {

        $user = Middleware::auth();
        $pdo = Database::getConnection();
        Notification::purgeOld();

        $stmt = $pdo->prepare("
            SELECT
                n.id,
                n.type,
                n.from_user_id,
                n.post_id,
                n.is_read,
                to_char(n.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') as created_at,
                u.username as from_username,
                u.avatar_path as from_avatar_path,
                p.title as post_title,
                p.file_path as post_file_path,
                p.user_id as post_user_id
            FROM notifications n
            LEFT JOIN usuarios u ON u.id = n.from_user_id
            LEFT JOIN posts p ON p.id = n.post_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT 30
        ");

        $stmt->execute([$user['id']]);
        $notifications = array_map([self::class, 'mapNotification'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
            AND is_read = false
        ");
        $countStmt->execute([$user['id']]);

        Response::json([
            'notifications' => $notifications,
            'unread_count' => (int) $countStmt->fetchColumn(),
        ]);
    }

    public static function unreadCount() {
        $user = Middleware::auth();
        $pdo = Database::getConnection();
        Notification::purgeOld();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
            AND is_read = false
        ");
        $stmt->execute([$user['id']]);

        Response::json(['unread_count' => (int) $stmt->fetchColumn()]);
    }

    public static function markRead() {
        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'] ?? null;
        $pdo = Database::getConnection();

        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = true
                WHERE user_id = ?
                AND id = ?
            ");
            $stmt->execute([$user['id'], $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = true
                WHERE user_id = ?
                AND is_read = false
            ");
            $stmt->execute([$user['id']]);
        }

        Response::json(['success' => true]);
    }

    private static function mapNotification($notification) {
        $fromUserId = $notification['from_user_id'] ?? null;
        $avatarPath = $notification['from_avatar_path'] ?? null;
        $postUserId = $notification['post_user_id'] ?? null;
        $postFilePath = $notification['post_file_path'] ?? null;

        return [
            'id' => (int) $notification['id'],
            'type' => $notification['type'],
            'from_user_id' => $fromUserId !== null ? (int) $fromUserId : null,
            'from_username' => $notification['from_username'] ?? null,
            'from_avatar_url' => $fromUserId && $avatarPath
                ? Storage::publicUrl($fromUserId, $avatarPath)
                : null,
            'post_id' => $notification['post_id'] !== null ? (int) $notification['post_id'] : null,
            'post_title' => $notification['post_title'] ?? null,
            'post_image_url' => $postUserId && $postFilePath
                ? Storage::publicUrl($postUserId, $postFilePath)
                : null,
            'is_read' => (bool) $notification['is_read'],
            'created_at' => $notification['created_at'],
        ];
    }
}
