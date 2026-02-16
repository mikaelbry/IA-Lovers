<?php

require_once __DIR__ . '/../config/database.php';

class User {

    public static function create($username, $email, $password) {

        $pdo = Database::getConnection();

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios (username, email, password_hash, created_at)
            VALUES (?, ?, ?, NOW())
        ");

        return $stmt->execute([$username, $email, $hash]);
    }

    public static function findByEmail($email) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);

        return $stmt->fetch();
    }
}
