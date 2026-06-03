<?php
/**
 * Controlador de usuarios.
 * Gestiona perfiles, configuracion, avatar, cambio de email y eliminacion
 * de cuenta.
 */
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PendingEmailChange.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../services/Storage.php';
require_once __DIR__ . '/../services/GmailMailer.php';

class UserController {
    private const EMAIL_CHANGE_CODE_TTL = 600;
    private const EMAIL_CHANGE_MAX_ATTEMPTS = 5;
    private const EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS = 30;
    private const MAX_AVATAR_MB = 4;
    private const MAX_AVATAR_BYTES = self::MAX_AVATAR_MB * 1024 * 1024;

    /** Agrega la URL publica del avatar al array del usuario. */
    private static function withAvatarUrl(array $user) {
        $user['avatar_url'] = !empty($user['avatar_path'])
            ? Storage::publicUrl($user['id'], $user['avatar_path'])
            : null;

        return $user;
    }

    /** Extrae solo los campos seguros del usuario para exponer en la API. */
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

    /** Agrega la URL publica del avatar del autor a un registro de post. */
    private static function withPostAuthorAvatarUrl(array $post) {
        $post['avatar_url'] = !empty($post['avatar_path']) && !empty($post['user_id'])
            ? Storage::publicUrl($post['user_id'], $post['avatar_path'])
            : null;

        return $post;
    }

    /** Devuelve la extension de archivo segun el MIME type del avatar. */
    private static function avatarExtension($mime) {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            default => null
        };
    }

    /** Traduce codigos de error de subida de avatar a mensajes. */
    private static function avatarUploadErrorMessage($errorCode) {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El avatar no puede superar los ' . self::MAX_AVATAR_MB . ' MB',
            UPLOAD_ERR_PARTIAL => 'La subida del avatar no se completo',
            UPLOAD_ERR_NO_FILE => 'El avatar es obligatorio',
            default => 'Error al subir el avatar'
        };
    }

    /** Lee y parsea el body JSON de la peticion. Responde 400 si es invalido. */
    private static function jsonBody() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON inválido'], 400);
        }

        return $data;
    }

    /** Genera un codigo de verificacion de 6 digitos. */
    private static function generateVerificationCode() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Calcula la fecha de expiracion del codigo de cambio de email (+10 min). */
    private static function emailChangeExpiresAt() {
        return date('Y-m-d H:i:s', time() + self::EMAIL_CHANGE_CODE_TTL);
    }

    /** Ofusca parcialmente un email para mostrarlo en la interfaz (ej: ju****@d.com). */
    private static function maskedEmail($email) {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($localPart === '' || $domain === '') {
            return $email;
        }

        $visible = substr($localPart, 0, min(2, strlen($localPart)));
        return $visible . str_repeat('*', max(2, strlen($localPart) - strlen($visible))) . '@' . $domain;
    }

    /** Devuelve el perfil completo del usuario autenticado con sus posts y seguidores. */
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
                users.username,
                users.avatar_path,

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
            JOIN users ON users.id = posts.user_id
            WHERE posts.user_id = ?
            ORDER BY posts.created_at DESC
        ");

        $stmt->execute([$user['id'], $user['id']]);
        $posts = Storage::mapPosts($stmt->fetchAll());
        $posts = array_map(fn($post) => self::withPostAuthorAvatarUrl($post), $posts);

        Response::json([
            'user' => $user,
            'followers' => (int) $followers,
            'posts' => $posts
        ]);
    }

    /** Resumen para la pantalla de configuracion: datos del usuario, seguidores y conteo de posts. */
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

    /** Perfil publico de un usuario buscado por nombre de usuario. Incluye follow status del visitante. */
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
                users.username,
                users.avatar_path,

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
            JOIN users ON users.id = posts.user_id
            WHERE posts.user_id = ?
            ORDER BY posts.created_at DESC
        ");

        $postsStmt->execute([$viewer_id, $user_id]);
        $posts = Storage::mapPosts($postsStmt->fetchAll());
        $posts = array_map(fn($post) => self::withPostAuthorAvatarUrl($post), $posts);

        Response::json([
            'user' => $user,
            'followers' => (int) $followers,
            'is_following' => $isFollowing,
            'posts' => $posts
        ]);
    }

    /** Verifica si un nombre de usuario esta disponible para el usuario actual. */
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

    /** Perfil publico de un usuario por ID. Incluye seguidores, follow status y posts. */
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
            FROM users
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
                users.username,
                users.avatar_path,

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
            JOIN users ON users.id = posts.user_id
            WHERE posts.user_id = ?
            ORDER BY posts.created_at DESC
        ");

        $postsStmt->execute([$viewer_id, $user_id]);
        $posts = Storage::mapPosts($postsStmt->fetchAll());
        $posts = array_map(fn($post) => self::withPostAuthorAvatarUrl($post), $posts);

        Response::json([
            'user' => $user,
            'followers' => (int) $followers,
            'is_following' => $isFollowing,
            'posts' => $posts
        ]);
    }

    /**
     * Actualiza perfil del usuario (username, email, password).
     * Requiere contrasena actual para cambios sensibles.
     * Si cambia la contrasena, revoca otras sesiones.
     */
    public static function update() {

        $user = Middleware::auth();
        $data = json_decode(file_get_contents("php://input"), true);

        $username = trim($data['username'] ?? $user['username']);
        $email = trim($data['email'] ?? $user['email']);
        $password = trim($data['password'] ?? '');
        $currentPassword = trim($data['current_password'] ?? '');

        if ($username === '' || $email === '') {
            Response::json(['error' => 'Nombre de usuario y correo electrónico son obligatorios'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Correo electrónico inválido'], 400);
        }

        $existingEmail = User::findByEmail($email);
        if ($existingEmail && (int) $existingEmail['id'] !== (int) $user['id']) {
            Response::json(['error' => 'Correo electrónico ya registrado'], 400);
        }

        $existingUsername = User::findByUsername($username);
        if ($existingUsername && (int) $existingUsername['id'] !== (int) $user['id']) {
            Response::json(['error' => 'Nombre de usuario ya registrado'], 400);
        }

        $requiresCurrentPassword = $username !== $user['username']
            || $email !== $user['email']
            || $password !== '';

        if ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) {
            Response::json(['error' => 'La contraseña debe tener al menos 8 caracteres e incluir letras y números'], 400);
        }

        if ($requiresCurrentPassword) {
            if ($currentPassword === '') {
                Response::json(['error' => 'Debes confirmar tu contraseña actual para cambiar estos datos'], 400);
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

    /**
     * Actualiza el avatar del usuario.
     * Valida archivo (max 4MB, JPEG/PNG/WEBP), lo sube a Storage,
     * actualiza la BD y elimina el avatar anterior si existe.
     */
    public static function updateAvatar() {

        $user = Middleware::auth();

        if (!isset($_FILES['avatar'])) {
            Response::json(['error' => 'El avatar es obligatorio'], 400);
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

    /**
     * Inicia el proceso de cambio de email.
     * Valida contrasena actual, genera codigo de 6 digitos, lo envia al nuevo
     * email y guarda la solicitud pendiente. Rate-limited.
     */
    public static function startEmailChange() {

        RateLimiter::check('email_change_start_attempts', 5, 300);
        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();
        $data = self::jsonBody();

        $newEmail = trim((string) ($data['new_email'] ?? ''));
        $currentPassword = trim((string) ($data['current_password'] ?? ''));

        if ($newEmail === '') {
            Response::json(['error' => 'Debes introducir un nuevo correo electrónico'], 400);
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Correo electrónico inválido'], 400);
        }

        if ($newEmail === $user['email']) {
            Response::json(['error' => 'Introduce un correo electrónico distinto al actual'], 400);
        }

        if ($currentPassword === '') {
            Response::json(['error' => 'Debes introducir tu contraseña actual'], 400);
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            Response::json(['error' => 'Contraseña actual incorrecta'], 401);
        }

        $existingUser = User::findByEmail($newEmail);
        if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
            Response::json(['error' => 'Ese correo electrónico ya está en uso'], 409);
        }

        $existingPendingForEmail = PendingEmailChange::findByNewEmail($newEmail);
        if ($existingPendingForEmail && (int) $existingPendingForEmail['user_id'] !== (int) $user['id']) {
            Response::json(['error' => 'Ya hay una verificación pendiente para ese correo electrónico'], 409);
        }

        $pending = PendingEmailChange::findByUserId($user['id']);
        $verificationCode = self::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT, ['cost' => 12]);
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
            'message' => 'Código enviado al nuevo correo electrónico',
            'new_email' => $newEmail,
            'masked_email' => self::maskedEmail($newEmail),
            'resend_cooldown' => self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    /** Reenvia el codigo de verificacion de cambio de email con cooldown de 30s. */
    public static function resendEmailChange() {

        RateLimiter::check('email_change_resend_attempts', 5, 300);
        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();

        $pending = PendingEmailChange::findByUserId($user['id']);

        if (!$pending) {
            Response::json(['error' => 'No hay una verificación de correo electrónico pendiente'], 404);
        }

        $lastSent = strtotime($pending['last_sent_at']);
        if ($lastSent && (time() - $lastSent) < self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS) {
            $remaining = self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS - (time() - $lastSent);
            Response::json([
                'error' => 'Espera ' . $remaining . ' segundos antes de pedir otro código',
                'retry_after' => $remaining,
            ], 429);
        }

        $existingUser = User::findByEmail($pending['new_email']);
        if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'Ese correo electrónico ya está en uso'], 409);
        }

        $verificationCode = self::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT, ['cost' => 12]);
        $expiresAt = self::emailChangeExpiresAt();

        PendingEmailChange::updateRequest(
            $pending['id'],
            $pending['new_email'],
            $verificationCodeHash,
            $expiresAt
        );

        GmailMailer::sendEmailChangeCode($pending['new_email'], $user['username'], $verificationCode);

        Response::json([
            'message' => 'Hemos reenviado un nuevo código',
            'masked_email' => self::maskedEmail($pending['new_email']),
            'resend_cooldown' => self::EMAIL_CHANGE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Verifica el codigo de cambio de email.
     * Maximo 5 intentos. Si es correcto, actualiza el email en la BD
     * y elimina la solicitud pendiente.
     */
    public static function verifyEmailChange() {

        RateLimiter::check('email_change_verify_attempts', 10, 300);
        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();
        $data = self::jsonBody();

        $code = trim((string) ($data['code'] ?? ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            Response::json(['error' => 'El código debe tener 6 dígitos'], 400);
        }

        $pending = PendingEmailChange::findByUserId($user['id']);

        if (!$pending) {
            Response::json(['error' => 'La verificación pendiente no existe o ya ha caducado'], 404);
        }

        if (strtotime($pending['verification_expires_at']) < time()) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'El código ha caducado. Solicita uno nuevo'], 410);
        }

        if ((int) $pending['verification_attempts'] >= self::EMAIL_CHANGE_MAX_ATTEMPTS) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'Se ha superado el número máximo de intentos. Solicita un nuevo código'], 429);
        }

        if (!password_verify($code, $pending['verification_code_hash'])) {
            PendingEmailChange::incrementAttempts($pending['id']);

            if (((int) $pending['verification_attempts']) + 1 >= self::EMAIL_CHANGE_MAX_ATTEMPTS) {
                PendingEmailChange::deleteById($pending['id']);
                Response::json(['error' => 'Código incorrecto demasiadas veces. Vuelve a solicitar el cambio de correo electrónico'], 429);
            }

            Response::json(['error' => 'Código incorrecto'], 400);
        }

        $existingUser = User::findByEmail($pending['new_email']);
        if ($existingUser && (int) $existingUser['id'] !== (int) $user['id']) {
            PendingEmailChange::deleteById($pending['id']);
            Response::json(['error' => 'Ese correo electrónico ya está en uso'], 409);
        }

        User::update($user['id'], $user['username'], $pending['new_email']);
        PendingEmailChange::deleteById($pending['id']);

        Response::json([
            'message' => 'Correo electrónico actualizado correctamente',
            'email' => $pending['new_email'],
        ]);
    }

    /** Cancela una solicitud de cambio de email pendiente. */
    public static function cancelEmailChange() {

        $user = Middleware::auth();
        PendingEmailChange::purgeExpired();
        PendingEmailChange::deleteByUserId($user['id']);

        Response::json(['success' => true]);
    }

    /**
     * Elimina la cuenta del usuario con todos sus datos.
     * Requiere contrasena actual y confirmacion textual.
     * Borra en cascada: comentarios (CTE recursivo), likes, notificaciones,
     * follows, post_tags, posts, archivos de storage, tokens, solicitudes
     * pendientes, tags huerfanos y finalmente el usuario.
     */
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
                DELETE FROM users
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
