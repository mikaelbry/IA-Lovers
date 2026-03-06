<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';

class UserController {

    public static function profile() {

        $user = Middleware::auth();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT 
                posts.*,
                usuarios.username,

                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as likes_count,

                (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count,

                EXISTS(
                    SELECT 1 FROM likes 
                    WHERE likes.post_id = posts.id 
                    AND likes.user_id = ?
                ) as liked_by_user,

                (
                    SELECT GROUP_CONCAT(tags.name SEPARATOR ',')
                    FROM post_tags
                    JOIN tags ON tags.id = post_tags.tag_id
                    WHERE post_tags.post_id = posts.id
                ) as tags

            FROM posts
            JOIN usuarios ON usuarios.id = posts.user_id
            WHERE posts.user_id = ?
            ORDER BY posts.created_at DESC
        ");

        $stmt->execute([$user['id'], $user['id']]);
        $posts = $stmt->fetchAll();

        Response::json([
            "user" => $user,
            "posts" => $posts
        ]);
    }

    public static function update() {

        $user = Auth::user();

        $data = json_decode(file_get_contents("php://input"), true);

        $username = $data['username'] ?? $user['username'];
        $email = $data['email'] ?? $user['email'];
        $password = $data['password'] ?? null;

        User::update($user['id'], $username, $email, $password);

        Response::json(['message' => 'Perfil actualizado']);
    }

    public static function publicProfile() {

        $user_id = $_GET['id'] ?? null;

        if (!$user_id) {
            Response::json(["error"=>"Usuario requerido"],400);
        }

        $pdo = Database::getConnection();

        $viewer = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            try {
                $viewer = Middleware::auth();
            } catch(Exception $e) {}
        }

        $viewer_id = $viewer['id'] ?? null;

        $userStmt = $pdo->prepare("
            SELECT id, username
            FROM usuarios
            WHERE id = ?
        ");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();

        if(!$user){
            Response::json(["error"=>"Usuario no encontrado"],404);
        }

        $postsStmt = $pdo->prepare("
            SELECT 
                posts.*,
                usuarios.username,

                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as likes_count,

                (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count,

                EXISTS(
                    SELECT 1 FROM likes 
                    WHERE likes.post_id = posts.id 
                    AND likes.user_id = ?
                ) as liked_by_user,

                (
                    SELECT GROUP_CONCAT(tags.name SEPARATOR ',')
                    FROM post_tags
                    JOIN tags ON tags.id = post_tags.tag_id
                    WHERE post_tags.post_id = posts.id
                ) as tags

            FROM posts
            JOIN usuarios ON usuarios.id = posts.user_id
            WHERE posts.user_id = ?
            ORDER BY posts.created_at DESC
        ");

        $postsStmt->execute([$viewer_id, $user_id]);
        $posts = $postsStmt->fetchAll();

        $followersStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM follows
            WHERE following_id = ?
        ");
        $followersStmt->execute([$user_id]);

        $followers = $followersStmt->fetchColumn();

        Response::json([
            "user"=>$user,
            "followers"=>$followers,
            "posts"=>$posts
        ]);
    }

}
