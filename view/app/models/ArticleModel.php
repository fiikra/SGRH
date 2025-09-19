<?php
// /app/models/ArticleModel.php

class ArticleModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Récupère tous les articles, triés par désignation.
     * @return array La liste des articles.
     */
    public function listerArticles() {
        $stmt = $this->db->query("SELECT * FROM articles ORDER BY designation ASC");
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouvel article.
     * @param array $data Les données de l'article.
     * @return bool True si succès, false sinon.
     */
    public function creerArticle($data) {
        $sql = "INSERT INTO articles (reference, designation, code_barre, prix_achat_ht, prix_vente_ht, stock_alerte, notes) 
                VALUES (:reference, :designation, :code_barre, :prix_achat_ht, :prix_vente_ht, :stock_alerte,:est_stocke, :notes)";
        
        $stmt = $this->db->prepare($sql);

        // Liaison des valeurs
        $stmt->bindParam(':reference', $data['reference']);
        $stmt->bindParam(':designation', $data['designation']);
        $stmt->bindParam(':code_barre', $data['code_barre']);
        $stmt->bindParam(':prix_achat_ht', $data['prix_achat_ht']);
        $stmt->bindParam(':prix_vente_ht', $data['prix_vente_ht']);
        $stmt->bindParam(':stock_alerte', $data['stock_alerte']);
        $stmt->bindParam(':est_stocke', $data['est_stocke']);
        $stmt->bindParam(':notes', $data['notes']);
        
        return $stmt->execute();
    }

    /**
     * Récupère un article par son ID.
     * @param int $id L'ID de l'article.
     * @return mixed Les données de l'article ou false.
     */
    public function trouverArticleParId($id) {
        $stmt = $this->db->prepare("SELECT * FROM articles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Met à jour un article existant.
     * @param array $data Les nouvelles données de l'article, incluant son ID.
     * @return bool True si succès, false sinon.
     */
    public function modifierArticle($data) {
        $sql = "UPDATE articles SET 
                    reference = :reference, 
                    designation = :designation, 
                    code_barre = :code_barre, 
                    prix_achat_ht = :prix_achat_ht, 
                    prix_vente_ht = :prix_vente_ht, 
                    stock_alerte = :stock_alerte, 
                    est_stocke = :est_stocke,
                    notes = :notes 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($data);
    }
    
    /**
     * Supprime un article par son ID.
     * @param int $id L'ID de l'article à supprimer.
     * @return bool True si succès, false sinon.
     */
    public function supprimerArticle($id) {
        $stmt = $this->db->prepare("DELETE FROM articles WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}