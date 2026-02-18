<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';

class CartController {

    public static function add() {

        $user = Auth::user();
        $data = json_decode(file_get_contents("php://input"), true);
        $post_id = $data['post_id'];

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO cart (user_id, post_id)
            VALUES (?, ?)
        ");

        $stmt->execute([$user['id'], $post_id]);

        Response::json(['message' => 'Añadido al carrito']);
    }

    public static function remove() {

        $user = Auth::user();
        $data = json_decode(file_get_contents("php://input"), true);
        $post_id = $data['post_id'];

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            DELETE FROM cart
            WHERE user_id = ? AND post_id = ?
        ");

        $stmt->execute([$user['id'], $post_id]);

        Response::json(['message' => 'Eliminado del carrito']);
    }

    public static function get() {

        $user = Auth::user();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT posts.*
            FROM cart
            JOIN posts ON posts.id = cart.post_id
            WHERE cart.user_id = ?
            ORDER BY cart.created_at DESC
        ");

        $stmt->execute([$user['id']]);

        Response::json($stmt->fetchAll());
    }
}
