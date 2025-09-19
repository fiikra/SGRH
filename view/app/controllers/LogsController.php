<?php
// /app/controllers/LogsController.php

class LogsController extends Controller {
    private $logModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('logs_voir')) die('Accès non autorisé.');
        $this->logModel = $this->model('LogModel');
    }

    /**
     * Affiche la liste de tous les logs.
     */
    public function index() {
        $data = [
            'titre' => 'Journal des Opérations Système',
            'logs' => $this->logModel->listerTousLesLogs()
        ];
        $this->view('logs/index', $data);
    }
}