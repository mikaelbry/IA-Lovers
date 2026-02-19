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

        // eliminar nombre del proyecto
        $uri = str_replace('/IA-Lovers/api', '', $uri);
        $uri = str_replace('/api', '', $uri);
        $uri = str_replace('/index.php', '', $uri);

        if ($uri === '') {
            $uri = '/';
        }

        if (isset($this->routes[$method][$uri])) {
            call_user_func($this->routes[$method][$uri]);
            return;
        }

        http_response_code(404);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(['error' => 'Ruta no encontrada: ' . $uri]);
        exit;
    }
}
