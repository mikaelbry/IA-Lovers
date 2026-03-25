<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth {

    public static function user() {

        $headers = getallheaders();
        $authHeader = null;

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if (!$authHeader) {
            Response::json(['error' => 'Login requerido'], 401);
        }

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::json(['error' => 'Token inválido'], 401);
        }

        $token = $matches[1];

        $pdo = Database::getConnection();

        $pdo->exec("DELETE FROM user_tokens WHERE expires_at < CURRENT_TIMESTAMP");

        $stmt = $pdo->prepare("
            SELECT usuarios.*
            FROM user_tokens
            JOIN usuarios ON usuarios.id = user_tokens.user_id
            WHERE user_tokens.token = ?
            AND user_tokens.expires_at > CURRENT_TIMESTAMP
        ");

        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['error' => 'Token inválido'], 401);
        }

        return $user;
    }
}
