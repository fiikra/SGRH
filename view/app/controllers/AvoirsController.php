<?php
// /app/controllers/AvoirsController.php

class AvoirsController extends Controller {
    private $avoirModel;
    private $factureModel;

    public function __construct() {
        if (!estConnecte()) rediriger('utilisateurs/login');
        $this->avoirModel = $this->model('AvoirModel');
        $this->factureModel = $this->model('FactureModel');
    }
    
    public function index() {
        if (!Auth::can('avoirs_voir_liste')) die('Accès non autorisé.');
        $data = [
            'titre' => 'Liste des Factures d\'Avoir',
            'avoirs' => $this->avoirModel->listerTousLesAvoirs()
        ];
        $this->view('avoirs/index', $data);
    }
    
    public function creer($facture_id) {
        if (!Auth::can('avoirs_creer')) die('Accès non autorisé.');
        $facture = $this->factureModel->trouverFactureParId($facture_id);
        if (!$facture) rediriger('factures');

        $data = [
            'titre' => 'Générer un Avoir pour la Facture ' . $facture['numero_facture'],
            'facture' => $facture,
            'lignes' => $this->factureModel->listerLignesParFactureId($facture_id)
        ];
        $this->view('avoirs/creer', $data);
    }
    
    public function enregistrer() {
        if (!Auth::can('avoirs_creer')) die('Accès non autorisé.');
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $avoir_id = $this->avoirModel->creerAvoir(
                $_POST['facture_originale_id'],
                $_POST['lignes'] ?? [],
                $_POST['notes'],
                $_SESSION['user_id']
            );

            if ($avoir_id) {
                enregistrerLog("Avoir #$avoir_id créé pour facture #" . $_POST['facture_originale_id']);
                rediriger('avoirs');
            } else {
                die('Erreur lors de la création de l\'avoir.');
            }
        }
    }
    public function voir($id) {
    // La permission pour voir la liste donne le droit de voir un détail
    if (!Auth::can('avoirs_voir_liste')) die('Accès non autorisé.');

    $avoir = $this->avoirModel->trouverAvoirParId($id);
    if (!$avoir) {
        rediriger('avoirs');
    }

    // JOURNALISATION DE L'ACTION
    enregistrerLog("Consultation de l'avoir #" . $avoir['numero_avoir']);

    $data = [
        'titre' => 'Détails de l\'Avoir ' . $avoir['numero_avoir'],
        'avoir' => $avoir,
        'lignes' => $this->avoirModel->listerLignesParAvoirId($id)
    ];
    
    // Note: nous créérons une vue "imprimer.php" qui utilisera simpleView
    // Pour l'instant, la vue "voir" utilise le layout normal.
    $this->view('avoirs/voir', $data);
}
}