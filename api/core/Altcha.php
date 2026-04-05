<?php

require_once __DIR__ . '/Response.php';

class Altcha {
    private const DEFAULT_ALGORITHM = 'SHA-256';
    private const DEFAULT_EXPIRE_SECONDS = 1200;
    private const DEFAULT_MAX_NUMBER = 75000;

    public static function challenge() {
        $hmacKey = self::hmacKey();
        $expireSeconds = max(60, (int) self::env('ALTCHA_EXPIRE_SECONDS', self::DEFAULT_EXPIRE_SECONDS));
        $maxNumber = max(1000, (int) self::env('ALTCHA_MAX_NUMBER', self::DEFAULT_MAX_NUMBER));

        $expiresAt = time() + $expireSeconds;
        $salt = bin2hex(random_bytes(12)) . '?expires=' . $expiresAt . '&nonce=' . bin2hex(random_bytes(12)) . '&';
        $number = random_int(0, $maxNumber);
        $challenge = hash('sha256', $salt . $number);
        $signature = hash_hmac('sha256', $challenge, $hmacKey);

        return [
            'algorithm' => self::DEFAULT_ALGORITHM,
            'challenge' => $challenge,
            'maxnumber' => $maxNumber,
            'salt' => $salt,
            'signature' => $signature,
        ];
    }

    public static function verifyOrFail($payload) {
        if (!self::verify($payload)) {
            Response::json(['error' => 'Verificacion ALTCHA invalida o caducada'], 400);
        }
    }

    public static function verify($payload) {
        if (!is_string($payload) || trim($payload) === '') {
            return false;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return false;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return false;
        }

        $algorithm = strtoupper((string) ($data['algorithm'] ?? ''));
        $challenge = (string) ($data['challenge'] ?? '');
        $salt = (string) ($data['salt'] ?? '');
        $signature = (string) ($data['signature'] ?? '');
        $number = $data['number'] ?? null;

        $hashAlgorithm = self::hashAlgorithm($algorithm);

        if ($hashAlgorithm === null || $challenge === '' || $salt === '' || $signature === '' || !is_numeric($number)) {
            return false;
        }

        $number = (int) $number;
        $params = self::extractParams($salt);
        $expires = isset($params['expires']) && is_numeric($params['expires']) ? (int) $params['expires'] : null;

        if ($expires === null || time() > $expires) {
            return false;
        }

        $expectedChallenge = hash($hashAlgorithm, $salt . $number);
        $expectedSignature = hash_hmac($hashAlgorithm, $expectedChallenge, self::hmacKey());

        if (!hash_equals($expectedChallenge, $challenge) || !hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $registryKey = hash('sha256', $algorithm . '|' . $salt . '|' . $number . '|' . $challenge . '|' . $signature);

        if (self::isReplay($registryKey, $expires)) {
            return false;
        }

        return true;
    }

    private static function hashAlgorithm($algorithm) {
        return match ($algorithm) {
            'SHA-1' => 'sha1',
            'SHA-256' => 'sha256',
            'SHA-512' => 'sha512',
            default => null
        };
    }

    private static function extractParams($salt) {
        $parts = explode('?', $salt, 2);
        if (count($parts) < 2) {
            return [];
        }

        $params = [];
        parse_str($parts[1], $params);
        return is_array($params) ? $params : [];
    }

    private static function isReplay($key, $expires) {
        $path = self::registryPath('ia_lovers_altcha_replays.json');
        $handle = fopen($path, 'c+');

        if (!$handle) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            $contents = stream_get_contents($handle);
            $entries = json_decode($contents ?: '{}', true);
            if (!is_array($entries)) {
                $entries = [];
            }

            $now = time();
            $entries = array_filter($entries, fn($entryExpiry) => is_numeric($entryExpiry) && (int) $entryExpiry > $now);

            $alreadyUsed = isset($entries[$key]);

            if (!$alreadyUsed) {
                $entries[$key] = $expires;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($entries, JSON_UNESCAPED_UNICODE));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $alreadyUsed;
        } finally {
            fclose($handle);
        }
    }

    private static function hmacKey() {
        $key = (string) self::env('ALTCHA_HMAC_KEY', '');

        if (strlen($key) < 24) {
            throw new RuntimeException('ALTCHA_HMAC_KEY debe existir en .env y tener al menos 24 caracteres');
        }

        return $key;
    }

    private static function registryPath($filename) {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
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
