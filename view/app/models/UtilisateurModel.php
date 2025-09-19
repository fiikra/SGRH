<?php
// /app/models/UtilisateurModel.php

class UtilisateurModel {
    private $db;

    public function __construct() {
        // On récupère l'instance unique de la connexion PDO
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Trouve un utilisateur par son email.
     * @param string $email
     * @return mixed Retourne les données de l'utilisateur ou false s'il n'est pas trouvé.
     */
    public function trouverUtilisateurParEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM utilisateurs WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function creerUtilisateur($data) {
        $sql = "INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, role_id) VALUES (:nom_complet, :email, :mot_de_passe, :role_id)";
        $stmt = $this->db->prepare($sql);
        
        // Hachage du mot de passe
        $data['mot_de_passe'] = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
        
        return $stmt->execute([
            ':nom_complet' => $data['nom_complet'],
            ':email' => $data['email'],
            ':mot_de_passe' => $data['mot_de_passe'],
            ':role_id' => $data['role_id']
        ]);
    }

    /**
     * NOUVEAU: Récupère tous les utilisateurs avec le nom de leur rôle.
     */
    public function listerUtilisateurs() {
        $sql = "SELECT u.id, u.nom_complet, u.email, u.est_actif, r.nom_role 
                FROM utilisateurs u
                JOIN roles r ON u.role_id = r.id
                ORDER BY u.nom_complet ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * NOUVEAU: Trouve un utilisateur par son ID.
     */
    public function trouverUtilisateurParId($id) {
        $stmt = $this->db->prepare("SELECT * FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * NOUVEAU: Met à jour un utilisateur.
     */
    public function modifierUtilisateur($data) {
        // Gérer la mise à jour du mot de passe optionnelle
        if (!empty($data['mot_de_passe'])) {
            $data['mot_de_passe'] = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);
            $sql = "UPDATE utilisateurs SET nom_complet = :nom, email = :email, role_id = :role_id, est_actif = :est_actif, mot_de_passe = :mot_de_passe WHERE id = :id";
        } else {
            $sql = "UPDATE utilisateurs SET nom_complet = :nom, email = :email, role_id = :role_id, est_actif = :est_actif WHERE id = :id";
        }

        $stmt = $this->db->prepare($sql);
        $params = [
            ':id' => $data['id'],
            ':nom' => $data['nom_complet'],
            ':email' => $data['email'],
            ':role_id' => $data['role_id'],
            ':est_actif' => $data['est_actif']
        ];
        if (!empty($data['mot_de_passe'])) {
            $params[':mot_de_passe'] = $data['mot_de_passe'];
        }
        return $stmt->execute($params);
    }

    /**
     * NOUVEAU: Supprime un utilisateur.
     */
    public function supprimerUtilisateur($id) {
        $stmt = $this->db->prepare("DELETE FROM utilisateurs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }


       /**
     * NOUVEAU: Récupère les slugs de toutes les permissions pour un rôle donné.
     */
    public function getPermissionsPourRole($role_id) {
        $sql = "SELECT p.slug 
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = :role_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_id' => $role_id]);
        
        // On retourne un simple tableau de slugs, ex: ['pos_acces', 'articles_voir_liste']
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}