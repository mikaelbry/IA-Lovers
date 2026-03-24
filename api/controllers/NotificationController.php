<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Middleware.php';

class NotificationController {

    public static function get() {

        $user = Middleware::auth();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT n.*, u.username as from_username
            FROM notifications n
            LEFT JOIN usuarios u ON u.id = n.from_user_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT 20
        ");

        $stmt->execute([$user['id']]);

        Response::json($stmt->fetchAll());
    }
}
