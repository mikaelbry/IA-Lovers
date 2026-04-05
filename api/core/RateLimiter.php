<?php

require_once __DIR__ . '/Response.php';

class RateLimiter {

    public static function check($key, $limit = 10, $seconds = 60) {
        $bucketKey = $key . '|' . self::clientIdentifier();
        $path = self::registryPath();
        $handle = fopen($path, 'c+');

        if (!$handle) {
            Response::json(['error' => 'No se pudo aplicar el limite de solicitudes'], 500);
        }

        $now = time();

        try {
            if (!flock($handle, LOCK_EX)) {
                Response::json(['error' => 'No se pudo aplicar el limite de solicitudes'], 500);
            }

            $contents = stream_get_contents($handle);
            $registry = json_decode($contents ?: '{}', true);

            if (!is_array($registry)) {
                $registry = [];
            }

            foreach ($registry as $registryKey => $timestamps) {
                $registry[$registryKey] = array_values(array_filter(
                    is_array($timestamps) ? $timestamps : [],
                    fn($timestamp) => is_numeric($timestamp) && (int) $timestamp > $now - $seconds
                ));

                if (!$registry[$registryKey]) {
                    unset($registry[$registryKey]);
                }
            }

            $attempts = $registry[$bucketKey] ?? [];

            if (count($attempts) >= $limit) {
                flock($handle, LOCK_UN);
                Response::json(['error' => 'Demasiadas solicitudes'], 429);
            }

            $attempts[] = $now;
            $registry[$bucketKey] = $attempts;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($registry, JSON_UNESCAPED_UNICODE));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private static function clientIdentifier() {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function registryPath() {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ia_lovers_rate_limit.json';
    }
}
