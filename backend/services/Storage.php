<?php
/**
 * Integracion con Supabase Storage para subir, eliminar y generar
 * URLs publicas de archivos (imagenes de posts y avatares).
 */
class Storage {

    /**
     * Devuelve la URL publica de un archivo en Supabase Storage.
     * Si storedPath ya es URL completa, la devuelve tal cual.
     */
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

    /** Transforma un post de BD a formato API: URLs, decodifica HTML y castea tipos. */
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

    /** Aplica mapPost() a un array de posts. */
    public static function mapPosts(array $posts) {
        return array_map(fn($post) => self::mapPost($post), $posts);
    }

    /**
     * Sube un archivo a Supabase Storage.
     * Construye la ruta uploads/{userId}/{filename} y envia peticion POST
     * con cURL usando la service role key.
     */
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
            throw new RuntimeException('Error al subir la imagen: ' . $response['body']);
        }
    }

    /** Elimina un archivo de Supabase Storage via peticion DELETE con cURL. */
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
            throw new RuntimeException('Error al eliminar la imagen: ' . $response['body']);
        }
    }

    /** Determina la ruta del objeto en Storage. Si tiene subdirectorio, extrae solo el nombre base. */
    private static function objectPath($userId, $storedPath) {
        $filename = basename($storedPath);

        if (str_contains($storedPath, '/')) {
            return 'uploads/' . $filename;
        }

        return self::userObjectPath($userId, $filename);
    }

    /** Construye la ruta uploads/{userId}/{filename}. */
    private static function userObjectPath($userId, $filename) {
        return 'uploads/' . $userId . '/' . $filename;
    }

    /** Devuelve el nombre del bucket desde .env (default: storage). */
    private static function bucket() {
        return self::env('SUPABASE_STORAGE_BUCKET', 'storage');
    }

    /** Ejecuta peticion HTTP a Supabase Storage con cURL usando service role key. */
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

    /** Obtiene variable de entorno de Storage. */
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

    /** Carga el archivo .env. */
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
