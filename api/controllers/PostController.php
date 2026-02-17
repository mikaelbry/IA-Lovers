<?php

require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';

class PostController {

    public static function create() {

        $user = Auth::user();

        if (!isset($_FILES['image'])) {
            Response::json(['error' => 'Imagen requerida'], 400);
        }

        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'Error al subir archivo'], 400);
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            Response::json(['error' => 'Archivo demasiado grande (max 2MB)'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed)) {
            Response::json(['error' => 'Formato no permitido'], 400);
        }

        $extension = match($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp'
        };

        $uploadDir = __DIR__ . '/../../storage/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . $extension;
        $targetPath = $uploadDir . $filename;

        move_uploaded_file($file['tmp_name'], $targetPath);

        $file_path = 'http://localhost/IA-Lovers/api/storage/uploads/' . $filename;

        Post::create(
            $user['id'],
            htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            $file_path
        );

        Response::json(['message' => 'Post creado']);
    }


    public static function latest() {
        $posts = Post::getLatest();
        Response::json($posts);
    }
}
