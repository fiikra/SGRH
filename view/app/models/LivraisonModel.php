<?php
// /app/models/LivraisonModel.php

class LivraisonModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function validerLivraison($facture_id, $lignes_livrees, $magasinier_id,$livreur_id) {
        $factureModel = new FactureModel();
        $facture = $factureModel->trouverFactureParId($facture_id);

        try {
            $this->db->beginTransaction();

            // 1. Créer le Bon de Livraison principal
            $numero_bl = 'BL-' . date('Ymd-His');
            $sql_bl = "INSERT INTO bons_livraison (facture_id, numero_bl, statut, adresse_livraison, cout_livraison, magasinier_id,livreur_id)
                       VALUES (:fid, :num, :statut, :adresse, :cout, :mid)";
            $stmt_bl = $this->db->prepare($sql_bl);
            $stmt_bl->execute([
                ':fid' => $facture_id,
                ':num' => $numero_bl,
                ':statut' => 'En préparation',
                ':adresse' => $facture['adresse_livraison'],
                ':cout' => $facture['cout_livraison'],
                ':mid' => $magasinier_id,
                ':lid' => $livreur_id // On enregistre le livreur
            ]);
            $bon_livraison_id = $this->db->lastInsertId();

            // 2. Insérer les lignes du BL et décrémenter le stock
            $sql_ligne = "INSERT INTO bon_livraison_lignes (bon_livraison_id, article_id, quantite_livree) VALUES (:bl_id, :art_id, :qte)";
            $stmt_ligne = $this->db->prepare($sql_ligne);
            $stockModel = new StockModel();

            foreach ($lignes_livrees as $ligne) {
                if ($ligne['quantite_livree'] > 0) {
                    $stmt_ligne->execute([
                        ':bl_id' => $bon_livraison_id,
                        ':art_id' => $ligne['article_id'],
                        ':qte' => $ligne['quantite_livree']
                    ]);

                    // Mise à jour du stock
                    $stockModel->enregistrerMouvement([
                        'article_id' => $ligne['article_id'],
                        'type_mouvement' => 'sortie_vente',
                        'quantite' => $ligne['quantite_livree'],
                        'utilisateur_id' => $magasinier_id,
                        'notes' => "Sortie pour BL #$numero_bl"
                    ]);
                }
            }

            // 3. Mettre à jour le statut de la facture originale
            // (Ici on pourrait ajouter une logique pour "Partiellement livré")
            $sql_update_facture = "UPDATE factures SET statut_livraison = 'Livré' WHERE id = :facture_id";
            $stmt_update_facture = $this->db->prepare($sql_update_facture);
            $stmt_update_facture->execute([':facture_id' => $facture_id]);

            $this->db->commit();
            return $bon_livraison_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

 public function listerBonsParLivreurId($livreur_id, $date_debut = null, $date_fin = null) {
    $sql = "SELECT bl.*, f.numero_facture, f.montant_ttc as valeur_facture, t.nom_raison_sociale as client_nom
            FROM bons_livraison bl
            JOIN factures f ON bl.facture_id = f.id
            LEFT JOIN tiers t ON f.client_id = t.id
            WHERE bl.livreur_id = :livreur_id";

    // Ajout du filtre par date
    if ($date_debut && $date_fin) {
        $sql .= " AND bl.date_livraison BETWEEN :date_debut AND :date_fin";
    }

    $sql .= " ORDER BY bl.date_livraison DESC";
            
    $stmt = $this->db->prepare($sql);
    $params = [':livreur_id' => $livreur_id];
    if ($date_debut && $date_fin) {
        $params[':date_debut'] = $date_debut;
        $params[':date_fin'] = $date_fin;
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}
}