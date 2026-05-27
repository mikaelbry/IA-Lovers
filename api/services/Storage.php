<?php

class Storage {

    public static function publicUrl($userId, $storedPath) {
        if (!$storedPath) {
            return null;
        }

        if (str_starts_with($storedPath, 'http://') || str_starts_with($storedPath, 'https://')) {
            return $storedPath;
        }

        $baseUrl = rtrim(self::env(
            'SUPABASE_STORAGE_PUBLIC_URL',
            rtrim(self::env('SUPABASE_URL', ''), '/') . '/storage/v1/object/public/' . self::bucket()
        ), '/');

        return $baseUrl . '/' . self::objectPath($userId, $storedPath);
    }

    public static function mapPost(array $post) {
        if (isset($post['user_id'], $post['file_path'])) {
            $post['file_path'] = self::publicUrl($post['user_id'], $post['file_path']);
        }

        foreach (['title', 'description'] as $textKey) {
            if (isset($post[$textKey]) && $post[$textKey] !== null) {
                $post[$textKey] = html_entity_decode($post[$textKey], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        foreach (['id', 'user_id', 'likes_count', 'comments_count'] as $numericKey) {
            if (isset($post[$numericKey])) {
                $post[$numericKey] = (int) $post[$numericKey];
            }
        }

        if (isset($post['liked_by_user'])) {
            $post['liked_by_user'] = filter_var($post['liked_by_user'], FILTER_VALIDATE_BOOLEAN);
        }

        return $post;
    }

    public static function mapPosts(array $posts) {
        return array_map(fn($post) => self::mapPost($post), $posts);
    }

    public static function uploadUserFile($userId, $tmpFile, $filename, $mimeType) {
        $objectPath = self::userObjectPath($userId, $filename);
        $endpoint = rtrim(self::env('SUPABASE_URL'), '/') . '/storage/v1/object/' . self::bucket() . '/' . $objectPath;

        $body = file_get_contents($tmpFile);
        if ($body === false) {
            throw new RuntimeException('No se pudo leer el archivo temporal');
        }

        $response = self::request('POST', $endpoint, [
            'Content-Type: ' . $mimeType,
            'x-upsert: false',
        ], $body);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Error subiendo imagen: ' . $response['body']);
        }
    }

    public static function deleteFile($userId, $storedPath) {
        if (!$storedPath) {
            return;
        }

        $endpoint = rtrim(self::env('SUPABASE_URL'), '/') . '/storage/v1/object/' . self::bucket();
        $payload = json_encode([
            'prefixes' => [self::objectPath($userId, $storedPath)],
        ], JSON_UNESCAPED_UNICODE);

        $response = self::request('DELETE', $endpoint, [
            'Content-Type: application/json',
        ], $payload);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Error eliminando imagen: ' . $response['body']);
        }
    }

    private static function objectPath($userId, $storedPath) {
        $filename = basename($storedPath);

        if (str_contains($storedPath, '/')) {
            return 'uploads/' . $filename;
        }

        return self::userObjectPath($userId, $filename);
    }

    private static function userObjectPath($userId, $filename) {
        return 'uploads/' . $userId . '/' . $filename;
    }

    private static function bucket() {
        return self::env('SUPABASE_STORAGE_BUCKET', 'storage');
    }

    private static function request($method, $url, array $headers, $body = null) {
        $serviceKey = self::env('SUPABASE_SERVICE_ROLE_KEY');

        if (!$serviceKey) {
            throw new RuntimeException('Falta SUPABASE_SERVICE_ROLE_KEY en el .env');
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge($headers, [
                'apikey: ' . $serviceKey,
                'Authorization: Bearer ' . $serviceKey,
            ]),
            CURLOPT_POSTFIELDS => $body,
        ]);

        try {
            $responseBody = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
        } finally {
            $ch = null;
        }

        if ($responseBody === false) {
            throw new RuntimeException('Error de red con Supabase Storage: ' . $error);
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
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
