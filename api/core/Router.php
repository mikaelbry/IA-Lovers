<?php

class Router {

    private $routes = [];

    public function get($uri, $action) {
        $this->routes['GET'][$this->normalizeUri($uri)] = $action;
    }

    public function post($uri, $action) {
        $this->routes['POST'][$this->normalizeUri($uri)] = $action;
    }

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

    private function normalizeUri($uri) {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        $apiPos = strpos($uri, '/api/');
        if ($apiPos !== false) {
            $uri = substr($uri, $apiPos + 4);
        } elseif (substr($uri, -4) === '/api') {
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
