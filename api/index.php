<?php

require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/PostController.php';

$router = new Router();

$router->get('/posts/latest', function() {
    PostController::latest();
});

$router->post('/register', function() {
    AuthController::register();
});

$router->post('/login', function() {
    AuthController::login();
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
