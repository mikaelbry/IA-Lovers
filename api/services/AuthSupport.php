<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Response.php';

class AuthSupport {
    public const VERIFICATION_CODE_TTL = 600;
    public const VERIFICATION_CODE_MAX_ATTEMPTS = 5;
    public const CODE_RESEND_COOLDOWN_SECONDS = 30;
    public const PASSWORD_RESET_LOCK_SECONDS = 900;

    public static function jsonBody() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON inválido'], 400);
        }

        return $data;
    }

    public static function validateRegistrationData($data) {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            Response::json(['error' => 'Nombre de usuario, correo electrónico y contraseña son obligatorios'], 400);
        }

        $username = trim((string) $data['username']);
        $email = trim((string) $data['email']);
        $password = (string) $data['password'];
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,24}$/', $username)) {
            Response::json(['error' => 'El nombre de usuario debe tener entre 3 y 24 caracteres y solo puede usar letras, números, punto, guion y guion bajo'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Correo electrónico inválido'], 400);
        }

        if ($password !== $passwordConfirmation) {
            Response::json(['error' => 'Las contraseñas no coinciden'], 400);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            Response::json(['error' => 'La contraseña debe tener al menos 8 caracteres e incluir letras y números'], 400);
        }

        return [$username, $email, $password];
    }

    public static function validateNewPassword($password, $passwordConfirmation) {
        if ($password === '' || $passwordConfirmation === '') {
            Response::json(['error' => 'La nueva contraseña y su confirmación son obligatorias'], 400);
        }

        if ($password !== $passwordConfirmation) {
            Response::json(['error' => 'Las contraseñas no coinciden'], 400);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            Response::json(['error' => 'La contraseña debe tener al menos 8 caracteres e incluir letras y números'], 400);
        }
    }

    public static function generateFlowToken() {
        return bin2hex(random_bytes(32));
    }

    public static function generateVerificationCode() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function expiresAt() {
        return date('Y-m-d H:i:s', time() + self::VERIFICATION_CODE_TTL);
    }

    public static function passwordResetLockUntil() {
        return date('Y-m-d H:i:s', time() + self::PASSWORD_RESET_LOCK_SECONDS);
    }

    public static function maskedEmail($email) {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($localPart === '' || $domain === '') {
            return $email;
        }

        $visible = substr($localPart, 0, min(2, strlen($localPart)));
        return $visible . str_repeat('*', max(2, strlen($localPart) - strlen($visible))) . '@' . $domain;
    }

    public static function secondsUntil($dateTime) {
        $timestamp = strtotime((string) $dateTime);

        if (!$timestamp) {
            return 0;
        }

        return max(0, $timestamp - time());
    }

    public static function assertEmailAndUsernameAvailable($email, $username) {
        if (User::findByEmail($email)) {
            Response::json(['error' => 'Correo electrónico ya registrado'], 400);
        }

        if (User::findByUsername($username)) {
            Response::json(['error' => 'Nombre de usuario ya registrado'], 400);
        }
    }
}
