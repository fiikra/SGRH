<?php
// /app/controllers/StockController.php

class StockController extends Controller {
    private $stockModel;
    private $articleModel;

    public function __construct() {
        if (!estConnecte()) {
            rediriger('utilisateurs/login');
        }
        $this->stockModel = $this->model('StockModel');
        $this->articleModel = $this->model('ArticleModel'); // On en a besoin pour récupérer les infos de l'article
    }

    // Affiche le formulaire pour ajouter du stock
    public function entree($article_id) {
        $article = $this->articleModel->trouverArticleParId($article_id);
        if (!$article) rediriger('articles');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'article_id' => $article_id,
                'type_mouvement' => 'entree', // Mouvement manuel
                'quantite' => (int)$_POST['quantite'],
                'prix_unitaire_ht' => $article['prix_achat_ht'], // On prend le prix d'achat par défaut
                'utilisateur_id' => $_SESSION['user_id'],
                'notes' => trim($_POST['notes'])
            ];

            if ($data['quantite'] > 0 && $this->stockModel->enregistrerMouvement($data)) {
                rediriger('articles');
            } else {
                // Gérer l'erreur
                die('Erreur lors de l\'enregistrement du mouvement.');
            }
        } else {
            $data = [
                'titre' => 'Entrée de Stock',
                'article' => $article
            ];
            $this->view('stock/entree', $data);
        }
    }

    // Affiche la page d'historique pour un article
    public function historique($article_id) {
        $article = $this->articleModel->trouverArticleParId($article_id);
        if (!$article) rediriger('articles');

        $mouvements = $this->stockModel->listerMouvementsParArticle($article_id);

        $data = [
            'titre' => 'Historique de stock pour : ' . htmlspecialchars($article['designation']),
            'article' => $article,
            'mouvements' => $mouvements
        ];

        $this->view('stock/historique', $data);
    }

    // On pourrait ajouter une méthode `sortie($article_id)` sur le même principe pour les pertes.
}