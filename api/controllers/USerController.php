<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';

class UserController {

    public static function profile() {

        $user = Middleware::auth();
        $pdo = Database::getConnection();

        $followersStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM follows
            WHERE following_id = ?
        ");
        $followersStmt->execute([$user['id']]);
        $followers = $followersStmt->fetchColumn();

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
                    SELECT STRING_AGG(tags.name, ',' ORDER BY tags.name)
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
            'user' => $user,
            'followers' => $followers,
            'posts' => $posts
        ]);
    }

    public static function profileByUsername() {

        $username = $_GET['username'] ?? null;

        if (!$username) {
            Response::json(['error' => 'Username requerido'], 400);
        }

        $pdo = Database::getConnection();

        $user = User::findByUsername($username);

        if (!$user) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $user_id = $user['id'];

        $viewer = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            try {
                $viewer = Middleware::auth();
            } catch (Exception $e) {
            }
        }

        $viewer_id = $viewer['id'] ?? null;

        $followersStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM follows
            WHERE following_id = ?
        ");
        $followersStmt->execute([$user_id]);
        $followers = $followersStmt->fetchColumn();

        $isFollowing = false;

        if ($viewer_id) {
            $check = $pdo->prepare("
                SELECT 1 FROM follows
                WHERE follower_id = ?
                AND following_id = ?
            ");

            $check->execute([$viewer_id, $user_id]);

            $isFollowing = $check->fetch() ? true : false;
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
                    SELECT STRING_AGG(tags.name, ',' ORDER BY tags.name)
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

        Response::json([
            'user' => $user,
            'followers' => $followers,
            'is_following' => $isFollowing,
            'posts' => $posts
        ]);
    }

    public static function publicProfile() {

        $user_id = $_GET['id'] ?? null;

        if (!$user_id) {
            Response::json(['error' => 'Usuario requerido'], 400);
        }

        $pdo = Database::getConnection();

        $viewer = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            try {
                $viewer = Middleware::auth();
            } catch (Exception $e) {
            }
        }

        $viewer_id = $viewer['id'] ?? null;

        $userStmt = $pdo->prepare("
            SELECT id, username
            FROM usuarios
            WHERE id = ?
        ");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();

        if (!$user) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $followersStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM follows
            WHERE following_id = ?
        ");
        $followersStmt->execute([$user_id]);
        $followers = $followersStmt->fetchColumn();

        $isFollowing = false;

        if ($viewer_id) {
            $check = $pdo->prepare("
                SELECT 1 FROM follows
                WHERE follower_id = ?
                AND following_id = ?
            ");

            $check->execute([$viewer_id, $user_id]);

            $isFollowing = $check->fetch() ? true : false;
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
                    SELECT STRING_AGG(tags.name, ',' ORDER BY tags.name)
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

        Response::json([
            'user' => $user,
            'followers' => $followers,
            'is_following' => $isFollowing,
            'posts' => $posts
        ]);
    }

    public static function update() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $username = trim($data['username'] ?? $user['username']);
        $email = trim($data['email'] ?? $user['email']);
        $password = trim($data['password'] ?? '');

        if ($username === '' || $email === '') {
            Response::json(['error' => 'Username y email son obligatorios'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Email invalido'], 400);
        }

        $existingEmail = User::findByEmail($email);
        if ($existingEmail && (int) $existingEmail['id'] !== (int) $user['id']) {
            Response::json(['error' => 'Email ya registrado'], 400);
        }

        $existingUsername = User::findByUsername($username);
        if ($existingUsername && (int) $existingUsername['id'] !== (int) $user['id']) {
            Response::json(['error' => 'Username ya registrado'], 400);
        }

        User::update($user['id'], $username, $email, $password !== '' ? $password : null);

        Response::json(['message' => 'Perfil actualizado']);
    }
}
