<?php

require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';

class PostController {

    public static function create() {

        $user = Middleware::auth();

        if (!isset($_FILES['image'])) {
            Response::json(['error' => 'Imagen requerida'], 400);
        }

        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'Error al subir archivo'], 400);
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            Response::json(['error' => 'Archivo demasiado grande'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg','image/png','image/webp'];

        if (!in_array($mime,$allowed)) {
            Response::json(['error'=>'Formato no permitido'],400);
        }

        $extension = match($mime){
            'image/jpeg'=>'.jpg',
            'image/png'=>'.png',
            'image/webp'=>'.webp'
        };

        $uploadDir = __DIR__.'/../../storage/uploads/';
        if(!is_dir($uploadDir)) mkdir($uploadDir,0755,true);

        $filename = bin2hex(random_bytes(16)).$extension;
        $targetPath = $uploadDir.$filename;

        move_uploaded_file($file['tmp_name'],$targetPath);

        $file_path = '/IA-Lovers/storage/uploads/'.$filename;


        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if(strlen($title) > 80){
            Response::json(['error'=>'Título demasiado largo'],400);
        }

        if(strlen($description) > 500){
            Response::json(['error'=>'Descripción demasiado larga'],400);
        }

        /* ===== DB ===== */

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id,title,description,file_path,created_at)
            VALUES (?,?,?, ?, NOW())
        ");

        $stmt->execute([
            $user['id'],
            htmlspecialchars($title),
            htmlspecialchars($description),
            $file_path
        ]);

        $postId = $pdo->lastInsertId();

        $tags = json_decode($_POST['tags'] ?? '[]', true);

        if($tags){

            foreach($tags as $name){

                $name = trim($name);

                if(strlen($name) > 24) continue;

                $name = ucfirst(strtolower($name));

                // buscar o crear
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE LOWER(name)=LOWER(?)");
                $stmt->execute([$name]);

                $tag = $stmt->fetch(PDO::FETCH_ASSOC);

                if(!$tag){
                    $insert = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                    $insert->execute([$name]);
                    $tagId = $pdo->lastInsertId();
                }else{
                    $tagId = $tag['id'];
                }

                $pdo->prepare("
                    INSERT INTO post_tags (post_id,tag_id)
                    VALUES (?,?)
                ")->execute([$postId,$tagId]);
            }
        }

        $pdo->commit();

        Response::json(['message'=>'Post creado','id'=>$postId]);
    }


    public static function feed(){

        $pdo = Database::getConnection();

        $type = $_GET['type'] ?? 'explore';
        $cursor = $_GET['cursor'] ?? null;
        $cursorLikes = $_GET['cursor_likes'] ?? null;
        $limit = 10;

        $title = $_GET['title'] ?? '';
        $tag = $_GET['tag'] ?? '';
        $order = $_GET['order'] ?? 'recent';
        $userFilter = $_GET['id'] ?? null;

        $user_id = null;

        $headers = getallheaders();

        if(isset($headers['Authorization'])){
            try{
                $user = Auth::user();
                $user_id = $user['id'];
            }catch(Exception $e){}
        }

        $where = [];
        $params = [];

        if($type === "following"){

            if(!$user_id){
                Response::json(['error'=>'Login requerido'],401);
            }

            $where[] = "posts.user_id IN (
                SELECT following_id
                FROM follows
                WHERE follower_id = ?
            )";

            $params[] = $user_id;
        }

        if($type === "user"){

            if(!$userFilter){
                Response::json(['error'=>'ID requerido'],400);
            }

            $where[] = "posts.user_id = ?";
            $params[] = $userFilter;
        }

        if($type === "me"){

            if(!$user_id){
                Response::json(['error'=>'Login requerido'],401);
            }

            $where[] = "posts.user_id = ?";
            $params[] = $user_id;
        }

        if($cursor && $order === 'likes' && $cursorLikes !== null){
            $where[] = "(
                (SELECT COUNT(*) FROM likes WHERE likes.post_id=posts.id) < ?
                OR (
                    (SELECT COUNT(*) FROM likes WHERE likes.post_id=posts.id) = ?
                    AND posts.id < ?
                )
            )";
            $params[] = $cursorLikes;
            $params[] = $cursorLikes;
            $params[] = $cursor;
        }
        elseif($cursor){
            $where[] = "posts.id < ?";
            $params[] = $cursor;
        }

        if($title !== ''){
            $where[] = "posts.title LIKE ?";
            $params[] = "%$title%";
        }

        if($tag !== ''){
            $where[] = "EXISTS (
                SELECT 1
                FROM post_tags
                JOIN tags ON tags.id = post_tags.tag_id
                WHERE post_tags.post_id = posts.id
                AND tags.name LIKE ?
            )";
            $params[] = "%$tag%";
        }

        $whereSQL = $where ? "WHERE ".implode(" AND ",$where) : "";

        $orderSQL = match($order){
            'oldest'=>'posts.created_at ASC, posts.id ASC',
            'likes'=>'likes_count DESC, posts.id DESC',
            default=>'posts.created_at DESC, posts.id DESC'
        };

        $sql = "
            SELECT
                posts.*,
                usuarios.username,

                (SELECT COUNT(*) FROM likes WHERE likes.post_id=posts.id) as likes_count,

                (SELECT COUNT(*) FROM comments WHERE comments.post_id=posts.id) as comments_count,

                EXISTS(
                    SELECT 1 FROM likes
                    WHERE likes.post_id=posts.id
                    AND likes.user_id=?
                ) as liked_by_user,

                (
                    SELECT GROUP_CONCAT(tags.name SEPARATOR ',')
                    FROM post_tags
                    JOIN tags ON tags.id=post_tags.tag_id
                    WHERE post_tags.post_id=posts.id
                ) as tags

            FROM posts
            JOIN usuarios ON usuarios.id=posts.user_id
            $whereSQL
            ORDER BY $orderSQL
            LIMIT $limit
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute(array_merge([$user_id],$params));

        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $nextCursor = null;
        $nextCursorLikes = null;

        if(count($posts) === $limit){
            $last = end($posts);
            $nextCursor = $last['id'];
            $nextCursorLikes = $last['likes_count'];
        }

        Response::json([
            "posts"=>$posts,
            "next_cursor"=>$nextCursor,
            "next_cursor_likes"=>$nextCursorLikes
        ]);
    }


    public static function toggleLike(){

        $user = Middleware::auth();

        $data = json_decode(file_get_contents("php://input"),true);
        $post_id = $data['post_id'] ?? null;

        if(!$post_id){
            Response::json(['error'=>'ID requerido'],400);
        }

        $pdo = Database::getConnection();

        $check = $pdo->prepare("
            SELECT id FROM likes WHERE user_id=? AND post_id=?
        ");
        $check->execute([$user['id'],$post_id]);

        if($check->fetch()){

            $pdo->prepare("
                DELETE FROM likes WHERE user_id=? AND post_id=?
            ")->execute([$user['id'],$post_id]);

            Response::json(['liked'=>false]);
        }

        $pdo->prepare("
            INSERT INTO likes (user_id,post_id)
            VALUES (?,?)
        ")->execute([$user['id'],$post_id]);

        Response::json(['liked'=>true]);
    }

    public static function show(){

        $pdo = Database::getConnection();

        $id = $_GET['id'] ?? null;

        if(!$id){
            Response::json(['error'=>'ID requerido'],400);
        }

        $user_id = null;

        $headers = getallheaders();

        if(isset($headers['Authorization'])){
            try{
                $user = Auth::user();
                $user_id = $user['id'];
            }catch(Exception $e){}
        }

        $stmt = $pdo->prepare("
            SELECT
                posts.*,
                usuarios.username,

                (SELECT COUNT(*) FROM likes WHERE likes.post_id=posts.id) as likes_count,

                (SELECT COUNT(*) FROM comments WHERE comments.post_id=posts.id) as comments_count,

                EXISTS(
                    SELECT 1 FROM likes
                    WHERE likes.post_id=posts.id
                    AND likes.user_id=?
                ) as liked_by_user,

                (
                    SELECT GROUP_CONCAT(tags.name SEPARATOR ',')
                    FROM post_tags
                    JOIN tags ON tags.id=post_tags.tag_id
                    WHERE post_tags.post_id=posts.id
                ) as tags

            FROM posts
            JOIN usuarios ON usuarios.id=posts.user_id
            WHERE posts.id=?
        ");

        $stmt->execute([$user_id,$id]);

        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$post){
            Response::json(['error'=>'Post no encontrado'],404);
        }

        $stmt = $pdo->prepare("
            SELECT
                comments.*,
                usuarios.username
            FROM comments
            JOIN usuarios ON usuarios.id=comments.user_id
            WHERE comments.post_id=?
            ORDER BY comments.created_at ASC
        ");

        $stmt->execute([$id]);

        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            "post"=>$post,
            "comments"=>$comments
        ]);
    }

}