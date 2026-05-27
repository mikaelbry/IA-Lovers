<?php
/**
 * Middleware de autenticacion.
 * Verifica el token Bearer y devuelve el usuario autenticado o 401.
 */
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Response.php';

class Middleware {

    /** Valida el token de la peticion y devuelve el usuario autenticado. */
    public static function auth() {

        try {
            return Auth::user();
        } catch (Exception $e) {
            Response::json(['error' => 'No autorizado'], 401);
        }
    }
}
