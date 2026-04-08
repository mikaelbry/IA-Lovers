<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PendingRegistration.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/RateLimiter.php';
require_once __DIR__ . '/../core/Storage.php';
require_once __DIR__ . '/../core/Altcha.php';
require_once __DIR__ . '/../core/GmailMailer.php';
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private const VERIFICATION_CODE_TTL = 600;
    private const VERIFICATION_CODE_MAX_ATTEMPTS = 5;
    private const CODE_RESEND_COOLDOWN_SECONDS = 30;

    private static function authUserPayload($user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'avatar_url' => !empty($user['avatar_path'])
                ? Storage::publicUrl($user['id'], $user['avatar_path'])
                : null,
        ];
    }

    private static function jsonBody() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!is_array($data)) {
            Response::json(['error' => 'JSON invalido'], 400);
        }

        return $data;
    }

    private static function validateRegistrationData($data) {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            Response::json(['error' => 'Nombre de usuario, correo y contraseña son obligatorios'], 400);
        }

        $username = trim((string) $data['username']);
        $email = trim((string) $data['email']);
        $password = (string) $data['password'];
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,24}$/', $username)) {
            Response::json(['error' => 'El nombre de usuario debe tener entre 3 y 24 caracteres y solo puede usar letras, numeros, punto, guion y guion bajo'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Email invalido'], 400);
        }

        if ($password !== $passwordConfirmation) {
            Response::json(['error' => 'Las contraseñas no coinciden'], 400);
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            Response::json(['error' => 'La contraseña debe tener al menos 8 caracteres e incluir letras y numeros'], 400);
        }

        return [$username, $email, $password];
    }

    private static function generateFlowToken() {
        return bin2hex(random_bytes(32));
    }

    private static function generateVerificationCode() {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private static function expiresAt() {
        return date('Y-m-d H:i:s', time() + self::VERIFICATION_CODE_TTL);
    }

    private static function maskedEmail($email) {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($localPart === '' || $domain === '') {
            return $email;
        }

        $visible = substr($localPart, 0, min(2, strlen($localPart)));
        return $visible . str_repeat('*', max(2, strlen($localPart) - strlen($visible))) . '@' . $domain;
    }

    private static function assertEmailAndUsernameAvailable($email, $username) {
        if (User::findByEmail($email)) {
            Response::json(['error' => 'Email ya registrado'], 400);
        }

        if (User::findByUsername($username)) {
            Response::json(['error' => 'Nombre de usuario ya registrado'], 400);
        }
    }

    public static function register() {
        self::startRegistration();
    }

    public static function startRegistration() {
        RateLimiter::check('register_attempts', 5, 300);
        PendingRegistration::purgeExpired();

        $data = self::jsonBody();
        Altcha::verifyOrFail($data['altcha'] ?? '');

        [$username, $email, $password] = self::validateRegistrationData($data);
        self::assertEmailAndUsernameAvailable($email, $username);

        $pendingByEmail = PendingRegistration::findByEmail($email);
        $pendingByUsername = PendingRegistration::findByUsername($username);

        if ($pendingByUsername && (!$pendingByEmail || (int) $pendingByUsername['id'] !== (int) $pendingByEmail['id'])) {
            Response::json(['error' => 'Ya hay un registro pendiente con ese nombre de usuario'], 409);
        }

        $flowToken = $pendingByEmail['flow_token'] ?? self::generateFlowToken();
        $verificationCode = self::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $expiresAt = self::expiresAt();
        $pendingId = null;
        $created = false;

        if ($pendingByEmail) {
            PendingRegistration::updateFlow(
                $pendingByEmail['id'],
                $username,
                $passwordHash,
                $flowToken,
                $verificationCodeHash,
                $expiresAt
            );
            $pendingId = (int) $pendingByEmail['id'];
        } else {
            PendingRegistration::create(
                $username,
                $email,
                $passwordHash,
                $flowToken,
                $verificationCodeHash,
                $expiresAt
            );
            $created = true;

            $pending = PendingRegistration::findByFlowToken($flowToken);
            $pendingId = $pending ? (int) $pending['id'] : null;
        }

        try {
            GmailMailer::sendRegistrationCode($email, $username, $verificationCode);
        } catch (Throwable $e) {
            if ($created && $pendingId) {
                PendingRegistration::deleteById($pendingId);
            }

            throw $e;
        }

        Response::json([
            'message' => 'Codigo enviado al correo indicado',
            'flow_token' => $flowToken,
            'email' => $email,
            'masked_email' => self::maskedEmail($email),
            'resend_cooldown' => self::CODE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public static function verifyRegistration() {
        RateLimiter::check('register_verify_attempts', 10, 300);
        PendingRegistration::purgeExpired();

        $data = self::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

        if ($flowToken === '' || $code === '') {
            Response::json(['error' => 'Codigo y flujo de verificacion obligatorios'], 400);
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            Response::json(['error' => 'El codigo debe tener 6 digitos'], 400);
        }

        $pending = PendingRegistration::findByFlowToken($flowToken);

        if (!$pending) {
            Response::json(['error' => 'El registro pendiente no existe o ya ha caducado'], 404);
        }

        if (strtotime($pending['verification_expires_at']) < time()) {
            PendingRegistration::deleteById($pending['id']);
            Response::json(['error' => 'El codigo ha caducado. Vuelve a registrarte para recibir uno nuevo'], 410);
        }

        if ((int) $pending['verification_attempts'] >= self::VERIFICATION_CODE_MAX_ATTEMPTS) {
            PendingRegistration::deleteById($pending['id']);
            Response::json(['error' => 'Se ha superado el numero maximo de intentos. Empieza el registro de nuevo'], 429);
        }

        if (!password_verify($code, $pending['verification_code_hash'])) {
            PendingRegistration::incrementAttempts($pending['id']);

            if (((int) $pending['verification_attempts']) + 1 >= self::VERIFICATION_CODE_MAX_ATTEMPTS) {
                PendingRegistration::deleteById($pending['id']);
                Response::json(['error' => 'Codigo incorrecto demasiadas veces. El registro pendiente ha sido cancelado'], 429);
            }

            Response::json(['error' => 'Codigo incorrecto'], 400);
        }

        self::assertEmailAndUsernameAvailable($pending['email'], $pending['username']);

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            User::createWithPasswordHash($pending['username'], $pending['email'], $pending['password_hash']);
            PendingRegistration::deleteById($pending['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json([
            'message' => 'Correo verificado y cuenta creada correctamente',
            'email' => $pending['email'],
        ]);
    }

    public static function resendRegistrationCode() {
        RateLimiter::check('register_resend_attempts', 5, 300);
        PendingRegistration::purgeExpired();

        $data = self::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));

        if ($flowToken === '') {
            Response::json(['error' => 'Falta el flujo de registro pendiente'], 400);
        }

        $pending = PendingRegistration::findByFlowToken($flowToken);

        if (!$pending) {
            Response::json(['error' => 'El registro pendiente no existe o ya ha caducado'], 404);
        }

        $lastSent = strtotime($pending['last_sent_at']);
        if ($lastSent && (time() - $lastSent) < self::CODE_RESEND_COOLDOWN_SECONDS) {
            $remaining = self::CODE_RESEND_COOLDOWN_SECONDS - (time() - $lastSent);
            Response::json([
                'error' => 'Espera ' . $remaining . ' segundos antes de pedir otro codigo',
                'retry_after' => $remaining,
            ], 429);
        }

        self::assertEmailAndUsernameAvailable($pending['email'], $pending['username']);

        $verificationCode = self::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
        $expiresAt = self::expiresAt();

        PendingRegistration::updateCode($pending['id'], $pending['flow_token'], $verificationCodeHash, $expiresAt);
        GmailMailer::sendRegistrationCode($pending['email'], $pending['username'], $verificationCode);

        Response::json([
            'message' => 'Hemos reenviado un nuevo codigo',
            'flow_token' => $pending['flow_token'],
            'masked_email' => self::maskedEmail($pending['email']),
            'resend_cooldown' => self::CODE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public static function cancelPendingRegistration() {
        PendingRegistration::purgeExpired();

        $data = self::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));

        if ($flowToken !== '') {
            PendingRegistration::deleteByFlowToken($flowToken);
        }

        Response::json(['success' => true]);
    }

    public static function login() {
        RateLimiter::check('login_attempts', 8, 300);

        $data = self::jsonBody();
        Altcha::verifyOrFail($data['altcha'] ?? '');

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json(['error' => 'Credenciales incorrectas'], 401);
        }

        $token = bin2hex(random_bytes(32));
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO user_tokens (user_id, token, expires_at)
            VALUES (?, ?, CURRENT_TIMESTAMP + INTERVAL '7 days')
        ");

        $stmt->execute([$user['id'], $token]);

        Response::json([
            'token' => $token,
            'user' => self::authUserPayload($user)
        ]);
    }
}
