<?php
/**
 * Enrutador HTTP casero para la API.
 * Soporta GET y POST, normalizacion de URI y dispatch.
 */
class Router {

    /** Almacen de rutas registradas: [METHOD => [uri => callable]]. */
    private $routes = [];

    /** Registra una ruta GET. */
    public function get($uri, $action) {
        $this->routes['GET'][$this->normalizeUri($uri)] = $action;
    }

    /** Registra una ruta POST. */
    public function post($uri, $action) {
        $this->routes['POST'][$this->normalizeUri($uri)] = $action;
    }

    /**
     * Ejecuta la accion asociada a la ruta solicitada.
     * Si no encuentra la ruta responde 404.
     */
    public function dispatch($method, $uri) {

        $uri = $this->normalizeUri($uri);

        if (isset($this->routes[$method][$uri])) {
            call_user_func($this->routes[$method][$uri]);
            return;
        }

        http_response_code(404);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            'error' => 'Ruta no encontrada: ' . $uri
        ]);
        exit;
    }

    /**
     * Normaliza la URI: extrae la parte tras /backend/, elimina /index.php,
     * garantiza slash inicial y elimina trailing slash.
     */
    private function normalizeUri($uri) {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        $apiPos = strpos($uri, '/backend/');
        if ($apiPos !== false) {
            $uri = substr($uri, $apiPos + 8);
        } elseif (substr($uri, -8) === '/backend') {
            $uri = '/';
        }

        $uri = str_replace('/index.php', '', $uri);

        $uri = '/' . ltrim($uri, '/');

        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri === '' ? '/' : $uri;
    }
}
