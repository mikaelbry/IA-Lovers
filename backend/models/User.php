<?php
/**
 * Modelo de acceso a datos de usuarios.
 * CRUD basico contra la tabla users.
 */
require_once __DIR__ . '/../config/Database.php';

class User {

    /** Crea un usuario con password hasheado (bcrypt, cost 12). */
    public static function create($username, $email, $password) {

        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        return self::createWithPasswordHash($username, $email, $hash);
    }

    /** Crea un usuario con un hash de password ya precomputado (para registros verificados). */
    public static function createWithPasswordHash($username, $email, $passwordHash) {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");

        return $stmt->execute([$username, $email, $passwordHash]);
    }

    /** Busca usuario por email (incluye password_hash para login). */
    public static function findByEmail($email) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /** Busca usuario por ID (sin password_hash). */
    public static function findById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id, username, email, created_at, avatar_path
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** Busca usuario por nombre de usuario (sin password_hash). */
    public static function findByUsername($username) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id, username, avatar_path
            FROM users
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    /** Actualiza datos del usuario. Si se provee password, lo hashea. */
    public static function update($id, $username, $email, $password = null) {

        $pdo = Database::getConnection();

        if ($password) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                UPDATE users
                SET username = ?, email = ?, password_hash = ?
                WHERE id = ?
            ");
            return $stmt->execute([$username, $email, $hash, $id]);
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET username = ?, email = ?
            WHERE id = ?
        ");

        return $stmt->execute([$username, $email, $id]);
    }

    /** Actualiza solo la contrasena del usuario. */
    public static function updatePassword($id, $password) {
        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("
            UPDATE users
            SET password_hash = ?
            WHERE id = ?
        ");

        return $stmt->execute([$hash, $id]);
    }

    /** Actualiza la ruta del avatar del usuario. */
    public static function updateAvatar($id, $avatarPath) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE users
            SET avatar_path = ?
            WHERE id = ?
        ");

        return $stmt->execute([$avatarPath, $id]);
    }
}
