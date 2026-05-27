<?php
/**
 * Helper para respuestas JSON de la API.
 */
class Response {

    /** Envia una respuesta JSON con codigo HTTP, establece headers y termina la ejecucion. */
    public static function json($data, $status = 200) {
        http_response_code($status);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
