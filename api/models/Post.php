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

    public static function getLatest($page = 1, $limit = 12) {

        $pdo = Database::getConnection();

        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT 
                posts.*,
                usuarios.username,
                (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count
            FROM posts
            JOIN usuarios ON usuarios.id = posts.user_id
            ORDER BY posts.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }



    public static function getByUser($user_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT * FROM posts
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$user_id]);

        return $stmt->fetchAll();
    }

}
