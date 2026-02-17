<?php

class Router {

    private $routes = [];

    public function get($uri, $action) {
        $this->routes['GET'][$uri] = $action;
    }

    public function post($uri, $action) {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch($method, $uri) {

        $uri = parse_url($uri, PHP_URL_PATH);

        // obtener solo lo que viene después de index.php
        if (strpos($uri, 'index.php') !== false) {
            $uri = substr($uri, strpos($uri, 'index.php') + 9);
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        }

        if (isset($this->routes[$method][$uri])) {
            call_user_func($this->routes[$method][$uri]);
            return;
        }

        http_response_code(404);
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Ruta no encontrada: ' . $uri]);
    }
}
