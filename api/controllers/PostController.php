<?php

require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../core/Response.php';

class PostController {

    public static function latest() {
        $posts = Post::getLatest(10);
        Response::json($posts);
    }
}
