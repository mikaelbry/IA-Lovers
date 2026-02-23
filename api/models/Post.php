<?php

require_once __DIR__ . '/../config/database.php';

class Post {

    public static function create($user_id, $title, $description, $file_path) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, title, description, file_path, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([$user_id, $title, $description, $file_path]);
    }

    public static function getByUser($user_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT *
            FROM posts
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}