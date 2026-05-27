<?php
/**
 * Autenticacion mediante tokens Bearer.
 * Los tokens se almacenan en user_tokens con hash SHA-256,
 * expiran a los 90 dias y se renuevan en cada uso.
 */
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Response.php';

class Auth {
    /** Duracion del token en dias. */
    private const TOKEN_TTL_DAYS = 90;

    /**
     * Obtiene el usuario autenticado desde el header Authorization: Bearer.
     * Limpia tokens expirados, busca el token en BD, lo renueva y devuelve
     * los datos del usuario. Responde 401 si no es valido.
     */
    public static function user() {
        $authHeader = self::authorizationHeader();

        if (!$authHeader) {
            Response::json(['error' => 'Inicio de sesión requerido'], 401);
        }

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::json(['error' => 'Token inválido'], 401);
        }

        $token = $matches[1];

        $pdo = Database::getConnection();

        self::cleanupExpiredTokens($pdo);

        $stmt = $pdo->prepare("
            SELECT users.*, user_tokens.token
            FROM user_tokens
            JOIN users ON users.id = user_tokens.user_id
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

    /** Verifica si la peticion incluye header Authorization (sin validarlo). */
    public static function hasAuthorizationHeader() {
        return self::authorizationHeader() !== null;
    }

    /**
     * Genera un token aleatorio de 64 caracteres hex, lo inserta en user_tokens
     * con fecha de expiracion y devuelve el payload del token.
     */
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

    /** Devuelve los dias de vida del token (90). */
    public static function tokenTtlDays() {
        return self::TOKEN_TTL_DAYS;
    }

    /** Elimina un token especifico de la base de datos. */
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

    /** Revoca todos los tokens de un usuario excepto el actual (opcional). */
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

    /** Renueva la expiracion del token a +90 dias desde ahora. */
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

    /** Elimina todos los tokens cuya fecha de expiracion ya pasó. */
    private static function cleanupExpiredTokens($pdo) {
        $pdo->exec("DELETE FROM user_tokens WHERE expires_at < CURRENT_TIMESTAMP");
    }

    /** Calcula la fecha UTC de expiracion (ahora + 90 dias). */
    private static function expiresAt() {
        return gmdate('Y-m-d H:i:s', time() + (self::TOKEN_TTL_DAYS * 86400));
    }

    /** Busca el header Authorization en getallheaders(), $_SERVER o REDIRECT_HTTP_AUTHORIZATION. */
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
