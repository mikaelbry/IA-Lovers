<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Storage.php';
require_once __DIR__ . '/../models/User.php';

class FollowController {
    private static function mapFollowUser(array $user) {
        return [
            'username' => $user['username'],
            'avatar_url' => !empty($user['avatar_path'])
                ? Storage::publicUrl($user['id'], $user['avatar_path'])
                : null,
        ];
    }

    public static function follow() {

        $user = Middleware::auth();

        $data = json_decode(file_get_contents("php://input"), true);
        $username = $data['username'] ?? null;

        if (!$username) {
            Response::json(['error' => 'Usuario inválido'], 400);
        }

        $target = User::findByUsername($username);

        if (!$target || $target['id'] == $user['id']) {
            Response::json(['error' => 'Usuario inválido'], 400);
        }

        $pdo = Database::getConnection();

        $check = $pdo->prepare("
            SELECT 1 FROM follows
            WHERE follower_id = ?
            AND following_id = ?
        ");
        $check->execute([$user['id'], $target['id']]);

        if($check->fetch()){
            $pdo->prepare("
                DELETE FROM follows
                WHERE follower_id = ?
                AND following_id = ?
            ")->execute([$user['id'], $target['id']]);

            Response::json(['following'=>false]);
        }

        $pdo->prepare("
            INSERT INTO follows (follower_id,following_id)
            VALUES (?,?)
        ")->execute([$user['id'], $target['id']]);

        Response::json(['following'=>true]);
    }

    public static function followers() {

        $user_id = $_GET['user_id'] ?? null;

        if(!$user_id){
            $user = Middleware::auth();
            $user_id = $user['id'];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT usuarios.id, usuarios.username, usuarios.avatar_path
            FROM follows
            JOIN usuarios ON usuarios.id = follows.follower_id
            WHERE follows.following_id = ?
            ORDER BY usuarios.username ASC
        ");

        $stmt->execute([$user_id]);

        Response::json(array_map([self::class, 'mapFollowUser'], $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    public static function following() {

        $user_id = $_GET['user_id'] ?? null;

        if(!$user_id){
            $user = Middleware::auth();
            $user_id = $user['id'];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT usuarios.id, usuarios.username, usuarios.avatar_path
            FROM follows
            JOIN usuarios ON usuarios.id = follows.following_id
            WHERE follows.follower_id = ?
            ORDER BY usuarios.username ASC
        ");

        $stmt->execute([$user_id]);

        Response::json(array_map([self::class, 'mapFollowUser'], $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }
}
