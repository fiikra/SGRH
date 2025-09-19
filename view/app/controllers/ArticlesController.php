<?php
// /app/controllers/ArticlesController.php

class ArticlesController extends Controller {
    private $articleModel;
    private $stockModel; // On ajoute le StockModel

    public function __construct() {
        if (!estConnecte() || !Auth::can('articles_voir_liste')) die('Accès non autorisé.');
        
        $this->articleModel = $this->model('ArticleModel');
        $this->stockModel = $this->model('StockModel'); // On l'instancie
   
    }

    // Affiche la liste des articles
    public function index() {
        $articles = $this->articleModel->listerArticles();
        $data = [
            'titre' => 'Gestion des Articles',
            'articles' => $articles
        ];
        $this->view('articles/index', $data);
    }

    // Gère la création d'un article (affichage du formulaire et traitement)
    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            
            $data = [
                'reference' => trim($_POST['reference']),
                'designation' => trim($_POST['designation']),
                'code_barre' => trim($_POST['code_barre']),
                'prix_achat_ht' => trim($_POST['prix_achat_ht']),
                'prix_vente_ht' => trim($_POST['prix_vente_ht']),
                'stock_alerte' => trim($_POST['stock_alerte']),
                'notes' => trim($_POST['notes']),
                    // Une checkbox non cochée n'est pas envoyée dans le POST, on utilise donc isset()
    'est_stocke' => isset($_POST['est_stocke']) ? 1 : 0 // <-- AJOUTER/MODIFIER CETTE LIGNE

                // On ajoutera la gestion des erreurs plus tard
            ];

            if ($this->articleModel->creerArticle($data)) {
                enregistrerLog("Création de l'article: " . $data['designation']);
                // Message de succès à implémenter
                rediriger('articles');
            } else {
                die('Erreur lors de la création de l\'article.');
            }

        } else {
            // Affiche le formulaire de création
            $data = ['titre' => 'Créer un nouvel article'];
            $this->view('articles/creer', $data);
        }
    }

    // Gère la modification d'un article
    public function modifier($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            
            $data = [
                'id' => $id,
                'reference' => trim($_POST['reference']),
                'designation' => trim($_POST['designation']),
                'code_barre' => trim($_POST['code_barre']),
                'prix_achat_ht' => trim($_POST['prix_achat_ht']),
                'prix_vente_ht' => trim($_POST['prix_vente_ht']),
                'stock_alerte' => trim($_POST['stock_alerte']),
                'notes' => trim($_POST['notes']),
                    // Une checkbox non cochée n'est pas envoyée dans le POST, on utilise donc isset()
    'est_stocke' => isset($_POST['est_stocke']) ? 1 : 0 // <-- AJOUTER/MODIFIER CETTE LIGNE

            ];

            if ($this->articleModel->modifierArticle($data)) {
                enregistrerLog("Modification de l'article ID: $id (" . $data['designation'] . ")");
                rediriger('articles');
            } else {
                die('Erreur lors de la modification.');
            }

        } else {
            $article = $this->articleModel->trouverArticleParId($id);
            if (!$article) {
                rediriger('articles');
            }

            $data = [
                'titre' => 'Modifier l\'article',
                'article' => $article
            ];
            $this->view('articles/modifier', $data);
        }
    }

     /**
     * NOUVEAU: Affiche les détails d'un article et son historique de stock.
     */
    public function voir($id) {
        $article = $this->articleModel->trouverArticleParId($id);
        if (!$article) {
            rediriger('articles');
        }

        // Récupérer l'historique des mouvements pour cet article
        $mouvements = $this->stockModel->listerMouvementsParArticle($id);

        $data = [
            'titre' => 'Détails de l\'article',
            'article' => $article,
            'mouvements' => $mouvements
        ];
        
        $this->view('articles/voir', $data);
    }

    // Gère la suppression (devrait être en POST pour la sécurité)
    public function supprimer($id) {
        // Pour plus de sécurité, on devrait utiliser une requête POST
        // et vérifier un token CSRF, mais pour l'instant, on fait simple.
        if($this->articleModel->supprimerArticle($id)){
            enregistrerLog("Suppression de l'article ID: $id", 'WARNING');
            rediriger('articles');
        } else {
            die('Erreur de suppression.');
        }
    }
}