<?php
// /app/controllers/ServicesController.php

class ServicesController extends Controller {
private $serviceModel;
    public function __construct() {
        // Sécurité à plusieurs niveaux
        if (!estConnecte() || get_param('module_services_active') != '1' || !Auth::can('services_gerer')) {
            die('Accès non autorisé.');
        }
        $this->serviceModel = $this->model('ServiceModel');
    }

    
 public function index() {
        $data = [
            'titre' => 'Suivi des Services & Réparations',
            'reparations' => $this->serviceModel->listerToutesLesReparations()
        ];
        $this->view('services/index', $data);
    }

    public function voir($id) {
        $reparation = $this->serviceModel->trouverReparationParId($id);
        if (!$reparation) {
            rediriger('services');
        }
        $data = [
            'titre' => 'Détails du Bon: ' . $reparation['numero_bon'],
            'reparation' => $reparation
        ];
        $this->view('services/voir', $data);
    }
    
    public function mettreAJourStatut($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $statut = $_POST['statut'];
            $notes = $_POST['notes_technicien'];
            if ($this->serviceModel->updateStatut($id, $statut, $notes)) {
                enregistrerLog("Statut du Bon #$id changé à: $statut");
                rediriger('services/voir/' . $id);
            }
        }
    }
    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            $data = [
                'numero_bon' => 'BR-' . date('Ymd-His'), // Bon de Réception
                'nom_client' => trim($_POST['nom_client']),
                'telephone_client' => trim($_POST['telephone_client']),
                'email_client' => trim($_POST['email_client']),
                'type_appareil' => trim($_POST['type_appareil']),
                'panne_declaree' => trim($_POST['panne_declaree']),
                'technicien_id' => $_SESSION['user_id']
            ];

            $serviceModel = $this->model('ServiceModel');
            if ($serviceModel->creerBonReception($data)) {
                enregistrerLog("Création du Bon de Réception: " . $data['numero_bon']);
                // Rediriger vers la page de détails ou d'impression du bon
                rediriger('services'); 
            } else {
                die('Erreur lors de la création du bon.');
            }
        }

        $data = ['titre' => 'Nouveau Bon de Réception'];
        $this->view('services/creer', $data);
    }
     /**
     * Affiche l'interface de facturation pour une réparation terminée.
     */
    public function facturer($id) {
        $reparation = $this->serviceModel->trouverReparationParId($id);
        if (!$reparation || $reparation['statut'] !== 'Terminé') {
            // On ne peut facturer qu'un service terminé
            rediriger('services/voir/' . $id);
        }

        // On a besoin de la liste des articles pour les pièces détachées et les services
        $articleModel = $this->model('ArticleModel');

        $data = [
            'titre' => 'Facturer le Bon de Réception: ' . $reparation['numero_bon'],
            'reparation' => $reparation,
            'articles' => $articleModel->listerArticles()
        ];

        $this->view('services/facturer', $data);
    }

    /**
     * Enregistre la facture pour un service et met à jour les statuts.
     */
    public function enregistrerFactureService($reparation_id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $lignes = $_POST['lignes'] ?? [];
            $reparation = $this->serviceModel->trouverReparationParId($reparation_id);

            if (empty($reparation) || empty($lignes)) {
                rediriger('services');
                return;
            }

            // On utilise la même logique que pour une facture standard
            $panier_formatte = [];
            $total_ht = 0;
            $total_tva = 0;

            foreach ($lignes as $ligne) {
                $panier_formatte[$ligne['article_id']] = [
                    'id' => $ligne['article_id'],
                    'designation' => $ligne['designation'],
                    'prix_vente_ht' => $ligne['prix_unitaire_ht'],
                    'cout_achat_ht' => $ligne['cout_unitaire_ht'],
                    'tva_taux' => $ligne['taux_tva'],
                    'qte' => $ligne['quantite']
                ];
                $total_ht += $ligne['prix_unitaire_ht'] * $ligne['quantite'];
                $total_tva += ($ligne['prix_unitaire_ht'] * $ligne['quantite']) * $ligne['taux_tva'];
            }

            $total_ttc = $total_ht + $total_tva;
            $timbre = ($total_ttc > 0) ? 1.00 : 0;

            $data_facture = [
                'total_ht' => $total_ht,
                'total_tva' => $total_tva,
                'total_ttc' => $total_ttc + $timbre,
                'timbre' => $timbre,
                'methode_paiement' => 'Especes', // ou un champ du formulaire
                'reparation_id' => $reparation_id, // L'ID de la réparation
                // Pas de client_id, car l'info est sur le bon de réparation
            ];
            
            $venteModel = $this->model('VenteModel');
            $facture_id = $venteModel->creerFacture($data_facture, $panier_formatte);
            
            if ($facture_id) {
                // Si la facture est créée, on met à jour le statut du service
                $this->serviceModel->updateStatut($reparation_id, 'Restitué', 'Facture ' . $facture_id . ' générée.');
                enregistrerLog("Facturation du service Bon #$reparation_id (Facture ID: $facture_id).");
                rediriger('factures/imprimer/' . $facture_id);
            } else {
                die('Erreur critique lors de la création de la facture de service.');
            }
        }
    }
}