<?php
// /app/models/RoleModel.php

class RoleModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Récupère tous les rôles disponibles.
     * @return array La liste des rôles.
     */
     public function creerRole($data) {
        $sql = "INSERT INTO roles (nom_role) VALUES (:nom_role)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':nom_role' => $data['nom_role']]);
    }

    public function listerRoles() {
        return $this->db->query("SELECT * FROM roles ORDER BY nom_role ASC")->fetchAll();
    }
    
    public function trouverRoleParId($id) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function modifierRole($data) {
        $sql = "UPDATE roles SET nom_role = :nom_role WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $data['id'], ':nom_role' => $data['nom_role']]);
    }
    
    public function supprimerRole($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM roles WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Ne peut pas être supprimé car lié à des utilisateurs ou des permissions
            return false;
        }
    }
}