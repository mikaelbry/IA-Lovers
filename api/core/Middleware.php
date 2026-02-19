<?php

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

class Middleware {

    public static function auth() {

        try {
            return Auth::user();
        } catch (Exception $e) {
            Response::json(['error' => 'No autorizado'], 401);
        }
    }
}
