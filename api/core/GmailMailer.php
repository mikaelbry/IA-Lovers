<?php

require_once __DIR__ . '/../vendor/Psr/Log/LoggerInterface.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/src/OAuthTokenProvider.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class GmailMailer {

    public static function sendRegistrationCode($toEmail, $username, $code) {
        $appName = self::env('APP_NAME', 'IA-Lovers');
        $subject = 'Tu codigo de verificacion de ' . $appName;
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

        $html = '
            <div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#13283d;line-height:1.6;">
                <h1 style="font-size:24px;margin:0 0 16px;">Verifica tu correo</h1>
                <p style="margin:0 0 12px;">Hola ' . $safeUsername . ',</p>
                <p style="margin:0 0 12px;">Usa este codigo para terminar tu registro en ' . $safeAppName . ':</p>
                <div style="margin:20px 0;padding:18px 20px;border-radius:16px;background:#eef6fd;border:1px solid #cfe2f3;text-align:center;">
                    <span style="font-size:34px;font-weight:700;letter-spacing:0.3em;color:#0e5f9d;">' . $safeCode . '</span>
                </div>
                <p style="margin:0 0 12px;">El codigo caduca en 10 minutos.</p>
                <p style="margin:0;color:#6a7f90;font-size:13px;">Si tu no intentaste crear esta cuenta, puedes ignorar este correo.</p>
            </div>
        ';

        self::send($toEmail, $subject, $html);
    }

    public static function sendEmailChangeCode($toEmail, $username, $code) {
        $appName = self::env('APP_NAME', 'IA-Lovers');
        $subject = 'Confirma tu nuevo correo en ' . $appName;
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

        $html = '
            <div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#13283d;line-height:1.6;">
                <h1 style="font-size:24px;margin:0 0 16px;">Verifica tu nuevo correo</h1>
                <p style="margin:0 0 12px;">Hola ' . $safeUsername . ',</p>
                <p style="margin:0 0 12px;">Usa este codigo para confirmar el cambio de correo en ' . $safeAppName . ':</p>
                <div style="margin:20px 0;padding:18px 20px;border-radius:16px;background:#eef6fd;border:1px solid #cfe2f3;text-align:center;">
                    <span style="font-size:34px;font-weight:700;letter-spacing:0.3em;color:#0e5f9d;">' . $safeCode . '</span>
                </div>
                <p style="margin:0 0 12px;">El codigo caduca en 10 minutos.</p>
                <p style="margin:0;color:#6a7f90;font-size:13px;">Si no has pedido este cambio, ignora este correo.</p>
            </div>
        ';

        self::send($toEmail, $subject, $html);
    }

    private static function send($toEmail, $subject, $html) {
        $host = self::env('SMTP_HOST', 'smtp.gmail.com');
        $port = (int) self::env('SMTP_PORT', '587');
        $secure = strtolower((string) self::env('SMTP_SECURE', 'tls'));
        $username = self::env('SMTP_USERNAME');
        $password = self::env('SMTP_PASSWORD');
        $fromEmail = self::env('SMTP_FROM_EMAIL', $username);
        $fromName = self::env('SMTP_FROM_NAME', self::env('APP_NAME', 'IA-Lovers'));

        if (!$username) {
            throw new RuntimeException('Falta SMTP_USERNAME en el .env');
        }

        if (!$password) {
            throw new RuntimeException('Falta SMTP_PASSWORD en el .env');
        }

        if (!$fromEmail) {
            throw new RuntimeException('Falta SMTP_FROM_EMAIL en el .env');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = $secure === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = self::plainTextBody($subject, $html);

            $mail->send();
        } catch (Exception $e) {
            throw new RuntimeException('No se pudo enviar el correo de verificacion: ' . $mail->ErrorInfo);
        }
    }

    private static function plainTextBody($subject, $html) {
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        return $subject . "\n\n" . $body;
    }

    private static function env($key, $default = null) {
        static $loaded = false;

        if (!$loaded) {
            self::loadEnv();
            $loaded = true;
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    private static function loadEnv() {
        $envPath = dirname(__DIR__, 2) . '/.env';

        if (!is_file($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
}
