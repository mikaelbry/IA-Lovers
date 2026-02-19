<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';

class UserController {

    public static function profile() {
        $user = Middleware::auth();

        $userData = User::findById($user['id']);

        $posts = Post::getByUser($user['id']);

        Response::json([
            'user' => $userData,
            'posts' => $posts
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
            Response::json(['error' => 'ID requerido'], 400);
        }

        $userData = User::findById($user_id);

        if (!$userData) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $posts = Post::getByUser($user_id);

        $pdo = Database::getConnection();

        $followers = $pdo->prepare("
            SELECT COUNT(*) FROM follows WHERE following_id = ?
        ");
        $followers->execute([$user_id]);

        Response::json([
            'user' => $userData,
            'posts' => $posts,
            'followers' => $followers->fetchColumn()
        ]);
    }

}
