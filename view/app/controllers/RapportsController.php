<?php
// /app/controllers/RapportsController.php

class RapportsController extends Controller {
    public function __construct() {
        if (!estConnecte() || !Auth::can('rapports_voir')) {
            die('Accès non autorisé.');
        }
    }

    public function ventes() {
        $rapportModel = $this->model('RapportModel');
        
        // Dates par défaut : le mois en cours
        $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
        $date_fin = $_GET['date_fin'] ?? date('Y-m-t');

        $lignes_ventes = $rapportModel->getRapportVentes($date_debut . ' 00:00:00', $date_fin . ' 23:59:59');

        $data = [
            'titre' => 'Rapport des Ventes et Bénéfices',
            'lignes_ventes' => $lignes_ventes,
            'date_debut' => $date_debut,
            'date_fin' => $date_fin
        ];

        $this->view('rapports/ventes', $data);
    }
    public function tresorerie() {
    $rapportModel = $this->model('RapportModel');
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
    $date_fin = $_GET['date_fin'] ?? date('Y-m-t');

    $lignes = $rapportModel->getRapportTresorerie($date_debut . ' 00:00:00', $date_fin . ' 23:59:59');

    // On traite les données pour un affichage simple
    $resume = ['Especes' => 0, 'TPE' => 0, 'Virement_Paye' => 0, 'Virement_Attente' => 0];
    foreach($lignes as $ligne){
        if($ligne['methode_paiement'] == 'Especes') $resume['Especes'] += $ligne['total_par_groupe'];
        if($ligne['methode_paiement'] == 'TPE') $resume['TPE'] += $ligne['total_par_groupe'];
        if($ligne['methode_paiement'] == 'Virement' && $ligne['statut_paiement'] == 'Payé') $resume['Virement_Paye'] += $ligne['total_par_groupe'];
        if($ligne['methode_paiement'] == 'Virement' && $ligne['statut_paiement'] == 'En attente') $resume['Virement_Attente'] += $ligne['total_par_groupe'];
    }

    $data = [
        'titre' => 'Rapport de Trésorerie',
        'resume' => $resume,
        'date_debut' => $date_debut,
        'date_fin' => $date_fin
    ];
    $this->view('rapports/tresorerie', $data);
}

public function livraisons() {
    $rapportModel = $this->model('RapportModel');
    $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
    $date_fin = $_GET['date_fin'] ?? date('Y-m-t');

    $livraisons = $rapportModel->getRapportLivraisons($date_debut . ' 00:00:00', $date_fin . ' 23:59:59');

    $data = [
        'titre' => 'Rapport des Livraisons',
        'livraisons' => $livraisons,
        'date_debut' => $date_debut,
        'date_fin' => $date_fin
    ];

    $this->view('rapports/livraisons', $data);
}
}