<?php
// /app/controllers/DashboardController.php

class DashboardController extends Controller {

    public function __construct() {
        // C'est LA LIGNE la plus importante pour la sécurité.
        // Si l'utilisateur n'est pas connecté, on le renvoie vers la page de login.
        if (!estConnecte()) {
            rediriger('utilisateurs/login');
        }
    }

    public function index() {
        $data = [
            'titre' => 'Tableau de Bord'
        ];
        $this->view('dashboard/index', $data);
    }
}