<?php
/**
 * Utilidades compartidas para servicios de autenticacion.
 * Validacion de datos, generacion de tokens/codigos y helpers de fechas.
 */
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../core/Response.php';

class AuthSupport {
    /** Tiempo de vida del codigo de verificacion en segundos (10 min). */
    public const VERIFICATION_CODE_TTL = 600;
    /** Maximo de intentos de verificacion permitidos. */
    public const VERIFICATION_CODE_MAX_ATTEMPTS = 5;
    /** Tiempo de espera entre reenvios de codigo (30s). */
    public const CODE_RESEND_COOLDOWN_SECONDS = 30;
    /** Duracion del bloqueo por demasiados intentos fallidos (15 min). */
    public const PASSWORD_RESET_LOCK_SECONDS = 900;

    /** Lee y parsea el body JSON. Responde 400 si es invalido. */
    public static function jsonBody() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON inválido'], 400);
        }

        return $data;
    }

    /**
     * Valida datos de registro: username (3-24 chars, alfanumerico + ._-),
     * email valido, password (8+ chars con letras y numeros), confirmacion.
     */
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

    /** Valida nueva contrasena y su confirmacion (8+ chars, letras y numeros). */
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

    /** Genera un token de flujo aleatorio de 64 caracteres hex. */
    public static function generateFlowToken() {
        return bin2hex(random_bytes(32));
    }

    /** Genera un codigo de verificacion de 6 digitos. */
    public static function generateVerificationCode() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Fecha actual + tiempo de vida del codigo (10 min). */
    public static function expiresAt() {
        return date('Y-m-d H:i:s', time() + self::VERIFICATION_CODE_TTL);
    }

    /** Fecha de desbloqueo tras exceder intentos (actual + 15 min). */
    public static function passwordResetLockUntil() {
        return date('Y-m-d H:i:s', time() + self::PASSWORD_RESET_LOCK_SECONDS);
    }

    /** Ofusca parcialmente un email para mostrarlo en la interfaz. */
    public static function maskedEmail($email) {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($localPart === '' || $domain === '') {
            return $email;
        }

        $visible = substr($localPart, 0, min(2, strlen($localPart)));
        return $visible . str_repeat('*', max(2, strlen($localPart) - strlen($visible))) . '@' . $domain;
    }

    /** Devuelve los segundos restantes hasta una fecha determinada. */
    public static function secondsUntil($dateTime) {
        $timestamp = strtotime((string) $dateTime);

        if (!$timestamp) {
            return 0;
        }

        return max(0, $timestamp - time());
    }

    /** Verifica que email y username no esten registrados. Responde 400 si existen. */
    public static function assertEmailAndUsernameAvailable($email, $username) {
        if (User::findByEmail($email)) {
            Response::json(['error' => 'Correo electrónico ya registrado'], 400);
        }

        if (User::findByUsername($username)) {
            Response::json(['error' => 'Nombre de usuario ya registrado'], 400);
        }
    }
}
