<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';

class UserController {

    public static function profile() {

        $user = Auth::user();

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
}
