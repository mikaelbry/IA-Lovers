<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth {
    private const TOKEN_TTL_DAYS = 90;

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

        self::cleanupExpiredTokens($pdo);

        $stmt = $pdo->prepare("
            SELECT usuarios.*, user_tokens.token
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

        self::renewToken($token);

        return $user;
    }

    public static function hasAuthorizationHeader() {
        return self::authorizationHeader() !== null;
    }

    public static function issueToken($userId) {
        $token = bin2hex(random_bytes(32));
        $pdo = Database::getConnection();
        $expiresAt = self::expiresAt();

        self::cleanupExpiredTokens($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO user_tokens (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $token,
            $expiresAt,
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_in_days' => self::TOKEN_TTL_DAYS,
        ];
    }

    public static function tokenTtlDays() {
        return self::TOKEN_TTL_DAYS;
    }

    public static function revokeToken($token) {
        if (!is_string($token) || trim($token) === '') {
            return;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            DELETE FROM user_tokens
            WHERE token = ?
        ");

        $stmt->execute([$token]);
    }

    public static function revokeOtherTokens($userId, $currentToken = null) {
        $pdo = Database::getConnection();

        if (is_string($currentToken) && trim($currentToken) !== '') {
            $stmt = $pdo->prepare("
                DELETE FROM user_tokens
                WHERE user_id = ?
                AND token <> ?
            ");

            $stmt->execute([$userId, $currentToken]);
            return;
        }

        $stmt = $pdo->prepare("
            DELETE FROM user_tokens
            WHERE user_id = ?
        ");

        $stmt->execute([$userId]);
    }

    private static function renewToken($token) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE user_tokens
            SET expires_at = ?
            WHERE token = ?
        ");

        $stmt->execute([
            self::expiresAt(),
            $token,
        ]);
    }

    private static function cleanupExpiredTokens($pdo) {
        $pdo->exec("DELETE FROM user_tokens WHERE expires_at < CURRENT_TIMESTAMP");
    }

    private static function expiresAt() {
        return gmdate('Y-m-d H:i:s', time() + (self::TOKEN_TTL_DAYS * 86400));
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
