<?php

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/RateLimiter.php'; // 🔥 AÑADE ESTA LÍNEA

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/PostController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/FollowController.php';
require_once __DIR__ . '/controllers/NotificationController.php';

$router = new Router();

$router->get('/user/profile', fn() => UserController::profile());
$router->post('/user/update', fn() => UserController::update());

$router->post('/posts/create', fn() => PostController::create());
$router->get('/posts/latest', fn() => PostController::latest());

$router->post('/register', fn() => AuthController::register());
$router->post('/login', fn() => AuthController::login());

$router->get('/notifications', fn() => NotificationController::get());

$router->post('/posts/like', fn() => PostController::like());

$router->post('/follow', fn() => FollowController::follow());
$router->get('/posts/following', fn() => FollowController::followingPosts());

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
$router->get('/users/public', fn() => UserController::publicProfile());

