<?php
// /app/models/AvoirModel.php

class AvoirModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crée un avoir, ajuste le stock et le solde client.
     * C'est l'opération la plus critique du module.
     */
    public function creerAvoir($facture_originale_id, $lignes_retournees, $notes, $utilisateur_id) {
        // On aura besoin de plusieurs modèles
        $factureModel = new FactureModel();
        $stockModel = new StockModel();
        $tiersModel = new TiersModel();

        $facture_originale = $factureModel->trouverFactureParId($facture_originale_id);
        if (!$facture_originale) return false;

        try {
            $this->db->beginTransaction();

            $total_ht_avoir = 0;
            $total_tva_avoir = 0;
            
            // 1. Calculer le montant de l'avoir
            $lignes_pour_insertion = [];
            foreach ($lignes_retournees as $ligne) {
                if (isset($ligne['quantite_retournee']) && $ligne['quantite_retournee'] > 0) {
                    $total_ligne_ht = $ligne['prix_unitaire_ht'] * $ligne['quantite_retournee'];
                    $tva_ligne = $total_ligne_ht * $ligne['taux_tva'];
                    
                    $total_ht_avoir += $total_ligne_ht;
                    $total_tva_avoir += $tva_ligne;
                    
                    $lignes_pour_insertion[] = $ligne;
                }
            }

            if (empty($lignes_pour_insertion)) throw new Exception("Aucun article sélectionné pour le retour.");
            
            $total_ttc_avoir = $total_ht_avoir + $total_tva_avoir;

            // 2. Créer l'enregistrement principal de l'avoir
            $numero_avoir = 'AV-' . date('Ymd-His');
            $sql_avoir = "INSERT INTO factures_avoir (facture_originale_id, numero_avoir, client_id, montant_ht, montant_tva, montant_ttc, notes, utilisateur_id)
                          VALUES (:facture_id, :num, :client_id, :ht, :tva, :ttc, :notes, :user_id)";
            $stmt_avoir = $this->db->prepare($sql_avoir);
            $stmt_avoir->execute([
                ':facture_id' => $facture_originale_id, ':num' => $numero_avoir, ':client_id' => $facture_originale['client_id'],
                ':ht' => $total_ht_avoir, ':tva' => $total_tva_avoir, ':ttc' => $total_ttc_avoir,
                ':notes' => $notes, ':user_id' => $utilisateur_id
            ]);
            $avoir_id = $this->db->lastInsertId();
            
            // 3. Insérer les lignes de l'avoir et ajuster le stock
            $sql_ligne = "INSERT INTO facture_avoir_lignes (avoir_id, article_id, designation, quantite_retournee, prix_unitaire_ht, remis_en_stock)
                          VALUES (:avoir_id, :article_id, :designation, :quantite, :prix, :stock)";
            $stmt_ligne = $this->db->prepare($sql_ligne);

            foreach ($lignes_pour_insertion as $ligne) {
                $remis_en_stock = isset($ligne['remis_en_stock']) ? 1 : 0;
                $stmt_ligne->execute([
                    ':avoir_id' => $avoir_id, ':article_id' => $ligne['article_id'], ':designation' => $ligne['designation'],
                    ':quantite' => $ligne['quantite_retournee'], ':prix' => $ligne['prix_unitaire_ht'], ':stock' => $remis_en_stock
                ]);
                
                if ($remis_en_stock) {
                    $stockModel->enregistrerMouvement([
                        'article_id' => $ligne['article_id'], 'type_mouvement' => 'retour_client',
                        'quantite' => $ligne['quantite_retournee'], 'utilisateur_id' => $utilisateur_id,
                        'notes' => "Retour pour Avoir #$numero_avoir"
                    ]);
                }
            }

            // 4. Mettre à jour le solde crédit du client (diminuer sa dette)
            if ($facture_originale['client_id']) {
                $tiersModel->mettreAJourSoldeCredit($facture_originale['client_id'], -$total_ttc_avoir);
            }

            $this->db->commit();
            return $avoir_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function listerTousLesAvoirs() {
        $sql = "SELECT av.*, f.numero_facture as num_fact_orig, t.nom_raison_sociale as client_nom
                FROM factures_avoir av
                JOIN factures f ON av.facture_originale_id = f.id
                LEFT JOIN tiers t ON av.client_id = t.id
                ORDER BY av.date_avoir DESC";
        return $this->db->query($sql)->fetchAll();
    }
    public function trouverAvoirParId($avoir_id) {
    $sql = "SELECT av.*, f.numero_facture as num_fact_orig, t.nom_raison_sociale as client_nom, u.nom_complet as utilisateur_nom
            FROM factures_avoir av
            JOIN factures f ON av.facture_originale_id = f.id
            JOIN utilisateurs u ON av.utilisateur_id = u.id
            LEFT JOIN tiers t ON av.client_id = t.id
            WHERE av.id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $avoir_id]);
    return $stmt->fetch();
}

public function listerLignesParAvoirId($avoir_id) {
    $sql = "SELECT * FROM facture_avoir_lignes WHERE avoir_id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':id' => $avoir_id]);
    return $stmt->fetchAll();
}
}