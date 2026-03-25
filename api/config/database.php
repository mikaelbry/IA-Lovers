<?php

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        self::loadEnv();

        $databaseUrl = self::env('DATABASE_URL');

        if ($databaseUrl) {
            $parts = parse_url($databaseUrl);

            if ($parts === false) {
                exit('DATABASE_URL no es valida');
            }

            $host = $parts['host'] ?? '127.0.0.1';
            $port = $parts['port'] ?? 5432;
            $db = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
            $user = $parts['user'] ?? '';
            $pass = $parts['pass'] ?? '';
        } else {
            $host = self::env('DB_HOST', 'db.pvpylqxvnsyerzsqwery.supabase.co');
            $port = self::env('DB_PORT', '5432');
            $db = self::env('DB_NAME', 'postgres');
            $user = self::env('DB_USER', 'postgres');
            $pass = self::env('DB_PASSWORD', '');
        }

        $sslmode = self::env('DB_SSLMODE', 'require');
        $hostaddr = self::env('DB_HOSTADDR');

        $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=$sslmode";

        if ($hostaddr) {
            $dsn .= ";hostaddr=$hostaddr";
        }

        try {
            $this->pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            $message = $e->getMessage();

            if (stripos($message, 'could not translate host name') !== false) {
                $message .= ' | Error de conexión con la base de datos.';
            }

            throw new RuntimeException($message, 0, $e);
        }
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance->pdo;
    }

    private static function env($key, $default = null) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    private static function loadEnv() {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $envPath = dirname(__DIR__, 2) . '/.env';

        if (!is_file($envPath)) {
            $loaded = true;
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

        $loaded = true;
    }
}
