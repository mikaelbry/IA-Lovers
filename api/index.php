<?php

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Content-Type: application/json");

require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/PostController.php';
require_once __DIR__ . '/controllers/UserController.php';

$router = new Router();

$router->get('/user/profile', function() {
    UserController::profile();
});

$router->post('/user/update', function() {
    UserController::update();
});

$router->post('/posts/create', function() {
    PostController::create();
});

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
