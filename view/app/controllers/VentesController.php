<?php
// /app/controllers/VentesController.php

class VentesController extends Controller {
    private $articleModel;
    private $venteModel;
    private $tiersModel; // Ajouter le modèle Tiers

    public function __construct() {
        if (!estConnecte() || !Auth::can('pos_utiliser')) die('Accès non autorisé.');
        $this->articleModel = $this->model('ArticleModel');
        $this->venteModel = $this->model('VenteModel');
        $this->tiersModel = $this->model('TiersModel'); // Instancier
    }

    // Affiche l'interface du Point de Vente
    
    public function pos() {
        if (!isset($_SESSION['panier'])) $_SESSION['panier'] = [];

        $data = [
            'titre' => 'Point de Vente',
            'articles' => $this->articleModel->listerArticles(),
            'clients' => $this->tiersModel->listerTiersParType('Client')
            // Le panier sera chargé via la vue partielle
        ];
        $this->view('ventes/pos', $data);
    }
    
    // Ajoute un article au panier (sera appelé en AJAX plus tard)
    public function ajouterAuPanier($id) {
        $article = $this->articleModel->trouverArticleParId($id);
        if ($article) {
            if (isset($_SESSION['panier'][$id])) {
                $_SESSION['panier'][$id]['qte']++;
            } else {
                $_SESSION['panier'][$id] = [
                    'id' => $id,
                    'designation' => $article['designation'],
                    'prix_vente_ht' => $article['prix_vente_ht'],
                    'tva_taux' => $article['tva_taux'],
                    'qte' => 1
                ];
            }
        }
        rediriger('ventes/pos');
    }
/**
     * API: Affiche simplement le contenu actuel du panier.
     * Appelé par AJAX au chargement initial de la page POS.
     */
    public function api_afficherPanier() {
        // Le panier est déjà dans la session, nous avons juste besoin
        // de passer les autres données requises par la vue partielle, comme les clients.
        $data['clients'] = $this->tiersModel->listerTiersParType('Client');
        
        // On charge la vue partielle qui lira directement $_SESSION['panier']
        require_once ROOT . '/app/views/ventes/_panier.php';
    }
    
    /**
     * API: Ajoute un article au panier et renvoie la vue du panier mise à jour.
     */
    // Vide complètement le panier
    public function viderPanier() {
        $_SESSION['panier'] = [];
        rediriger('ventes/pos');
    }

    /**
     * API: Ajoute un article au panier et renvoie la vue du panier mise à jour.
     */
    public function api_ajouterAuPanier($id) {
        $article = $this->articleModel->trouverArticleParId($id);
        if ($article) {
             // Vérifier si le stock est suffisant
            $stock_dispo = $article['stock_actuel'];
            $qte_panier = isset($_SESSION['panier'][$id]) ? $_SESSION['panier'][$id]['qte'] : 0;
            if ($stock_dispo > $qte_panier) {
                if (isset($_SESSION['panier'][$id])) {
                    $_SESSION['panier'][$id]['qte']++;
                } else {
                    $_SESSION['panier'][$id] = [ 'id' => $id, 'designation' => $article['designation'], 'prix_vente_ht' => $article['prix_vente_ht'],'cout_achat_ht' => $article['prix_achat_ht'], 'tva_taux' => $article['tva_taux'],'est_stocke' => $article['est_stocke'], 'qte' => 1 ];
                }
            } else {
                // Optionnel: renvoyer une erreur de stock insuffisant
            }
        }
        // Charger uniquement la vue partielle du panier
        $data['clients'] = $this->tiersModel->listerTiersParType('Client');
        require_once ROOT . '/app/views/ventes/_panier.php';
    }
/**
     * API: Vide le panier et renvoie la vue du panier vide.
     */
     public function api_viderPanier() {
        $_SESSION['panier'] = [];
        $data['clients'] = $this->tiersModel->listerTiersParType('Client');
        require_once ROOT . '/app/views/ventes/_panier.php';
    }

    // Traite la vente finale
   public function finaliserVente() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['panier'])) {
            $panier = $_SESSION['panier'];
            $is_walker_mode = isset($_POST['mode_walker']) && $_POST['mode_walker'] == '1';
            $methode_paiement = $_POST['methode_paiement'];
            
            // Calcul des totaux
            $total_ht = 0;
            foreach ($panier as $item) { $total_ht += $item['prix_vente_ht'] * $item['qte']; }

            $total_tva = 0;
            $timbre = 0;
            $type_document = $is_walker_mode ? 'Ticket de Caisse' : 'Facture';

            if (!$is_walker_mode) {
                foreach ($panier as $item) { $total_tva += ($item['prix_vente_ht'] * $item['tva_taux']) * $item['qte']; }
                // NOUVELLE REGLE: Le timbre ne s'applique QUE pour les espèces
                if ($methode_paiement == 'Especes') {
                    $timbre = 1.00; // Ou votre règle de timbre
                }
            }

            $total_ttc = $total_ht + $total_tva + $timbre;
            $statut_paiement = ($methode_paiement == 'Virement') ? 'En attente' : 'Payé';

            $data_facture = [
                'type_document' => $type_document,
                'total_ht' => $total_ht,
                'total_tva' => $total_tva,
                'timbre' => $timbre,
                'total_ttc' => $total_ttc,
                'methode_paiement' => $methode_paiement,
                'reference_paiement' => trim($_POST['reference_paiement'] ?? ''),
                'statut_paiement' => $statut_paiement,
                'client_id' => $_POST['client_id'] == '0' ? null : $_POST['client_id']
            ];

            $facture_id = $this->venteModel->creerFacture($data_facture, $panier);
            if ($facture_id) {
                $_SESSION['panier'] = [];
                enregistrerLog("Vente encaissée ($type_document) #$facture_id");
                rediriger('factures/imprimer/' . $facture_id);
            }
        }
    }

    public function finaliserVenteACredit() {
        if (!Auth::can('ventes_credit_accorder')) die('Accès non autorisé.');

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_SESSION['panier'])) {
            $panier = $_SESSION['panier'];
            $client_id = $_POST['client_id'];

            // Une vente à crédit nécessite obligatoirement un client enregistré
            if ($client_id == '0') { die("Erreur: Impossible de faire une vente à crédit sans client."); }
            
            $total_ht = 0;
            foreach ($panier as $item) { $total_ht += $item['prix_vente_ht'] * $item['qte']; }

            $total_tva = 0;
            foreach ($panier as $item) { $total_tva += ($item['prix_vente_ht'] * $item['tva_taux']) * $item['qte']; }
            
            $timbre = 0; // Pas de timbre sur une vente à crédit (non payée en espèces)
            $total_ttc = $total_ht + $total_tva + $timbre;

            $data_facture = [
                'type_document' => 'Facture',
                'total_ht' => $total_ht,
                'total_tva' => $total_tva,
                'timbre' => $timbre,
                'total_ttc' => $total_ttc,
                'methode_paiement' => 'Credit', // Méthode de paiement spécifique
                'statut_paiement' => 'En attente', // Le paiement est en attente
                'client_id' => $client_id
            ];

            $facture_id = $this->venteModel->creerFacture($data_facture, $panier);
            
            if ($facture_id) {
                // Mettre à jour le solde du client
                $tiersModel = $this->model('TiersModel');
                $tiersModel->mettreAJourSoldeCredit($client_id, $total_ttc);
                
                $_SESSION['panier'] = [];
                enregistrerLog("Vente à crédit #$facture_id pour client ID $client_id");
                rediriger('factures/imprimer/' . $facture_id);
            }
        }
    }
    
    // Méthode pour imprimer (on la créera plus tard)
    public function imprimer($id_facture) {
        echo "<h1>Facture N°" . htmlspecialchars($id_facture) . "</h1>";
        echo "<p>Ceci est la page d'impression. À développer.</p>";
        echo '<a href="/ventes/pos">Nouvelle Vente</a>';
    }




    
}