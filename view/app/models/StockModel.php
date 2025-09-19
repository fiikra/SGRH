<?php
// /app/models/StockModel.php

class StockModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Enregistre un mouvement de stock ET met à jour la quantité dans la table articles.
     * C'est une opération critique qui utilise une transaction.
     * * @param array $data Données du mouvement (article_id, type_mouvement, quantite, utilisateur_id, etc.)
     * @return bool True si tout s'est bien passé, false sinon.
     */
    public function enregistrerMouvement($data) {
        // Déterminer si c'est une entrée ou une sortie pour le calcul
        $signe = 1; // Par défaut, c'est une entrée
        $sorties = ['sortie_vente', 'perte', 'retour_fournisseur'];
        if (in_array($data['type_mouvement'], $sorties)) {
            $signe = -1;
        }

        $quantite_calculee = $signe * $data['quantite'];
        
        try {
            // Démarrer une transaction : si une requête échoue, tout est annulé.
            $this->db->beginTransaction();

            // 1. Insérer le mouvement dans le journal
            $sql_mouvement = "INSERT INTO mouvements_stock (article_id, utilisateur_id, type_mouvement, quantite, prix_unitaire_ht, notes) 
                              VALUES (:article_id, :utilisateur_id, :type_mouvement, :quantite, :prix_unitaire_ht, :notes)";
            $stmt_mouvement = $this->db->prepare($sql_mouvement);
            $stmt_mouvement->execute([
                ':article_id' => $data['article_id'],
                ':utilisateur_id' => $data['utilisateur_id'],
                ':type_mouvement' => $data['type_mouvement'],
                ':quantite' => $data['quantite'], // Quantité toujours positive
                ':prix_unitaire_ht' => $data['prix_unitaire_ht'] ?? null,
                ':notes' => $data['notes'] ?? ''
            ]);

            // 2. Mettre à jour le stock actuel de l'article
            $sql_article = "UPDATE articles SET stock_actuel = stock_actuel + :quantite WHERE id = :article_id";
            $stmt_article = $this->db->prepare($sql_article);
            $stmt_article->execute([
                ':quantite' => $quantite_calculee,
                ':article_id' => $data['article_id']
            ]);

            // Si les deux requêtes ont réussi, on valide la transaction
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // En cas d'erreur, on annule tout
            $this->db->rollBack();
            // Idéalement, on devrait loguer l'erreur $e->getMessage()
            return false;
        }
    }
    
    /**
     * Récupère l'historique des mouvements pour un article donné.
     * @param int $article_id
     * @return array
     */
    public function listerMouvementsParArticle($article_id) {
        $sql = "SELECT m.*, u.nom_complet as utilisateur_nom 
                FROM mouvements_stock m
                JOIN utilisateurs u ON m.utilisateur_id = u.id
                WHERE m.article_id = :article_id 
                ORDER BY m.date_mouvement DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':article_id' => $article_id]);
        return $stmt->fetchAll();
    }
}