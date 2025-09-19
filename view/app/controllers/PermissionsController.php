<?php
// /app/controllers/PermissionsController.php

class PermissionsController extends Controller {
    private $permissionModel;

    public function __construct() {
        if (!estConnecte() || !Auth::can('permissions_gerer')) {
            die('Accès non autorisé.');
        }
        $this->permissionModel = $this->model('PermissionModel');
    }

    public function index() {
        $roleModel = $this->model('RoleModel');
        $data = [
            'titre' => 'Gestion des Permissions',
            'roles' => $roleModel->listerRoles(),
            'permissions' => $this->permissionModel->listerToutesLesPermissions(),
            'role_perms' => $this->permissionModel->listerPermissionsDeTousLesRoles()
        ];
        $this->view('permissions/index', $data);
    }

    public function sauvegarder() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->permissionModel->sauvegarderPermissions($_POST)) {
                enregistrerLog("Mise à jour des permissions pour tous les rôles", "CRITICAL");
                rediriger('permissions');
            } else {
                die('Erreur lors de la sauvegarde des permissions.');
            }
        }
    }
    // Affiche la liste des permissions définies
    public function liste() {
        $data = [
            'titre' => 'Liste des Permissions Définies',
            'permissions' => $this->permissionModel->listerToutesLesPermissions()
        ];
        $this->view('permissions/liste', $data);
    }

    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'slug' => trim($_POST['slug']),
                'description' => trim($_POST['description']),
                'module' => trim($_POST['module']),
            ];
            if ($this->permissionModel->creerPermission($data)) {
                enregistrerLog("Création de la permission: " . $data['slug'], "CRITICAL");
                rediriger('permissions/liste');
            }
        }
        $data = ['titre' => 'Créer une nouvelle Permission'];
        $this->view('permissions/creer', $data);
    }
    
    public function modifier($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'id' => $id,
                'slug' => trim($_POST['slug']),
                'description' => trim($_POST['description']),
                'module' => trim($_POST['module']),
            ];
            if ($this->permissionModel->modifierPermission($data)) {
                enregistrerLog("Modification de la permission ID $id: " . $data['slug'], "CRITICAL");
                rediriger('permissions/liste');
            }
        }
        $data = [
            'titre' => 'Modifier la Permission',
            'permission' => $this->permissionModel->trouverPermissionParId($id)
        ];
        $this->view('permissions/modifier', $data);
    }
     public function supprimer($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $perm = $this->permissionModel->trouverPermissionParId($id); // Pour le log
            if ($this->permissionModel->supprimerPermission($id)) {
                enregistrerLog("Suppression de la permission: " . $perm['slug'], "CRITICAL");
            } else {
                enregistrerLog("Tentative de suppression échouée de la permission: " . $perm['slug'], "WARNING");
            }
            rediriger('permissions/liste');
        }
    }
}