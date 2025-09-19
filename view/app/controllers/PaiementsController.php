<?php
// /app/controllers/PaiementsController.php

class PaiementsController extends Controller {
    private $factureModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('paiements_encaisser')) {
            die('Accès non autorisé.');
        }
        $this->factureModel = $this->model('FactureModel');
    }

    // Affiche la liste des paiements en attente
    public function index() {
        $data = [
            'titre' => 'Suivi des Encaissements',
            'factures_en_attente' => $this->factureModel->listerFacturesEnAttenteDePaiement()
        ];
        $this->view('paiements/index', $data);
    }

    // Affiche le formulaire pour enregistrer un paiement
    public function enregistrer($facture_id) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $facture = $this->factureModel->trouverFactureParId($facture_id);
        if (!$facture) die('Facture non trouvée');

        $montant_versement = (float)$_POST['montant_versement'];
        $montant_restant_du = $facture['montant_ttc'] - $facture['montant_paye'];

        // S'assurer que le versement ne dépasse pas le montant restant dû
        if ($montant_versement > $montant_restant_du) {
            die('Erreur : Le montant du versement est supérieur au montant restant à payer.');
        }

        $data_paiement = [
            'facture_id' => $facture_id,
            'montant' => $montant_versement,
            'methode_paiement' => $_POST['methode_paiement_encaissement'],
            'reference_paiement' => $_POST['reference_paiement'],
            'utilisateur_id' => $_SESSION['user_id']
        ];
        
        $paiementModel = $this->model('PaiementModel'); // Nouveau modèle à créer
        if ($paiementModel->enregistrerVersement($data_paiement)) {
            enregistrerLog("Versement de $montant_versement DA enregistré pour facture #$facture_id");
            rediriger('factures/voir/' . $facture_id);
        } else {
            die('Erreur lors de l\'enregistrement du versement.');
        }
    }
}
}