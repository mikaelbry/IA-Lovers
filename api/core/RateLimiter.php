<?php

class RateLimiter {

    public static function check($key, $limit = 10, $seconds = 60) {

        session_start();

        if (!isset($_SESSION['rate'][$key])) {
            $_SESSION['rate'][$key] = [];
        }

        $_SESSION['rate'][$key] = array_filter(
            $_SESSION['rate'][$key],
            fn($timestamp) => $timestamp > time() - $seconds
        );

        if (count($_SESSION['rate'][$key]) >= $limit) {
            http_response_code(429);
            echo json_encode(['error' => 'Demasiadas solicitudes']);
            exit;
        }

        $_SESSION['rate'][$key][] = time();
    }
}
