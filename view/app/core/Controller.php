<?php
// /app/core/Controller.php

class Controller {
    public function model($model) {
        require_once ROOT . '/app/models/' . $model . '.php';
        return new $model();
    }

    public function view($view, $data = []) {
        // Le chemin vers le fichier de la vue
        $viewFile = ROOT . '/app/views/' . $view . '.php';

        if (file_exists($viewFile)) {
            // Extraire les données pour les rendre accessibles
            extract($data);
            
            // Inclure l'en-tête
            require_once ROOT . '/app/views/includes/header.php';
            
            // Inclure la vue principale
            require_once $viewFile;

            // Inclure le pied de page
            require_once ROOT . '/app/views/includes/footer.php';

        } else {
            die('La vue n\'existe pas.');
        }
    }

    /**
     * NOUVELLE MÉTHODE
     * Charge une vue simple, sans header ni footer.
     * Idéal pour les pages d'impression ou les réponses AJAX.
     * @param string $view Le chemin de la vue.
     * @param array $data Les données à passer à la vue.
     */
    public function simpleView($view, $data = []) {
        $viewFile = ROOT . '/app/views/' . $view . '.php';

        if (file_exists($viewFile)) {
            // Rendre les données accessibles
            extract($data);
            
            // Inclure UNIQUEMENT le fichier de la vue
            require_once $viewFile;

        } else {
            die('La vue simple n\'existe pas : ' . $viewFile);
        }
    }
}