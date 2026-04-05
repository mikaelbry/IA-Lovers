<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Storage.php';

class UserController {

    private static function withAvatarUrl(array $user) {
        $user['avatar_url'] = !empty($user['avatar_path'])
            ? Storage::publicUrl($user['id'], $user['avatar_path'])
            : null;

        return $user;
    }

    private static function avatarExtension($mime) {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            default => null
        };
    }

    public static function profile() {

        $user = self::withAvatarUrl(Middleware::auth());
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
        $posts = Storage::mapPosts($stmt->fetchAll());

        Response::json([
            'user' => $user,
            'followers' => $followers,
            'posts' => $posts
        ]);
    }

    public static function profileByUsername() {

        $username = $_GET['username'] ?? null;

        if (!$username) {
            Response::json(['error' => 'Nombre de usuario requerido'], 400);
        }

        $pdo = Database::getConnection();

        $user = User::findByUsername($username);

        if (!$user) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $user = self::withAvatarUrl($user);

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
        $posts = Storage::mapPosts($postsStmt->fetchAll());

        Response::json([
            'user' => $user,
            'followers' => $followers,
            'is_following' => $isFollowing,
            'posts' => $posts
        ]);
    }

    public static function checkUsername() {

        $user = Middleware::auth();
        $username = trim($_GET['username'] ?? '');

        if ($username === '') {
            Response::json(['error' => 'Nombre de usuario requerido'], 400);
        }

        $existingUser = User::findByUsername($username);
        $available = !$existingUser || (int) $existingUser['id'] === (int) $user['id'];

        Response::json([
            'available' => $available
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
            SELECT id, username, avatar_path
            FROM usuarios
            WHERE id = ?
        ");
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();

        if (!$user) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $user = self::withAvatarUrl($user);

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
        $posts = Storage::mapPosts($postsStmt->fetchAll());

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
        $currentPassword = trim($data['current_password'] ?? '');

        if ($username === '' || $email === '') {
            Response::json(['error' => 'Nombre de usuario y correo son obligatorios'], 400);
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
            Response::json(['error' => 'Nombre de usuario ya registrado'], 400);
        }

        $requiresCurrentPassword = $username !== $user['username']
            || $email !== $user['email']
            || $password !== '';

        if ($password !== '' && strlen($password) < 6) {
            Response::json(['error' => 'La contrasena debe tener al menos 6 caracteres'], 400);
        }

        if ($requiresCurrentPassword) {
            if ($currentPassword === '') {
                Response::json(['error' => 'Debes confirmar tu contrasena actual para cambiar estos datos'], 400);
            }

            if (!password_verify($currentPassword, $user['password_hash'])) {
                Response::json(['error' => 'Contrasena actual incorrecta'], 401);
            }
        }

        User::update($user['id'], $username, $email, $password !== '' ? $password : null);

        Response::json(['message' => 'Perfil actualizado']);
    }

    public static function updateAvatar() {

        $user = Middleware::auth();

        if (!isset($_FILES['avatar'])) {
            Response::json(['error' => 'Avatar requerido'], 400);
        }

        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'Error al subir el avatar'], 400);
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            Response::json(['error' => 'El avatar no puede superar los 2 MB'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $extension = self::avatarExtension($mime);

        if ($extension === null) {
            Response::json(['error' => 'Formato de avatar no permitido'], 400);
        }

        $filename = 'avatar-' . bin2hex(random_bytes(16)) . $extension;
        $oldAvatarPath = $user['avatar_path'] ?? null;

        Storage::uploadUserFile($user['id'], $file['tmp_name'], $filename, $mime);

        try {
            User::updateAvatar($user['id'], $filename);

            if ($oldAvatarPath && $oldAvatarPath !== $filename) {
                Storage::deleteFile($user['id'], $oldAvatarPath);
            }
        } catch (Throwable $e) {
            Storage::deleteFile($user['id'], $filename);
            throw $e;
        }

        Response::json([
            'message' => 'Avatar actualizado',
            'avatar_path' => $filename,
            'avatar_url' => Storage::publicUrl($user['id'], $filename),
        ]);
    }

    public static function delete() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $currentPassword = trim($data['current_password'] ?? '');
        $confirmText = trim($data['confirm_text'] ?? '');

        if ($currentPassword === '') {
            Response::json(['error' => 'Debes introducir tu contrasena para borrar la cuenta'], 400);
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            Response::json(['error' => 'Contrasena actual incorrecta'], 401);
        }

        if ($confirmText !== 'ELIMINAR MI CUENTA') {
            Response::json(['error' => 'Falta la confirmacion final para borrar la cuenta'], 400);
        }

        $pdo = Database::getConnection();

        $postsStmt = $pdo->prepare("
            SELECT id, file_path
            FROM posts
            WHERE user_id = ?
        ");
        $postsStmt->execute([$user['id']]);
        $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        try {
            $commentTree = $pdo->prepare("
                WITH RECURSIVE comment_tree AS (
                    SELECT id
                    FROM comments
                    WHERE user_id = ?
                    OR post_id IN (
                        SELECT id
                        FROM posts
                        WHERE user_id = ?
                    )

                    UNION

                    SELECT comments.id
                    FROM comments
                    JOIN comment_tree ON comments.parent_id = comment_tree.id
                )
                DELETE FROM comments
                WHERE id IN (SELECT id FROM comment_tree)
            ");
            $commentTree->execute([$user['id'], $user['id']]);

            $pdo->prepare("
                DELETE FROM likes
                WHERE user_id = ?
                OR post_id IN (
                    SELECT id
                    FROM posts
                    WHERE user_id = ?
                )
            ")->execute([$user['id'], $user['id']]);

            $pdo->prepare("
                DELETE FROM notifications
                WHERE user_id = ?
                OR from_user_id = ?
                OR post_id IN (
                    SELECT id
                    FROM posts
                    WHERE user_id = ?
                )
            ")->execute([$user['id'], $user['id'], $user['id']]);

            $pdo->prepare("
                DELETE FROM follows
                WHERE follower_id = ?
                OR following_id = ?
            ")->execute([$user['id'], $user['id']]);

            $pdo->prepare("
                DELETE FROM post_tags
                WHERE post_id IN (
                    SELECT id
                    FROM posts
                    WHERE user_id = ?
                )
            ")->execute([$user['id']]);

            $pdo->prepare("
                DELETE FROM posts
                WHERE user_id = ?
            ")->execute([$user['id']]);

            foreach ($posts as $post) {
                Storage::deleteFile($user['id'], $post['file_path'] ?? null);
            }

            Storage::deleteFile($user['id'], $user['avatar_path'] ?? null);

            $pdo->prepare("
                DELETE FROM user_tokens
                WHERE user_id = ?
            ")->execute([$user['id']]);

            $pdo->prepare("
                DELETE FROM usuarios
                WHERE id = ?
            ")->execute([$user['id']]);

            $pdo->exec("
                DELETE FROM tags
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM post_tags
                    WHERE post_tags.tag_id = tags.id
                )
            ");

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json(['message' => 'Cuenta eliminada']);
    }
}
