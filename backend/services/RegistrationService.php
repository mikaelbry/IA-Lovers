<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PendingRegistration.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Altcha.php';
require_once __DIR__ . '/AuthSupport.php';
require_once __DIR__ . '/GmailMailer.php';

class RegistrationService {

    public static function register() {
        self::startRegistration();
    }

    public static function startRegistration() {
        self::startRegistrationFlow(true);
    }

    public static function mobileStartRegistration() {
        self::startRegistrationFlow(false);
    }

    public static function verifyRegistration() {
        RateLimiter::check('register_verify_attempts', 10, 300);
        PendingRegistration::purgeExpired();

        $data = AuthSupport::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

        if ($flowToken === '' || $code === '') {
            Response::json(['error' => 'Código y flujo de verificación obligatorios'], 400);
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            Response::json(['error' => 'El código debe tener 6 dígitos'], 400);
        }

        $pending = PendingRegistration::findByFlowToken($flowToken);

        if (!$pending) {
            Response::json(['error' => 'El registro pendiente no existe o ya ha caducado'], 404);
        }

        if (strtotime($pending['verification_expires_at']) < time()) {
            PendingRegistration::deleteById($pending['id']);
            Response::json(['error' => 'El código ha caducado. Vuelve a registrarte para recibir uno nuevo'], 410);
        }

        if ((int) $pending['verification_attempts'] >= AuthSupport::VERIFICATION_CODE_MAX_ATTEMPTS) {
            PendingRegistration::deleteById($pending['id']);
            Response::json(['error' => 'Se ha superado el número máximo de intentos. Empieza el registro de nuevo'], 429);
        }

        if (!password_verify($code, $pending['verification_code_hash'])) {
            PendingRegistration::incrementAttempts($pending['id']);

            if (((int) $pending['verification_attempts']) + 1 >= AuthSupport::VERIFICATION_CODE_MAX_ATTEMPTS) {
                PendingRegistration::deleteById($pending['id']);
                Response::json(['error' => 'Código incorrecto demasiadas veces. El registro pendiente ha sido cancelado'], 429);
            }

            Response::json(['error' => 'Código incorrecto'], 400);
        }

        AuthSupport::assertEmailAndUsernameAvailable($pending['email'], $pending['username']);

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

    public static function mobileVerifyRegistration() {
        self::verifyRegistration();
    }

    public static function resendRegistrationCode() {
        RateLimiter::check('register_resend_attempts', 5, 300);
        PendingRegistration::purgeExpired();

        $data = AuthSupport::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));

        if ($flowToken === '') {
            Response::json(['error' => 'Falta el flujo de registro pendiente'], 400);
        }

        $pending = PendingRegistration::findByFlowToken($flowToken);

        if (!$pending) {
            Response::json(['error' => 'El registro pendiente no existe o ya ha caducado'], 404);
        }

        $lastSent = strtotime($pending['last_sent_at']);
        if ($lastSent && (time() - $lastSent) < AuthSupport::CODE_RESEND_COOLDOWN_SECONDS) {
            $remaining = AuthSupport::CODE_RESEND_COOLDOWN_SECONDS - (time() - $lastSent);
            Response::json([
                'error' => 'Espera ' . $remaining . ' segundos antes de pedir otro código',
                'retry_after' => $remaining,
            ], 429);
        }

        AuthSupport::assertEmailAndUsernameAvailable($pending['email'], $pending['username']);

        $verificationCode = AuthSupport::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT, ['cost' => 12]);
        $expiresAt = AuthSupport::expiresAt();

        PendingRegistration::updateCode($pending['id'], $pending['flow_token'], $verificationCodeHash, $expiresAt);
        GmailMailer::sendRegistrationCode($pending['email'], $pending['username'], $verificationCode);

        Response::json([
            'message' => 'Hemos reenviado un nuevo código',
            'flow_token' => $pending['flow_token'],
            'masked_email' => AuthSupport::maskedEmail($pending['email']),
            'resend_cooldown' => AuthSupport::CODE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public static function mobileResendRegistrationCode() {
        self::resendRegistrationCode();
    }

    public static function cancelPendingRegistration() {
        PendingRegistration::purgeExpired();

        $data = AuthSupport::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));

        if ($flowToken !== '') {
            PendingRegistration::deleteByFlowToken($flowToken);
        }

        Response::json(['success' => true]);
    }

    public static function mobileCancelPendingRegistration() {
        self::cancelPendingRegistration();
    }

    private static function startRegistrationFlow($requireAltcha) {
        RateLimiter::check('register_attempts', 5, 300);
        PendingRegistration::purgeExpired();

        $data = AuthSupport::jsonBody();

        if ($requireAltcha) {
            Altcha::verifyOrFail($data['altcha'] ?? '');
        }

        [$username, $email, $password] = AuthSupport::validateRegistrationData($data);
        AuthSupport::assertEmailAndUsernameAvailable($email, $username);

        $pendingByEmail = PendingRegistration::findByEmail($email);
        $pendingByUsername = PendingRegistration::findByUsername($username);

        if ($pendingByUsername && (!$pendingByEmail || (int) $pendingByUsername['id'] !== (int) $pendingByEmail['id'])) {
            Response::json(['error' => 'Ya hay un registro pendiente con ese nombre de usuario'], 409);
        }

        $flowToken = $pendingByEmail['flow_token'] ?? AuthSupport::generateFlowToken();
        $verificationCode = AuthSupport::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT, ['cost' => 12]);
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $expiresAt = AuthSupport::expiresAt();
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
            'message' => 'Código enviado al correo indicado',
            'flow_token' => $flowToken,
            'email' => $email,
            'masked_email' => AuthSupport::maskedEmail($email),
            'resend_cooldown' => AuthSupport::CODE_RESEND_COOLDOWN_SECONDS,
        ]);
    }
}
