<?php
// /app/models/VenteModel.php

class VenteModel {
    private $db;
    private $stockModel;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        // On a besoin de notre StockModel pour les sorties
        require_once ROOT . '/app/models/StockModel.php';
        $this->stockModel = new StockModel();
    }
    
    /**
     * Crée une facture complète (facture + lignes + mouvements de stock)
     * dans une transaction pour assurer la cohérence des données.
     * @param array $data Les données de la facture (client_id, totaux, etc.)
     * @param array $panier Le panier d'articles
     * @return int|false L'ID de la nouvelle facture si succès, false sinon.
     */
    public function creerFacture($data, $panier) {
        try {
            $this->db->beginTransaction();

            // 1. Créer l'enregistrement principal de la facture
            $sql_facture = "INSERT INTO factures (numero_facture, client_id, utilisateur_id,reparation_id, montant_ht, montant_tva, montant_ttc, montant_timbre, methode_paiement)
                            VALUES (:numero_facture,:type_document, :client_id, :utilisateur_id,:reparation_id, :montant_ht, :montant_tva, :montant_ttc, :montant_timbre, :methode_paiement:reference_paiement, :statut_paiement)";
            $stmt_facture = $this->db->prepare($sql_facture);
            $stmt_facture->execute([
                ':numero_facture' => 'FACT-' . date('Ymd-His'),
                ':type_document' => $data['type_document'], // Numéro simple pour l'instant
                ':client_id' => $data['client_id'] ?? null,
                ':utilisateur_id' => $_SESSION['user_id'],
                ':reparation_id' => $data['reparation_id'] ?? null,
                ':montant_ht' => $data['total_ht'],
                ':montant_tva' => $data['total_tva'],
                ':montant_ttc' => $data['total_ttc'],
                ':montant_timbre' => $data['timbre'],
                ':methode_paiement' => $data['methode_paiement'],
                ':reference_paiement'=> $data['reference_paiement'],
                ':statut_paiement'=> $data['statut_paiement'],

            ]);
            
            $facture_id = $this->db->lastInsertId();

            // 2. Insérer chaque ligne de la facture ET faire la sortie de stock
            $sql_ligne = "INSERT INTO facture_lignes (facture_id, article_id, designation, quantite, prix_unitaire_ht, cout_unitaire_ht, taux_tva)
              VALUES (:facture_id, :article_id, :designation, :quantite, :prix_unitaire_ht, :cout_unitaire_ht, :taux_tva)";
$stmt_ligne = $this->db->prepare($sql_ligne);

            foreach ($panier as $item) {
                // Insérer la ligne
                $stmt_ligne->execute([
                    ':facture_id' => $facture_id,
                    ':article_id' => $item['id'],
                    ':designation' => $item['designation'],
                    ':quantite' => $item['qte'],
                    ':prix_unitaire_ht' => $item['prix_vente_ht'],
                    ':cout_unitaire_ht' => $item['cout_achat_ht'],
                    ':taux_tva' => $item['tva_taux']
                ]);
                
                // On ne met à jour le stock QUE si l'article est géré en stock
                if ($item['est_stocke'] == 1) {
        $this->stockModel->enregistrerMouvement([
            'article_id' => $item['id'],
            'type_mouvement' => 'sortie_vente',
            'quantite' => $item['qte'],
            'utilisateur_id' => $_SESSION['user_id'],
            'notes' => 'Vente facture ID: ' . $facture_id
        ]);
    }
            }

            $this->db->commit();
            return $facture_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            // Logger l'erreur : error_log($e->getMessage());
            return false;
        }
    }
}