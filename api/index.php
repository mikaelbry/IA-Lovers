<?php

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store, private");
header("Pragma: no-cache");
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/RateLimiter.php';
require_once __DIR__ . '/core/Altcha.php';

/* CONTROLLERS */
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/PostController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/FollowController.php';
require_once __DIR__ . '/controllers/NotificationController.php';
require_once __DIR__ . '/controllers/CommentController.php';
require_once __DIR__ . '/controllers/TagController.php';

$router = new Router();

/* =======================
   USER
======================= */

$router->get('/users/profile', fn() => UserController::profile());
$router->get('/users/settings-summary', fn() => UserController::settingsSummary());
$router->get('/users/public', fn() => UserController::publicProfile());
$router->get('/users/username', fn() => UserController::profileByUsername());
$router->get('/users/check-username', fn() => UserController::checkUsername());
$router->get('/users/followers', fn() => FollowController::followers());
$router->get('/users/following', fn() => FollowController::following());
$router->post('/user/update', fn() => UserController::update());
$router->post('/user/avatar', fn() => UserController::updateAvatar());
$router->post('/user/email-change/start', fn() => UserController::startEmailChange());
$router->post('/user/email-change/resend', fn() => UserController::resendEmailChange());
$router->post('/user/email-change/verify', fn() => UserController::verifyEmailChange());
$router->post('/user/email-change/cancel', fn() => UserController::cancelEmailChange());
$router->post('/user/delete', fn() => UserController::delete());

/* =======================
   POSTS
======================= */

$router->post('/posts/create', fn() => PostController::create());
$router->post('/posts/delete',fn() => PostController::delete());
$router->get('/posts', fn() => PostController::feed());

$router->get('/posts/show', fn() => PostController::show());
$router->post('/posts/toggle-like', fn() => PostController::toggleLike());

/* =======================
   AUTH
======================= */

$router->post('/register', fn() => AuthController::register());
$router->post('/register/start', fn() => AuthController::startRegistration());
$router->post('/register/verify', fn() => AuthController::verifyRegistration());
$router->post('/register/resend', fn() => AuthController::resendRegistrationCode());
$router->post('/register/cancel', fn() => AuthController::cancelPendingRegistration());
$router->post('/login', fn() => AuthController::login());
$router->post('/password-reset/start', fn() => AuthController::startPasswordReset());
$router->post('/password-reset/resend', fn() => AuthController::resendPasswordResetCode());
$router->post('/password-reset/complete', fn() => AuthController::completePasswordReset());
$router->post('/password-reset/cancel', fn() => AuthController::cancelPasswordReset());
$router->get('/session', fn() => AuthController::session());
$router->post('/logout', fn() => AuthController::logout());
$router->post('/mobile/register/start', fn() => AuthController::mobileStartRegistration());
$router->post('/mobile/register/verify', fn() => AuthController::mobileVerifyRegistration());
$router->post('/mobile/register/resend', fn() => AuthController::mobileResendRegistrationCode());
$router->post('/mobile/register/cancel', fn() => AuthController::mobileCancelPendingRegistration());
$router->post('/mobile/login', fn() => AuthController::mobileLogin());
$router->post('/mobile/password-reset/start', fn() => AuthController::mobileStartPasswordReset());
$router->post('/mobile/password-reset/resend', fn() => AuthController::mobileResendPasswordResetCode());
$router->post('/mobile/password-reset/complete', fn() => AuthController::mobileCompletePasswordReset());
$router->post('/mobile/password-reset/cancel', fn() => AuthController::mobileCancelPasswordReset());
$router->post('/mobile/logout', fn() => AuthController::logout());
$router->get('/altcha/challenge', fn() => Response::json(Altcha::challenge()));

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

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    Response::json([
        'error' => $e->getMessage()
    ], 500);
}
