<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/User.php';

class FollowController {

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
            SELECT usuarios.username
            FROM follows
            JOIN usuarios ON usuarios.id = follows.follower_id
            WHERE follows.following_id = ?
        ");

        $stmt->execute([$user_id]);

        Response::json($stmt->fetchAll());
    }

    public static function following() {

        $user_id = $_GET['user_id'] ?? null;

        if(!$user_id){
            $user = Middleware::auth();
            $user_id = $user['id'];
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT usuarios.username
            FROM follows
            JOIN usuarios ON usuarios.id = follows.following_id
            WHERE follows.follower_id = ?
        ");

        $stmt->execute([$user_id]);

        Response::json($stmt->fetchAll());
    }
}