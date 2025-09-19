<?php
// /app/controllers/TiersController.php

class TiersController extends Controller {
    private $tiersModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('tiers_voir_liste')) die('Accès non autorisé.');
        $this->tiersModel = $this->model('TiersModel');
    }

    // Affiche la liste des tiers
    public function index() {
        $data = [
            'titre' => 'Gestion des Tiers',
            'tiers' => $this->tiersModel->listerTousLesTiers()
        ];
        $this->view('tiers/index', $data);
    }

    // Gère la création
    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            $data = [
                'type' => trim($_POST['type']),
                'nom_raison_sociale' => trim($_POST['nom_raison_sociale']),
                'adresse' => trim($_POST['adresse']),
                'telephone' => trim($_POST['telephone']),
                'email' => trim($_POST['email']),
                'nif' => trim($_POST['nif']),
                'rc' => trim($_POST['rc']),
                'nis' => trim($_POST['nis']),
                'art' => trim($_POST['art'])
            ];
            if ($this->tiersModel->creerTiers($data)) {
                enregistrerLog("Création du tiers: " . $data['nom_raison_sociale']);
                rediriger('tiers');
            } else {
                die('Erreur lors de la création du tiers.');
            }
        } else {
            $data = ['titre' => 'Créer un nouveau Tiers'];
            $this->view('tiers/creer', $data);
        }
    }

    // Gère la modification
    public function modifier($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            $data = [
                'id' => $id,
                'type' => trim($_POST['type']),
                'nom_raison_sociale' => trim($_POST['nom_raison_sociale']),
                'adresse' => trim($_POST['adresse']),
                'telephone' => trim($_POST['telephone']),
                'email' => trim($_POST['email']),
                'nif' => trim($_POST['nif']),
                'rc' => trim($_POST['rc']),
                'nis' => trim($_POST['nis']),
                'art' => trim($_POST['art'])
            ];
            if ($this->tiersModel->modifierTiers($data)) {
                enregistrerLog("Modification du tiers ID: $id (" . $data['nom_raison_sociale'] . ")");
                rediriger('tiers');
            } else {
                die('Erreur lors de la modification.');
            }
        } else {
            $tiers = $this->tiersModel->trouverTiersParId($id);
            if (!$tiers) rediriger('tiers');
            $data = [
                'titre' => 'Modifier le Tiers',
                'tiers' => $tiers
            ];
            $this->view('tiers/modifier', $data);
        }
    }

    // Gère la suppression
    public function supprimer($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->tiersModel->supprimerTiers($id)) {
                enregistrerLog("Suppression du tiers ID: $id", 'WARNING');
                rediriger('tiers');
            } else {
                // Gérer l'erreur (ex: afficher un message que le tiers est utilisé)
                die('Impossible de supprimer ce tiers car il est lié à des factures.');
            }
        }
    }
}