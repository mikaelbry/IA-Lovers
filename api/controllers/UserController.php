<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PendingEmailChange.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/RateLimiter.php';
require_once __DIR__ . '/../core/Storage.php';
require_once __DIR__ . '/../core/GmailMailer.php';

class UserController {
    private const EMAIL_CHANGE_CODE_TTL = 600;
    private const EMAIL_CHANGE_MAX_ATTEMPTS = 5;
    private const EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS = 30;
    private const MAX_AVATAR_MB = 4;
    private const MAX_AVATAR_BYTES = self::MAX_AVATAR_MB * 1024 * 1024;

    private static function withAvatarUrl(array $user) {
        $user['avatar_url'] = !empty($user['avatar_path'])
            ? Storage::publicUrl($user['id'], $user['avatar_path'])
            : null;

        return $user;
    }

    private static function privateUserPayload(array $user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? null,
            'avatar_path' => $user['avatar_path'] ?? null,
        ];
    }

    private static function avatarExtension($mime) {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            default => null
        };
    }

    private static function avatarUploadErrorMessage($errorCode) {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El avatar no puede superar los ' . self::MAX_AVATAR_MB . ' MB',
            UPLOAD_ERR_PARTIAL => 'La subida del avatar no se completo',
            UPLOAD_ERR_NO_FILE => 'Avatar requerido',
            default => 'Error al subir el avatar'
        };
    }

    private static function jsonBody() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON invalido'], 400);
        }

        return $data;
    }

    private static function generateVerificationCode() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private static function emailChangeExpiresAt() {
        return date('Y-m-d H:i:s', time() + self::EMAIL_CHANGE_CODE_TTL);
    }

    private static function maskedEmail($email) {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($localPart === '' || $domain === '') {
            return $email;
        }

        $visible = substr($localPart, 0, min(2, strlen($localPart)));
        return $visible . str_repeat('*', max(2, strlen($localPart) - strlen($visible))) . '@' . $domain;
    }

    public static function profile() {

        $user = self::privateUserPayload(self::withAvatarUrl(Middleware::auth()));
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
            'followers' => (int) $followers,
            'posts' => $posts
        ]);
    }

    public static function settingsSummary() {

        $user = self::privateUserPayload(self::withAvatarUrl(Middleware::auth()));
        $pdo = Database::getConnection();

        $statsStmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM follows WHERE following_id = ?) AS followers,
                (SELECT COUNT(*) FROM posts WHERE user_id = ?) AS posts_count
        ");
        $statsStmt->execute([$user['id'], $user['id']]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        Response::json([
            'user' => $user,
            'followers' => (int) ($stats['followers'] ?? 0),
            'posts_count' => (int) ($stats['posts_count'] ?? 0),
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

        if (Auth::hasAuthorizationHeader()) {
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
            'followers' => (int) $followers,
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

        if (Auth::hasAuthorizationHeader()) {
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
            'followers' => (int) $followers,
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

        if ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) {
            Response::json(['error' => 'La contraseña debe tener al menos 8 caracteres e incluir letras y numeros'], 400);
        }

        if ($requiresCurrentPassword) {
            if ($currentPassword === '') {
                Response::json(['error' => 'Debes confirmar tu contrasena actual para cambiar estos datos'], 400);
            }

            if (!password_verify($currentPassword, $user['password_hash'])) {
                Response::json(['error' => 'Contraseña actual incorrecta'], 401);
            }
        }

        User::update($user['id'], $username, $email, $password !== '' ? $password : null);

        if ($password !== '') {
            Auth::revokeOtherTokens($user['id'], $user['token'] ?? null);
        }

        Response::json(['message' => 'Perfil actualizado']);
    }

    public static function updateAvatar() {

        $user = Middleware::auth();

        if (!isset($_FILES['avatar'])) {
            Response::json(['error' => 'Avatar requerido'], 400);
        }

        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => self::avatarUploadErrorMessage($file['error'])], 400);
        }

        if ($file['size'] > self::MAX_AVATAR_BYTES) {
            Response::json(['error' => 'El avatar no puede superar los ' . self::MAX_AVATAR_MB . ' MB'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);

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

    public static function startEmailChange() {

        RateLimiter::check('email_change_start_attempts', 5, 300);
        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();
        $data = self::jsonBody();

        $newEmail = trim((string) ($data['new_email'] ?? ''));
        $currentPassword = trim((string) ($data['current_password'] ?? ''));

        if ($newEmail === '') {
            Response::json(['error' => 'Debes introducir un nuevo correo'], 400);
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Email invalido'], 400);
        }

        if ($newEmail === $user['email']) {
            Response::json(['error' => 'Introduce un correo distinto al actual'], 400);
        }

        if ($currentPassword === '') {
            Response::json(['error' => 'Debes introducir tu contraseña actual'], 400);
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            Response::json(['error' => 'Contraseña actual incorrecta'], 401);
        }

        $existingUser = User::findByEmail($newEmail);
        if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
            Response::json(['error' => 'Ese correo ya esta en uso'], 409);
        }

        $existingPendingForEmail = PendingEmailChange::findByNewEmail($newEmail);
        if ($existingPendingForEmail && (int) $existingPendingForEmail['user_id'] !== (int) $user['id']) {
            Response::json(['error' => 'Ya hay una verificacion pendiente para ese correo'], 409);
        }

        $pending = PendingEmailChange::findByUserId($user['id']);
        $verificationCode = self::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
        $expiresAt = self::emailChangeExpiresAt();
        $created = false;

        if ($pending) {
            PendingEmailChange::updateRequest(
                $pending['id'],
                $newEmail,
                $verificationCodeHash,
                $expiresAt
            );
        } else {
            PendingEmailChange::create(
                $user['id'],
                $newEmail,
                $verificationCodeHash,
                $expiresAt
            );
            $created = true;
        }

        try {
            GmailMailer::sendEmailChangeCode($newEmail, $user['username'], $verificationCode);
        } catch (Throwable $e) {
            if ($created) {
                PendingEmailChange::deleteByUserId($user['id']);
            }

            throw $e;
        }

        Response::json([
            'message' => 'Codigo enviado al nuevo correo',
            'new_email' => $newEmail,
            'masked_email' => self::maskedEmail($newEmail),
            'resend_cooldown' => self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public static function resendEmailChange() {

        RateLimiter::check('email_change_resend_attempts', 5, 300);
        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();

        $pending = PendingEmailChange::findByUserId($user['id']);

        if (!$pending) {
            Response::json(['error' => 'No hay una verificacion de correo pendiente'], 404);
        }

        $lastSent = strtotime($pending['last_sent_at']);
        if ($lastSent && (time() - $lastSent) < self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS) {
            $remaining = self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS - (time() - $lastSent);
            Response::json([
                'error' => 'Espera ' . $remaining . ' segundos antes de pedir otro codigo',
                'retry_after' => $remaining,
            ], 429);
        }

        $existingUser = User::findByEmail($pending['new_email']);
        if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'Ese correo ya ha pasado a estar en uso'], 409);
        }

        $verificationCode = self::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
        $expiresAt = self::emailChangeExpiresAt();

        PendingEmailChange::updateRequest(
            $pending['id'],
            $pending['new_email'],
            $verificationCodeHash,
            $expiresAt
        );

        GmailMailer::sendEmailChangeCode($pending['new_email'], $user['username'], $verificationCode);

        Response::json([
            'message' => 'Hemos reenviado un nuevo codigo',
            'masked_email' => self::maskedEmail($pending['new_email']),
            'resend_cooldown' => self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public static function verifyEmailChange() {

        RateLimiter::check('email_change_verify_attempts', 10, 300);
        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();
        $data = self::jsonBody();

        $code = trim((string) ($data['code'] ?? ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            Response::json(['error' => 'El codigo debe tener 6 digitos'], 400);
        }

        $pending = PendingEmailChange::findByUserId($user['id']);

        if (!$pending) {
            Response::json(['error' => 'La verificacion pendiente no existe o ya ha caducado'], 404);
        }

        if (strtotime($pending['verification_expires_at']) < time()) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'El codigo ha caducado. Solicita uno nuevo'], 410);
        }

        if ((int) $pending['verification_attempts'] >= self::EMAIL_CHANGE_MAX_ATTEMPTS) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'Se ha superado el número maximo de intentos. Solicita un nuevo codigo'], 429);
        }

        if (!password_verify($code, $pending['verification_code_hash'])) {
            PendingEmailChange::incrementAttempts($pending['id']);

            if (((int) $pending['verification_attempts']) + 1 >= self::EMAIL_CHANGE_MAX_ATTEMPTS) {
                PendingEmailChange::deleteById($pending['id']);
                Response::json(['error' => 'Codigo incorrecto demasiadas veces. Vuelve a solicitar el cambio de correo'], 429);
            }

            Response::json(['error' => 'Codigo incorrecto'], 400);
        }

        $existingUser = User::findByEmail($pending['new_email']);
        if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'Ese correo ya ha pasado a estar en uso'], 409);
        }

        User::update($user['id'], $user['username'], $pending['new_email']);
        PendingEmailChange::deleteById($pending['id']);

        Response::json([
            'message' => 'Correo actualizado correctamente',
            'email' => $pending['new_email'],
        ]);
    }

    public static function cancelEmailChange() {

        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();
        PendingEmailChange::deleteByUserId($user['id']);

        Response::json(['success' => true]);
    }

    public static function delete() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $currentPassword = trim($data['current_password'] ?? '');
        $confirmText = trim($data['confirm_text'] ?? '');

        if ($currentPassword === '') {
            Response::json(['error' => 'Debes introducir tu contraseña para borrar la cuenta'], 400);
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            Response::json(['error' => 'Contraseña actual incorrecta'], 401);
        }

        if ($confirmText !== 'ELIMINAR MI CUENTA') {
            Response::json(['error' => 'Falta la confirmación final para borrar la cuenta'], 400);
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
                DELETE FROM pending_email_changes
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
