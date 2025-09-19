<?php
class FacturesController extends Controller {
     private $factureModel;
    private $tiersModel;
    private $venteModel;

    public function __construct() {
        if (!estConnecte()) rediriger('utilisateurs/login');
        $this->factureModel = $this->model('FactureModel');
        $this->tiersModel = $this->model('TiersModel');
        $this->venteModel = $this->model('VenteModel');
    }
   

    // Lister toutes les factures
  public function index() {
        if (!Auth::can('factures_voir_liste')) die('Accès non autorisé.');
        $data = [
            'titre' => 'Liste des Factures',
            'factures' => $this->factureModel->listerToutesLesFactures()
        ];
        $this->view('factures/index', $data);
    }

    public function creer() {
        if (!Auth::can('factures_creer')) die('Accès non autorisé.');
        $articleModel = $this->model('ArticleModel');
        $data = [
            'titre' => 'Créer une nouvelle facture',
            'clients' => $this->tiersModel->listerTiersParType('Client'),
            'articles' => $articleModel->listerArticles()
        ];
        $this->view('factures/creer', $data);
    }

    public function enregistrer() {
        if (!Auth::can('factures_creer')) die('Accès non autorisé.');
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $lignes = $_POST['lignes'] ?? [];
            if (empty($_POST['client_id']) || empty($lignes)) {
                rediriger('factures/creer');
                return;
            }

            // Calcul des totaux des articles
            $total_ht = 0; $total_tva = 0;
            foreach ($lignes as $ligne) {
                $total_ht += $ligne['prix_unitaire_ht'] * $ligne['quantite'];
                $total_tva += ($ligne['prix_unitaire_ht'] * $ligne['quantite']) * $ligne['taux_tva'];
            }
            
            // Ajout du coût de livraison et calcul du timbre
            $cout_livraison = (float)($_POST['cout_livraison'] ?? 0);
            $methode_paiement = $_POST['methode_paiement'];
            $timbre = ($methode_paiement == 'Especes') ? 1.00 : 0;
            
            $total_ttc = $total_ht + $total_tva + $cout_livraison + $timbre;
            $montant_paye = (float)$_POST['montant_paye'];
            $montant_credit = $total_ttc - $montant_paye;

            // Préparation des données pour le modèle
            $data_facture = [
                'type_document' => 'Facture',
                'total_ht' => $total_ht,
                'total_tva' => $total_tva,
                'timbre' => $timbre,
                'cout_livraison' => $cout_livraison,
                'total_ttc' => $total_ttc,
                'montant_paye' => $montant_paye,
                'methode_paiement' => $methode_paiement,
                'statut_paiement' => ($montant_credit > 0) ? 'En attente' : 'Payé',
                'statut_livraison' => 'En attente de préparation',
                'client_id' => $_POST['client_id'],
                'type_livraison' => $_POST['type_livraison'],
                'adresse_livraison' => $_POST['adresse_livraison'] ?? '',
                'utilisateur_id' => $_SESSION['user_id']
            ];
            
            // La méthode creerFacture a été modifiée pour ne plus toucher au stock
            $facture_id = $this->venteModel->creerFacture($data_facture, $lignes);
            
            if ($facture_id) {
                if ($montant_credit > 0) {
                    $this->tiersModel->mettreAJourSoldeCredit($_POST['client_id'], $montant_credit);
                }

                if (isset($_POST['preparer_livraison_immediatement']) && Auth::can('livraisons_gerer')) {
                    enregistrerLog("Facture #$facture_id créée. En attente de livraison.");
        rediriger('livraisons/preparer/' . $facture_id);
    } else {
        enregistrerLog("Facture #$facture_id créée. En attente de livraison.");
        rediriger('factures/voir/' . $facture_id);
    }
                
           
            } else { die('Erreur lors de la création de la facture.'); }
        }
    }

    /**
     * NOUVEAU: Affiche les détails d'une facture spécifique.
     */
    public function voir($facture_id) {
        $facture = $this->factureModel->trouverFactureParId($facture_id);
        if (!$facture) {
            rediriger('factures');
        }

        $lignes = $this->factureModel->listerLignesParFactureId($facture_id);

        $data = [
            'titre' => 'Détails de la Facture ' . $facture['numero_facture'],
            'facture' => $facture,
            'lignes' => $lignes
        ];
        
        $this->view('factures/voir', $data);
    }

    public function imprimer($id) {
        $factureModel = $this->model('FactureModel');
        $facture = $factureModel->trouverFactureParId($id);
        $lignes = $factureModel->listerLignesParFactureId($id);

        if (!$facture) {
            rediriger('factures');
        }

        $data = [
            'titre' => 'Facture N° ' . $facture['numero_facture'],
            'facture' => $facture,
            'lignes' => $lignes
        ];
        $this->simpleView('factures/imprimer', $data);
    }

}