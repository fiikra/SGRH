<?php
// /app/core/Router.php

class Router {
    protected $controller = 'HomeController';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {
        $url = $this->parseUrl();

        // 1. Déterminer le contrôleur
        if (!empty($url[0]) && file_exists(ROOT . '/app/controllers/' . ucfirst($url[0]) . 'Controller.php')) {
            $this->controller = ucfirst($url[0]) . 'Controller';
            unset($url[0]);
        }
        require_once ROOT . '/app/controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        // 2. Déterminer la méthode
        if (isset($url[1]) && method_exists($this->controller, $url[1])) {
            $this->method = $url[1];
            unset($url[1]);
        }

        // 3. Récupérer les paramètres
        $this->params = $url ? array_values($url) : [];
    }

    public function dispatch() {
        // Appelle la méthode du contrôleur avec les paramètres
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    private function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
    }
}