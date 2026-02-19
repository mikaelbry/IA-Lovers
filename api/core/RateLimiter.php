<?php

require_once __DIR__ . '/Response.php';

class RateLimiter {

    public static function check($key, $limit = 10, $seconds = 60) {

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['rate'][$key])) {
            $_SESSION['rate'][$key] = [];
        }

        $_SESSION['rate'][$key] = array_filter(
            $_SESSION['rate'][$key],
            fn($timestamp) => $timestamp > time() - $seconds
        );

        if (count($_SESSION['rate'][$key]) >= $limit) {
            Response::json(['error' => 'Demasiadas solicitudes'], 429);
        }

        $_SESSION['rate'][$key][] = time();
    }
}
