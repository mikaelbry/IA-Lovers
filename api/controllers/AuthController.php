<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/RateLimiter.php';
require_once __DIR__ . '/../core/Storage.php';
require_once __DIR__ . '/../core/Altcha.php';
require_once __DIR__ . '/../config/database.php';

class AuthController {

    private static function authUserPayload($user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'avatar_url' => !empty($user['avatar_path'])
                ? Storage::publicUrl($user['id'], $user['avatar_path'])
                : null,
        ];
    }

    public static function register() {
        RateLimiter::check('register_attempts', 5, 300);

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON invalido'], 400);
        }

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            Response::json(['error' => 'Nombre de usuario, correo y contrasena son obligatorios'], 400);
        }

        Altcha::verifyOrFail($data['altcha'] ?? '');

        $username = trim((string) $data['username']);
        $email = trim((string) $data['email']);
        $password = (string) $data['password'];
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,24}$/', $username)) {
            Response::json(['error' => 'El nombre de usuario debe tener entre 3 y 24 caracteres y solo puede usar letras, numeros, punto, guion y guion bajo'], 400);
        }

        if (User::findByUsername($username)) {
            Response::json(['error' => 'Nombre de usuario ya registrado'], 400);
        }

        if (User::findByEmail($email)) {
            Response::json(['error' => 'Email ya registrado'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Email invalido'], 400);
        }

        if ($password !== $passwordConfirmation) {
            Response::json(['error' => 'Las contrasenas no coinciden'], 400);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            Response::json(['error' => 'La contrasena debe tener al menos 8 caracteres e incluir letras y numeros'], 400);
        }

        User::create($username, $email, $password);

        Response::json(['message' => 'Usuario creado correctamente']);
    }

    public static function login() {
        RateLimiter::check('login_attempts', 8, 300);

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON invalido'], 400);
        }

        Altcha::verifyOrFail($data['altcha'] ?? '');

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json(['error' => 'Credenciales incorrectas'], 401);
        }

        $token = bin2hex(random_bytes(32));

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO user_tokens (user_id, token, expires_at)
            VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '7 days')
        ");

        $stmt->execute([$user['id'], $token]);

        Response::json([
            'token' => $token,
            'user' => self::authUserPayload($user)
        ]);
    }
}
