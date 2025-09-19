<?php
// /app/controllers/LivreursController.php

class LivreursController extends Controller {
    private $livreurModel;
    private $livraisonModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('livreurs_gerer')) { // Nouvelle permission requise
            die('Accès non autorisé.');
        }
        $this->livreurModel = $this->model('LivreurModel');
    }

    public function index() {
        $data = [
            'titre' => 'Gestion des Livreurs',
            'livreurs' => $this->livreurModel->listerTousLesLivreurs()
        ];
        $this->view('livreurs/index', $data);
    }

    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'nom' => trim($_POST['nom_complet']),
                'tel' => trim($_POST['telephone']),
                'actif' => isset($_POST['est_actif']) ? 1 : 0
            ];
            if ($this->livreurModel->creerLivreur($data)) {
                enregistrerLog("Création du livreur: " . $data['nom']);
                rediriger('livreurs');
            }
        }
        $data = ['titre' => 'Ajouter un Livreur'];
        $this->view('livreurs/creer', $data);
    }

    public function modifier($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'id' => $id,
                'nom' => trim($_POST['nom_complet']),
                'tel' => trim($_POST['telephone']),
                'actif' => isset($_POST['est_actif']) ? 1 : 0
            ];
            if ($this->livreurModel->modifierLivreur($data)) {
                enregistrerLog("Modification du livreur ID #$id: " . $data['nom']);
                rediriger('livreurs');
            }
        }
        $data = [
            'titre' => 'Modifier un Livreur',
            'livreur' => $this->livreurModel->trouverLivreurParId($id)
        ];
        $this->view('livreurs/modifier', $data);
    }

    public function voir($id) {
    $livreur = $this->livreurModel->trouverLivreurParId($id);
    if (!$livreur) {
        rediriger('livreurs');
    }

    // Gestion du filtre par date
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
    $date_fin = $_GET['date_fin'] ?? date('Y-m-t');

    $livraisonModel = $this->model('LivraisonModel');
    $livraisons = $livraisonModel->listerBonsParLivreurId($id, $date_debut . ' 00:00:00', $date_fin . ' 23:59:59');

    $data = [
        'titre' => 'Détails du Livreur : ' . $livreur['nom_complet'],
        'livreur' => $livreur,
        'livraisons' => $livraisons,
        'date_debut' => $date_debut,
        'date_fin' => $date_fin
    ];
    
    $this->view('livreurs/voir', $data);
}
}