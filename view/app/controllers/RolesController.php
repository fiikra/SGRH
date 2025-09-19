<?php
// /app/controllers/RolesController.php

class RolesController extends Controller {
    private $roleModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('permissions_gerer')) {
            die('Accès non autorisé.');
        }
        $this->roleModel = $this->model('RoleModel');
    }

    public function index() {
        $data = [
            'titre' => 'Gestion des Rôles',
            'roles' => $this->roleModel->listerRoles()
        ];
        $this->view('roles/index', $data);
    }

    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $nom_role = trim($_POST['nom_role']);
            if (!empty($nom_role) && $this->roleModel->creerRole(['nom_role' => $nom_role])) {
                enregistrerLog("Création du rôle: " . $nom_role, "CRITICAL");
                rediriger('roles');
            }
        }
        $data = ['titre' => 'Créer un nouveau Rôle'];
        $this->view('roles/creer', $data);
    }

    public function modifier($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $nom_role = trim($_POST['nom_role']);
            if (!empty($nom_role) && $this->roleModel->modifierRole(['id' => $id, 'nom_role' => $nom_role])) {
                enregistrerLog("Modification du rôle ID $id en: " . $nom_role, "CRITICAL");
                rediriger('roles');
            }
        }
        $data = [
            'titre' => 'Modifier le Rôle',
            'role' => $this->roleModel->trouverRoleParId($id)
        ];
        $this->view('roles/modifier', $data);
    }

    public function supprimer($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $role = $this->roleModel->trouverRoleParId($id); // Pour le log
            if ($this->roleModel->supprimerRole($id)) {
                enregistrerLog("Suppression du rôle: " . $role['nom_role'], "CRITICAL");
            } else {
                enregistrerLog("Tentative de suppression échouée du rôle: " . $role['nom_role'], "WARNING");
            }
            rediriger('roles');
        }
    }
    
    public function voir($id) {
        $permissionModel = $this->model('PermissionModel');
        $role = $this->roleModel->trouverRoleParId($id);
        // Récupérer les permissions de ce rôle
        $role_perms_brut = $permissionModel->listerPermissionsDeTousLesRoles();
        $perms_ids_pour_ce_role = $role_perms_brut[$id] ?? [];
        $toutes_les_perms = $permissionModel->listerToutesLesPermissions();
        
        $perms_assignees = array_filter($toutes_les_perms, function($perm) use ($perms_ids_pour_ce_role) {
            return in_array($perm['id'], $perms_ids_pour_ce_role);
        });

        $data = [
            'titre' => 'Détails du Rôle: ' . $role['nom_role'],
            'role' => $role,
            'permissions' => $perms_assignees
        ];
        $this->view('roles/voir', $data);
    }
}