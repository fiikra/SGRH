<?php
// /app/controllers/AchatsController.php

class AchatsController extends Controller {
    private $achatModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('achats_voir_liste')) die('Accès non autorisé.');
        $this->achatModel = $this->model('AchatModel');
    }

    // Affiche la liste des commandes fournisseurs
    public function index() {
        $achatModel = $this->model('AchatModel');
        $data = [
            'titre' => 'Commandes Fournisseurs',
            'commandes' => $achatModel->listerToutesLesCommandes()
        ];
        $this->view('achats/index', $data);
    }

    // Affiche le formulaire de création de commande
    public function creer() {
        $tiersModel = $this->model('TiersModel');
        $articleModel = $this->model('ArticleModel');
        $data = [
            'titre' => 'Créer une Commande Fournisseur',
            'fournisseurs' => $tiersModel->listerTiersParType('Fournisseur'),
            'articles' => $articleModel->listerArticles()
        ];
        $this->view('achats/creer', $data);
    }
    
    // Traite l'enregistrement de la commande
    public function enregistrer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $lignes = $_POST['lignes'] ?? [];
            if (empty($_POST['fournisseur_id']) || empty($lignes)) {
                rediriger('achats/creer');
                return;
            }

            $total_ht = 0;
            foreach ($lignes as $ligne) {
                $total_ht += $ligne['prix_achat'] * $ligne['quantite'];
            }

            $data_commande = [
                'numero_commande' => 'CF-' . date('Ymd-His'),
                'fournisseur_id' => $_POST['fournisseur_id'],
                'utilisateur_id' => $_SESSION['user_id'],
                'montant_ht' => $total_ht,
                'statut' => 'Commandé'
            ];

            $achatModel = $this->model('AchatModel');
            if ($achatModel->creerCommande($data_commande, $lignes)) {
                enregistrerLog("Création Commande Fournisseur: " . $data_commande['numero_commande']);
                rediriger('achats');
            } else {
                die('Erreur lors de l\'enregistrement de la commande.');
            }
        }
    }

     /**
     * NOUVEAU: Affiche la page pour réceptionner une commande.
     */
    public function recevoir($commande_id) {
        $commande = $this->achatModel->trouverCommandeParId($commande_id);
        if (!$commande || ($commande['statut'] != 'Commandé' && $commande['statut'] != 'Reçu Partiellement')) {
            rediriger('achats');
        }

        $lignes = $this->achatModel->listerLignesParCommandeId($commande_id);

        $data = [
            'titre' => 'Réception de la Commande ' . $commande['numero_commande'],
            'commande' => $commande,
            'lignes' => $lignes
        ];
        $this->view('achats/recevoir', $data);
    }

    /**
     * NOUVEAU: Traite le formulaire de réception.
     */
    public function enregistrerReception($commande_id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $lignes_recues = $_POST['lignes'] ?? [];
            if ($this->achatModel->receptionnerLignes($commande_id, $lignes_recues, $_SESSION['user_id'])) {
                enregistrerLog("Réception marchandise pour Commande ID: " . $commande_id);
                rediriger('achats');
            } else {
                die('Erreur lors de la réception de la marchandise.');
            }
        }
    }
        /**
     * NOUVEAU: Affiche les détails d'une commande spécifique.
     */
    public function voir($commande_id) {
        $commande = $this->achatModel->trouverCommandeParId($commande_id);
        if (!$commande) {
            rediriger('achats'); // Si la commande n'existe pas, retour à la liste
        }

        $lignes = $this->achatModel->listerLignesParCommandeId($commande_id);

        $data = [
            'titre' => 'Détails de la Commande ' . $commande['numero_commande'],
            'commande' => $commande,
            'lignes' => $lignes
        ];
        
        // On utilise la vue normale avec header/footer
        $this->view('achats/voir', $data);
    }
}