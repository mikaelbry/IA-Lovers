<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/Altcha.php';
require_once __DIR__ . '/AuthSupport.php';
require_once __DIR__ . '/Storage.php';

class AuthService {

    public static function session() {
        $user = Middleware::auth();

        Response::json([
            'authenticated' => true,
            'expires_in_days' => Auth::tokenTtlDays(),
            'user' => self::authUserPayload($user),
        ]);
    }

    public static function logout() {
        $user = Middleware::auth();
        Auth::revokeToken($user['token'] ?? null);

        Response::json(['success' => true]);
    }

    public static function login() {
        self::loginFlow(true);
    }

    public static function mobileLogin() {
        self::loginFlow(false);
    }

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

    private static function authResponsePayload($user) {
        $session = Auth::issueToken($user['id']);

        return [
            'token' => $session['token'],
            'expires_at' => $session['expires_at'],
            'expires_in_days' => $session['expires_in_days'],
            'user' => self::authUserPayload($user),
        ];
    }

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
