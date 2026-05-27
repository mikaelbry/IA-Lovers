<?php
/**
 * Logica de negocio para el flujo de restablecimiento de contrasena.
 * Incluye inicio, reenvio de codigo, verificacion y cancelacion.
 * Versiones para web (con CAPTCHA) y mobile (sin CAPTCHA).
 */
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PendingPasswordReset.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Altcha.php';
require_once __DIR__ . '/AuthSupport.php';
require_once __DIR__ . '/GmailMailer.php';

class PasswordResetService {

    /** Inicia restablecimiento web (con CAPTCHA). */
    public static function startPasswordReset() {
        self::startPasswordResetFlow(true);
    }

    /** Inicia restablecimiento mobile (sin CAPTCHA). */
    public static function mobileStartPasswordReset() {
        self::startPasswordResetFlow(false);
    }

    /** Reenvia el codigo de restablecimiento. Rate-limited. Verifica bloqueos y cooldown. */
    public static function resendPasswordResetCode() {
        RateLimiter::check('password_reset_resend_attempts', 5, 300);
        PendingPasswordReset::purgeExpired();

        $data = AuthSupport::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));

        if ($flowToken === '') {
            Response::json(['error' => 'Falta el flujo de recuperación pendiente'], 400);
        }

        $pending = PendingPasswordReset::findByFlowToken($flowToken);

        if (!$pending) {
            Response::json([
                'message' => 'Hemos reenviado un nuevo código',
                'flow_token' => AuthSupport::generateFlowToken(),
                'masked_email' => 'tu correo',
                'resend_cooldown' => AuthSupport::CODE_RESEND_COOLDOWN_SECONDS,
            ]);
        }

        if (!empty($pending['locked_until'])) {
            $retryAfter = AuthSupport::secondsUntil($pending['locked_until']);

            if ($retryAfter > 0) {
                Response::json([
                    'error' => 'Demasiados intentos. Espera antes de pedir otro código',
                    'retry_after' => $retryAfter,
                    'locked' => true,
                ], 429);
            }
        }

        $lastSent = strtotime($pending['last_sent_at']);
        if ($lastSent && (time() - $lastSent) < AuthSupport::CODE_RESEND_COOLDOWN_SECONDS) {
            $remaining = AuthSupport::CODE_RESEND_COOLDOWN_SECONDS - (time() - $lastSent);
            Response::json([
                'error' => 'Espera antes de pedir otro código',
                'retry_after' => $remaining,
            ], 429);
        }

        $newFlowToken = AuthSupport::generateFlowToken();
        $verificationCode = AuthSupport::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT, ['cost' => 12]);
        $expiresAt = AuthSupport::expiresAt();

        PendingPasswordReset::updateRequest($pending['id'], $newFlowToken, $verificationCodeHash, $expiresAt);
        GmailMailer::sendPasswordResetCode($pending['email'], $pending['username'], $verificationCode);

        Response::json([
            'message' => 'Hemos reenviado un nuevo código',
            'flow_token' => $newFlowToken,
            'masked_email' => AuthSupport::maskedEmail($pending['email']),
            'resend_cooldown' => AuthSupport::CODE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public static function mobileResendPasswordResetCode() {
        self::resendPasswordResetCode();
    }

    /**
     * Completa el restablecimiento: valida codigo, verifica contraseña nueva,
     * actualiza en BD, revoca todos los tokens y elimina el pending.
     */
    public static function completePasswordReset() {
        RateLimiter::check('password_reset_verify_attempts', 10, 300);
        PendingPasswordReset::purgeExpired();

        $data = AuthSupport::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');

        if ($flowToken === '' || $code === '') {
            Response::json(['error' => 'Código y flujo de recuperación obligatorios'], 400);
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            Response::json(['error' => 'El código debe tener 6 dígitos'], 400);
        }

        AuthSupport::validateNewPassword($password, $passwordConfirmation);

        $pending = PendingPasswordReset::findByFlowToken($flowToken);

        if (!$pending) {
            Response::json(['error' => 'Código incorrecto'], 400);
        }

        if (!empty($pending['locked_until'])) {
            $retryAfter = AuthSupport::secondsUntil($pending['locked_until']);

            if ($retryAfter > 0) {
                Response::json([
                    'error' => 'Demasiados intentos. Espera antes de volver a intentarlo',
                    'retry_after' => $retryAfter,
                    'locked' => true,
                ], 429);
            }
        }

        if (strtotime($pending['verification_expires_at']) < time()) {
            PendingPasswordReset::deleteById($pending['id']);
            Response::json(['error' => 'El código ha caducado. Solicita uno nuevo'], 410);
        }

        if ((int) $pending['verification_attempts'] >= AuthSupport::VERIFICATION_CODE_MAX_ATTEMPTS) {
            $lockedUntil = AuthSupport::passwordResetLockUntil();
            PendingPasswordReset::lock($pending['id'], $lockedUntil);
            Response::json([
                'error' => 'Demasiados intentos. Espera antes de volver a intentarlo',
                'retry_after' => AuthSupport::PASSWORD_RESET_LOCK_SECONDS,
                'locked' => true,
            ], 429);
        }

        if (!password_verify($code, $pending['verification_code_hash'])) {
            PendingPasswordReset::incrementAttempts($pending['id']);

            if (((int) $pending['verification_attempts']) + 1 >= AuthSupport::VERIFICATION_CODE_MAX_ATTEMPTS) {
                $lockedUntil = AuthSupport::passwordResetLockUntil();
                PendingPasswordReset::lock($pending['id'], $lockedUntil);
                Response::json([
                    'error' => 'Código incorrecto demasiadas veces. Espera antes de volver a intentarlo',
                    'retry_after' => AuthSupport::PASSWORD_RESET_LOCK_SECONDS,
                    'locked' => true,
                ], 429);
            }

            Response::json(['error' => 'Código incorrecto'], 400);
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            User::updatePassword($pending['user_id'], $password);
            Auth::revokeOtherTokens($pending['user_id']);
            PendingPasswordReset::deleteById($pending['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json(['message' => 'Contraseña restaurada correctamente']);
    }

    public static function mobileCompletePasswordReset() {
        self::completePasswordReset();
    }

    /** Cancela un restablecimiento pendiente (solo si no esta bloqueado). */
    public static function cancelPasswordReset() {
        PendingPasswordReset::purgeExpired();

        $data = AuthSupport::jsonBody();
        $flowToken = trim((string) ($data['flow_token'] ?? ''));

        if ($flowToken !== '') {
            $pending = PendingPasswordReset::findByFlowToken($flowToken);
            if ($pending) {
                PendingPasswordReset::deleteUnlockedById($pending['id']);
            }
        }

        Response::json(['success' => true]);
    }

    public static function mobileCancelPasswordReset() {
        self::cancelPasswordReset();
    }

    /**
     * Flujo comun de inicio de restablecimiento: rate limiting, CAPTCHA opcional,
     * busca usuario (responde igual exista o no), verifica bloqueos,
     * genera flow_token y codigo, envia email.
     */
    private static function startPasswordResetFlow($requireAltcha) {
        RateLimiter::check('password_reset_start_attempts', 5, 300);
        PendingPasswordReset::purgeExpired();

        $data = AuthSupport::jsonBody();

        if ($requireAltcha) {
            Altcha::verifyOrFail($data['altcha'] ?? '');
        }

        $email = trim((string) ($data['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Introduce un correo electrónico válido'], 400);
        }

        $user = User::findByEmail($email);

        if (!$user) {
            Response::json(self::startPayload(AuthSupport::maskedEmail($email)));
        }

        $pending = PendingPasswordReset::findByUserId($user['id']);

        if ($pending && !empty($pending['locked_until'])) {
            $retryAfter = AuthSupport::secondsUntil($pending['locked_until']);

            if ($retryAfter > 0) {
                Response::json([
                    'error' => 'Demasiados intentos. Espera antes de pedir otro código',
                    'retry_after' => $retryAfter,
                    'locked' => true,
                ], 429);
            }
        }

        $flowToken = AuthSupport::generateFlowToken();
        $verificationCode = AuthSupport::generateVerificationCode();
        $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT, ['cost' => 12]);
        $expiresAt = AuthSupport::expiresAt();
        $created = false;

        if ($pending) {
            PendingPasswordReset::updateRequest($pending['id'], $flowToken, $verificationCodeHash, $expiresAt);
        } else {
            PendingPasswordReset::create($user['id'], $flowToken, $verificationCodeHash, $expiresAt);
            $created = true;
        }

        try {
            GmailMailer::sendPasswordResetCode($user['email'], $user['username'], $verificationCode);
        } catch (Throwable $e) {
            if ($created) {
                PendingPasswordReset::deleteByUserId($user['id']);
            }

            throw $e;
        }

        Response::json([
            'message' => 'Si existe una cuenta con ese correo, recibirás un código para restaurar tu contraseña.',
            'flow_token' => $flowToken,
            'masked_email' => AuthSupport::maskedEmail($user['email']),
            'resend_cooldown' => AuthSupport::CODE_RESEND_COOLDOWN_SECONDS,
        ]);
    }

    private static function startPayload($maskedEmail = null) {
        return [
            'message' => 'Si existe una cuenta con ese correo, recibirás un código para restaurar tu contraseña.',
            'flow_token' => AuthSupport::generateFlowToken(),
            'masked_email' => $maskedEmail ?: 'tu correo',
            'resend_cooldown' => AuthSupport::CODE_RESEND_COOLDOWN_SECONDS,
        ];
    }
}
