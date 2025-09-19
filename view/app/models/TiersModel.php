<?php
// /app/models/TiersModel.php

class TiersModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Récupère tous les tiers d'un certain type (Client ou Fournisseur).
     * @param string $type Le type de tiers à lister.
     * @return array La liste des tiers.
     */
    public function listerTiersParType($type = 'Client') {
        $stmt = $this->db->prepare("SELECT * FROM tiers WHERE type = :type ORDER BY nom_raison_sociale ASC");
        $stmt->execute(['type' => $type]);
        return $stmt->fetchAll();
    }

      public function creerTiers($data) {
        $sql = "INSERT INTO tiers (type, nom_raison_sociale, adresse, telephone, email, nif, rc, nis, art) 
                VALUES (:type, :nom_raison_sociale, :adresse, :telephone, :email, :nif, :rc, :nis, :art)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Récupère tous les tiers.
     */
    public function listerTousLesTiers() {
        $stmt = $this->db->query("SELECT * FROM tiers ORDER BY nom_raison_sociale ASC");
        return $stmt->fetchAll();
    }
    
    /**
     * Trouve un tiers par son ID.
     */
    public function trouverTiersParId($id) {
        $stmt = $this->db->prepare("SELECT * FROM tiers WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Met à jour un tiers existant.
     */
    public function modifierTiers($data) {
        $sql = "UPDATE tiers SET 
                    type = :type, 
                    nom_raison_sociale = :nom_raison_sociale, 
                    adresse = :adresse, 
                    telephone = :telephone, 
                    email = :email, 
                    nif = :nif, 
                    rc = :rc, 
                    nis = :nis, 
                    art = :art 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Supprime un tiers par son ID.
     */
    public function supprimerTiers($id) {
        // Attention: la suppression peut échouer si le tiers est lié à des factures.
        // On ajoutera une gestion de cette contrainte plus tard.
        try {
            $stmt = $this->db->prepare("DELETE FROM tiers WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            // Probablement une erreur de contrainte de clé étrangère
            return false;
        }
    }
    public function mettreAJourSoldeCredit($client_id, $montant) {
    $sql = "UPDATE tiers SET solde_credit = solde_credit + :montant WHERE id = :id";
    $stmt = $this->db->prepare($sql);
    return $stmt->execute([':montant' => $montant, ':id' => $client_id]);
}
}