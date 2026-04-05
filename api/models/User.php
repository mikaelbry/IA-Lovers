<?php

require_once __DIR__ . '/../config/database.php';

class User {

    public static function create($username, $email, $password) {

        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (username, email, password_hash, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");

        return $stmt->execute([$username, $email, $hash]);
    }

    public static function findByEmail($email) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function findById($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id, username, email, created_at, avatar_path
            FROM usuarios
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findByUsername($username) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id, username, avatar_path
            FROM usuarios
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public static function update($id, $username, $email, $password = null) {

        $pdo = Database::getConnection();

        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE usuarios
                SET username = ?, email = ?, password_hash = ?
                WHERE id = ?
            ");
            return $stmt->execute([$username, $email, $hash, $id]);
        }

        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET username = ?, email = ?
            WHERE id = ?
        ");

        return $stmt->execute([$username, $email, $id]);
    }

    public static function updateAvatar($id, $avatarPath) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET avatar_path = ?
            WHERE id = ?
        ");

        return $stmt->execute([$avatarPath, $id]);
    }
}
