<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth {

    public static function user() {
    
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            Response::json(['error' => 'Token requerido'], 401);
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);

        $pdo = Database::getConnection();
        $pdo->exec("DELETE FROM user_tokens WHERE expires_at < NOW()");    
        $stmt = $pdo->prepare("
            SELECT usuarios.*
            FROM user_tokens
            JOIN usuarios ON usuarios.id = user_tokens.user_id
            WHERE user_tokens.token = ?
            AND user_tokens.expires_at > NOW()
        ");

        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::json(['error' => 'Token inválido'], 401);
        }

        return $user;
    }
}
