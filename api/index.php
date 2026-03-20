<?php

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/RateLimiter.php';

/* CONTROLLERS */
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/PostController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/FollowController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/CommentController.php';
require_once __DIR__ . '/controllers/TagController.php';

$router = new Router();

/* =======================
   USER
======================= */

$router->get('/users/profile', fn() => UserController::profile());
$router->get('/users/public', fn() => UserController::publicProfile());
$router->get('/users/username', fn() => UserController::profileByUsername());
$router->get('/users/followers', fn() => FollowController::followers());
$router->get('/users/following', fn() => FollowController::following());

/* =======================
   POSTS
======================= */

$router->post('/posts/create', fn() => PostController::create());

$router->get('/posts', fn() => PostController::feed());

$router->get('/posts/show', fn() => PostController::show());
$router->post('/posts/toggle-like', fn() => PostController::toggleLike());

/* =======================
   AUTH
======================= */

$router->post('/register', fn() => AuthController::register());
$router->post('/login', fn() => AuthController::login());

/* =======================
   FOLLOW
======================= */

$router->post('/follow', fn() => FollowController::follow());

/* =======================
   NOTIFICATIONS
======================= */

$router->get('/notifications', fn() => NotificationController::get());

/* =======================
   COMMENTS
======================= */

$router->post('/comments/create', fn() => CommentController::create());
$router->post('/comments/delete', fn() => CommentController::delete());

/* =======================
   TAGS
======================= */

$router->get('/tags/search', fn() => TagController::search());
$router->post('/tags/create', fn() => TagController::create());

/* =======================
   DISPATCH
======================= */

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);