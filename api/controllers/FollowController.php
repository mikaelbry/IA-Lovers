<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Middleware.php';

class FollowController {

    public static function follow() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);
        $target = $data['user_id'];

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO follows (follower_id, following_id)
            VALUES (?, ?)
        ");

        $stmt->execute([$user['id'], $target]);

        // crear notificación
        $pdo->prepare("
            INSERT INTO notifications (user_id, type, from_user_id)
            VALUES (?, 'follow', ?)
        ")->execute([$target, $user['id']]);

        Response::json(['message' => 'Ahora sigues a este usuario']);
    }

    public static function followingPosts() {

        $user = Middleware::auth();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT posts.*, usuarios.username
            FROM posts
            JOIN follows ON follows.following_id = posts.user_id
            JOIN usuarios ON usuarios.id = posts.user_id
            WHERE follows.follower_id = ?
            ORDER BY posts.created_at DESC
        ");

        $stmt->execute([$user['id']]);

        Response::json($stmt->fetchAll());
    }
}
