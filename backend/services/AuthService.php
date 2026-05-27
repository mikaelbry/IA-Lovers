<?php
/**
 * Logica de negocio de autenticacion: login, session y logout.
 */
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/Altcha.php';
require_once __DIR__ . '/AuthSupport.php';
require_once __DIR__ . '/Storage.php';

class AuthService {

    /** Verifica el token y devuelve los datos del usuario autenticado. */
    public static function session() {
        $user = Middleware::auth();

        Response::json([
            'authenticated' => true,
            'expires_in_days' => Auth::tokenTtlDays(),
            'user' => self::authUserPayload($user),
        ]);
    }

    /** Revoca el token actual del usuario y cierra sesion. */
    public static function logout() {
        $user = Middleware::auth();
        Auth::revokeToken($user['token'] ?? null);

        Response::json(['success' => true]);
    }

    /** Inicia sesion con verificacion CAPTCHA. */
    public static function login() {
        self::loginFlow(true);
    }

    /** Inicia sesion desde mobile (sin CAPTCHA). */
    public static function mobileLogin() {
        self::loginFlow(false);
    }

    /**
     * Flujo comun de login: rate limiting, verifica CAPTCHA (opcional),
     * busca usuario por email, verifica password, emite token.
     */
    private static function loginFlow($requireAltcha) {
        RateLimiter::check('login_attempts', 8, 300);

        $data = AuthSupport::jsonBody();

        if ($requireAltcha) {
            Altcha::verifyOrFail($data['altcha'] ?? '');
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json(['error' => 'Credenciales incorrectas'], 401);
        }

        Response::json(self::authResponsePayload($user));
    }

    /** Genera payload con token, expiracion y datos del usuario. */
    private static function authResponsePayload($user) {
        $session = Auth::issueToken($user['id']);

        return [
            'token' => $session['token'],
            'expires_at' => $session['expires_at'],
            'expires_in_days' => $session['expires_in_days'],
            'user' => self::authUserPayload($user),
        ];
    }

    /** Extrae datos publicos del usuario (id, username, avatar). */
    private static function authUserPayload($user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'avatar_url' => !empty($user['avatar_path'])
                ? Storage::publicUrl($user['id'], $user['avatar_path'])
                : null,
        ];
    }
}
