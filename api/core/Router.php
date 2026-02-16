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

        // quitar /api del inicio
        if (strpos($uri, '/api') === 0) {
            $uri = substr($uri, 4);
        }

        if ($uri === '') {
            $uri = '/';
        }

        if (isset($this->routes[$method][$uri])) {
            call_user_func($this->routes[$method][$uri]);
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
}
