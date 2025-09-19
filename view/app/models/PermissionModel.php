<?php
// /app/models/PermissionModel.php

class PermissionModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function listerToutesLesPermissions() {
        return $this->db->query("SELECT * FROM permissions ORDER BY module, description ASC")->fetchAll();
    }
    
    // Récupère les permissions sous forme de [role_id => [perm_id1, perm_id2]]
    public function listerPermissionsDeTousLesRoles() {
        $role_perms = [];
        $results = $this->db->query("SELECT * FROM role_permissions")->fetchAll();
        foreach ($results as $row) {
            $role_perms[$row['role_id']][] = $row['permission_id'];
        }
        return $role_perms;
    }

    public function sauvegarderPermissions($data) {
        try {
            $this->db->beginTransaction();
            // 1. Vider toutes les permissions actuelles
            $this->db->exec("DELETE FROM role_permissions");

            // 2. Insérer les nouvelles permissions cochées
            $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
            $stmt = $this->db->prepare($sql);

            if (!empty($data['perms'])) {
                foreach ($data['perms'] as $role_id => $permission_ids) {
                    foreach ($permission_ids as $permission_id) {
                        $stmt->execute([':role_id' => $role_id, ':permission_id' => $permission_id]);
                    }
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }
     public function creerPermission($data) {
        $sql = "INSERT INTO permissions (slug, description, module) VALUES (:slug, :description, :module)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function trouverPermissionParId($id) {
        $stmt = $this->db->prepare("SELECT * FROM permissions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function modifierPermission($data) {
        $sql = "UPDATE permissions SET slug = :slug, description = :description, module = :module WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function supprimerPermission($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM permissions WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}