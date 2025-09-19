<?php
// /app/models/RapportModel.php

class RapportModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getRapportVentes($date_debut, $date_fin) {
        $sql = "SELECT 
                    f.date_facturation, 
                    fl.designation, 
                    fl.quantite, 
                    fl.prix_unitaire_ht, 
                    fl.cout_unitaire_ht,
                    (fl.prix_unitaire_ht * fl.quantite) as chiffre_affaires_ligne,
                    (fl.cout_unitaire_ht * fl.quantite) as cout_total_ligne,
                    ((fl.prix_unitaire_ht - fl.cout_unitaire_ht) * fl.quantite) as benefice_ligne
                FROM facture_lignes fl
                JOIN factures f ON fl.facture_id = f.id
                WHERE f.date_facturation BETWEEN :date_debut AND :date_fin
                ORDER BY f.date_facturation DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
        return $stmt->fetchAll();
    }

    public function getRapportServices($date_debut, $date_fin) {
    // Cette requête sélectionne uniquement les lignes de factures liées à une réparation
    $sql = "SELECT ... 
            FROM facture_lignes fl
            JOIN factures f ON fl.facture_id = f.id
            WHERE f.reparation_id IS NOT NULL 
            AND f.date_facturation BETWEEN :date_debut AND :date_fin";
    // ...
}

public function getRapportTresorerie($date_debut, $date_fin) {
    $sql = "SELECT 
                methode_paiement,
                statut_paiement,
                SUM(montant_ttc) as total_par_groupe
            FROM factures
            WHERE date_facturation BETWEEN :date_debut AND :date_fin
            GROUP BY methode_paiement, statut_paiement";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    return $stmt->fetchAll();
}
public function getRapportLivraisons($date_debut, $date_fin) {
    $sql = "SELECT bl.*, l.nom_complet as livreur_nom, f.montant_ttc as valeur_facture
            FROM bons_livraison bl
            LEFT JOIN livreurs l ON bl.livreur_id = l.id
            JOIN factures f ON bl.facture_id = f.id
            WHERE bl.date_livraison BETWEEN :date_debut AND :date_fin
            ORDER BY bl.date_livraison DESC";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':date_debut' => $date_debut, ':date_fin' => $date_fin]);
    return $stmt->fetchAll();
}
}