<?php

require_once __DIR__ . '/../config/database.php';

class Post {

    public static function getLatest($limit = 10) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT posts.id, posts.title, posts.description,
                   posts.file_path,
                   posts.created_at,
                   usuarios.username,
                   COUNT(likes.id) as likes_count
            FROM posts
            JOIN usuarios ON posts.user_id = usuarios.id
            LEFT JOIN likes ON likes.post_id = posts.id
            GROUP BY posts.id
            ORDER BY posts.created_at DESC
            LIMIT ?
        ");

        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
