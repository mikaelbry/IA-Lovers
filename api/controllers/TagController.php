<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Middleware.php';

class TagController {

    public static function search() {

        $q = trim($_GET['q'] ?? '');

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

        // Normalizar nombre
        $name = ucfirst(strtolower($name));

        $pdo = Database::getConnection();

        // Buscar ignorando mayúsculas/minúsculas
        $check = $pdo->prepare("
            SELECT id, name 
            FROM tags 
            WHERE LOWER(name) = LOWER(?)
        ");
        $check->execute([$name]);

        if ($existing = $check->fetch(PDO::FETCH_ASSOC)) {
            Response::json($existing);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tags (name)
            VALUES (?)
        ");

        $stmt->execute([$name]);

        Response::json([
            'id' => $pdo->lastInsertId(),
            'name' => $name
        ]);
    }
}