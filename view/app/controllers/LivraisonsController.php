<?php
// /app/controllers/LivraisonsController.php

class LivraisonsController extends Controller {
    private $livraisonModel;
    private $livreurModel;
    private $factureModel;

    public function __construct() {
        if (!estConnecte()) {
            rediriger('utilisateurs/login');
        }
        // Le modèle principal pour ce contrôleur
        $this->livraisonModel = $this->model('LivraisonModel');
        // On a besoin du modèle Facture pour lire les commandes à livrer
        $this->factureModel = $this->model('FactureModel');
    }

    /**
     * Affiche la liste des factures en attente de préparation ou de livraison.
     * C'est le tableau de bord du magasinier.
     */
      public function index() {
        if (!Auth::can('livraisons_voir_liste')) die('Accès non autorisé.');
        
        $data = [
            'titre' => 'Tableau de Bord des Livraisons',
            'factures_a_preparer' => $this->factureModel->listerFacturesParStatutLivraison('En attente de préparation'),
            'bons_livraison' => $this->livraisonModel->listerTousLesBonsLivraison()
        ];
        $this->view('livraisons/index', $data);
    }

    /**
     * Affiche le détail d'une facture pour préparer le Bon de Livraison.
     */
   public function preparer($facture_id) {
    if (!Auth::can('livraisons_gerer')) die('Accès non autorisé.');

    $facture = $this->factureModel->trouverFactureParId($facture_id);
    if (!$facture) {
        rediriger('livraisons');
    }

    // NOUVEAU : On charge la liste des livreurs
    $livreurModel = $this->model('LivreurModel');

    $data = [
        'titre' => 'Préparer le Bon de Livraison pour la Facture ' . $facture['numero_facture'],
        'facture' => $facture,
        'lignes' => $this->factureModel->listerLignesParFactureId($facture_id),
        'livreurs' => $livreurModel->listerLivreursActifs() // On passe les livreurs à la vue
    ];
    $this->view('livraisons/preparer', $data);
}
    
    /**
     * Valide la livraison, crée le BL, et DÉCRÉMENTE le stock.
     */
    public function valider($facture_id) {
        if (!Auth::can('livraisons_gerer')) die('Accès non autorisé.');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $lignes_livrees = $_POST['lignes'] ?? [];
            
            // La méthode validerLivraison est la plus critique de ce module.
            $bl_id = $this->livraisonModel->validerLivraison($facture_id, $lignes_livrees, $_SESSION['user_id']);

            if ($bl_id) {
                enregistrerLog("Bon de Livraison #$bl_id créé et stock mis à jour pour Facture #$facture_id.");
                rediriger('livraisons');
            } else {
                die('Erreur lors de la validation de la livraison.');
            }
        }
    }

    /**
 * Affiche les détails d'un livreur et son historique de livraisons.
 */
public function voir($id) {
    $livreur = $this->livreurModel->trouverLivreurParId($id);
    if (!$livreur) {
        rediriger('livreurs');
    }

    // On charge le modèle des livraisons pour récupérer l'historique
    $livraisonModel = $this->model('LivraisonModel');
    $livraisons = $livraisonModel->listerBonsParLivreurId($id);

    $data = [
        'titre' => 'Détails du Livreur : ' . $livreur['nom_complet'],
        'livreur' => $livreur,
        'livraisons' => $livraisons
    ];
    
    $this->view('livreurs/voir', $data);
}
}