<?php
// /app/models/LivreurModel.php

class LivreurModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function creerLivreur($data) {
        $sql = "INSERT INTO livreurs (nom_complet, telephone, est_actif) VALUES (:nom, :tel, :actif)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function listerTousLesLivreurs() {
        return $this->db->query("SELECT * FROM livreurs ORDER BY nom_complet ASC")->fetchAll();
    }
    
    public function listerLivreursActifs() {
        return $this->db->query("SELECT * FROM livreurs WHERE est_actif = 1 ORDER BY nom_complet ASC")->fetchAll();
    }

    public function trouverLivreurParId($id) {
        $stmt = $this->db->prepare("SELECT * FROM livreurs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function modifierLivreur($data) {
        $sql = "UPDATE livreurs SET nom_complet = :nom, telephone = :tel, est_actif = :actif WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function supprimerLivreur($id) {
        // Attention: la suppression peut échouer si le livreur est lié à des BL.
        try {
            $stmt = $this->db->prepare("DELETE FROM livreurs WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}