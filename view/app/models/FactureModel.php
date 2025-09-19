<?php
// /app/models/FactureModel.php

class FactureModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }



      public function listerToutesLesFactures() {
        $sql = "SELECT f.*, t.nom_raison_sociale 
                FROM factures f 
                LEFT JOIN tiers t ON f.client_id = t.id
                ORDER BY f.date_facturation DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    /**
     * Trouve une facture par son ID et récupère les infos du client associé.
     */
    public function trouverFactureParId($id) {
        $sql = "SELECT f.*, t.nom_raison_sociale, t.adresse, t.nif, t.rc 
                FROM factures f 
                LEFT JOIN tiers t ON f.client_id = t.id
                WHERE f.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }


    public function updateStatutPaiement($facture_id, $statut, $date_encaissement) {
    $sql = "UPDATE factures SET statut_paiement = :statut, date_encaissement = :date_encaissement WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([
        ':id' => $facture_id,
        ':statut' => $statut,
        ':date_encaissement' => $date_encaissement
    ]);
}

    /**
     * Récupère toutes les lignes (articles) d'une facture donnée.
     */
    public function listerLignesParFactureId($facture_id) {
        $sql = "SELECT * FROM facture_lignes WHERE facture_id = :facture_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':facture_id' => $facture_id]);
        return $stmt->fetchAll();
    }



    public function listerFacturesEnAttenteDePaiement() {
    $sql = "SELECT f.*, t.nom_raison_sociale 
            FROM factures f
            LEFT JOIN tiers t ON f.client_id = t.id
            WHERE f.statut_paiement = 'En attente'
            ORDER BY f.date_facturation ASC";
    return $this->db->query($sql)->fetchAll();
}



public function enregistrerEncaissement($facture_id, $data) {
    $sql = "UPDATE factures SET 
                statut_paiement = 'Payé', 
                date_encaissement = :date_encaissement,
                methode_paiement = :methode,
                reference_paiement = :reference
            WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([
        ':id' => $facture_id,
        ':date_encaissement' => date('Y-m-d'),
        ':methode' => $data['methode_paiement_encaissement'],
        ':reference' => $data['reference_paiement']
    ]);
}
}