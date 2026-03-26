<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth {

    public static function user() {
        $authHeader = self::authorizationHeader();

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

    public static function hasAuthorizationHeader() {
        return self::authorizationHeader() !== null;
    }

    private static function authorizationHeader() {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return $value;
                }
            }
        }

        $serverKeys = [
            'HTTP_AUTHORIZATION',
            'Authorization',
            'REDIRECT_HTTP_AUTHORIZATION',
        ];

        foreach ($serverKeys as $key) {
            if (!empty($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }

        return null;
    }
}
