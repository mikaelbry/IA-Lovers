<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';

class AuthController {

    public static function register() {

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (!$data) {
            Response::json(['error' => 'JSON inválido'], 400);
        }

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            Response::json(['error' => 'Campos obligatorios'], 400);
        }

        if (User::findByEmail($data['email'])) {
            Response::json(['error' => 'Email ya registrado'], 400);
        }

        User::create($data['username'], $data['email'], $data['password']);

        Response::json(['message' => 'Usuario creado correctamente']);
    }

    public static function login() {

        $input = file_get_contents("php://input");
        $data = json_decode($input, true);

        if (!$data) {
            Response::json(['error' => 'JSON inválido'], 400);
        }

        $user = User::findByEmail($data['email'] ?? '');

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            Response::json(['error' => 'Credenciales incorrectas'], 401);
        }

        $token = bin2hex(random_bytes(32));

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO user_tokens (user_id, token, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");

        $stmt->execute([$user['id'], $token]);

        Response::json([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ]);
    }
}
