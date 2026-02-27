<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';

class TagController {

    public static function search() {

        $q = $_GET['q'] ?? '';

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT id, name
            FROM tags
            WHERE name LIKE ?
            ORDER BY name ASC
            LIMIT 10
        ");

        $stmt->execute(["%$q%"]);

        Response::json($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create() {

        Middleware::auth();

        $data = json_decode(file_get_contents("php://input"), true);
        $name = trim($data['name'] ?? '');

        if (!$name) {
            Response::json(['error' => 'Nombre requerido'], 400);
        }

        $pdo = Database::getConnection();

        $check = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
        $check->execute([$name]);

        if ($existing = $check->fetch()) {
            Response::json($existing);
        }

        $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
        $stmt->execute([$name]);

        Response::json([
            'id' => $pdo->lastInsertId(),
            'name' => $name
        ]);
    }
}