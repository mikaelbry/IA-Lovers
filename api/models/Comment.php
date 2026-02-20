<?php

require_once __DIR__ . '/../config/database.php';

class Comment {

    public static function create($user_id, $post_id, $content, $parent_id = null) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO comments (user_id, post_id, content, parent_id)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([$user_id, $post_id, $content, $parent_id]);
    }

    public static function delete($comment_id, $user_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            DELETE FROM comments
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([$comment_id, $user_id]);
    }

    public static function getByPost($post_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT comments.*, usuarios.username
            FROM comments
            JOIN usuarios ON usuarios.id = comments.user_id
            WHERE post_id = ?
            ORDER BY created_at ASC
        ");

        $stmt->execute([$post_id]);

        return $stmt->fetchAll();
    }

    public static function countByPost($post_id) {

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM comments WHERE post_id = ?
        ");

        $stmt->execute([$post_id]);

        return $stmt->fetchColumn();
    }
}