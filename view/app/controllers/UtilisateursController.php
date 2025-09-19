<?php
// /app/controllers/UtilisateursController.php

class UtilisateursController extends Controller {
    
    private $utilisateurModel;

    public function __construct() {
        $this->utilisateurModel = $this->model('UtilisateurModel');
        // Sécuriser toutes les méthodes sauf le login/logout
        if (!in_array(debug_backtrace()[1]['function'], ['login', 'logout'])) {
            if (!estConnecte()) {
                rediriger('utilisateurs/login');
            }
        }
    }

    /**
     * NOUVEAU: Affiche la liste des utilisateurs.
     */
    public function index() {
        $data = [
            'titre' => 'Gestion des Utilisateurs',
            'utilisateurs' => $this->utilisateurModel->listerUtilisateurs()
        ];
        $this->view('utilisateurs/index', $data);
    }

    /**
     * NOUVEAU: Gère la création d'un utilisateur.
     */
    public function creer() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            $data = [
                'nom_complet' => trim($_POST['nom_complet']),
                'email' => trim($_POST['email']),
                'mot_de_passe' => trim($_POST['mot_de_passe']),
                'role_id' => trim($_POST['role_id']),
            ];

            if ($this->utilisateurModel->creerUtilisateur($data)) {
                enregistrerLog("Création de l'utilisateur : " . $data['email']);
                rediriger('utilisateurs');
            } else { die('Erreur de création.'); }

        } else {
            $roleModel = $this->model('RoleModel');
            $data = [
                'titre' => 'Créer un Utilisateur',
                'roles' => $roleModel->listerRoles()
            ];
            $this->view('utilisateurs/creer', $data);
        }
    }

    /**
     * NOUVEAU: Gère la modification d'un utilisateur.
     */
    public function modifier($id) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
            $data = [
                'id' => $id,
                'nom_complet' => trim($_POST['nom_complet']),
                'email' => trim($_POST['email']),
                'mot_de_passe' => trim($_POST['mot_de_passe']), // Laisser vide pour ne pas changer
                'role_id' => trim($_POST['role_id']),
                'est_actif' => isset($_POST['est_actif']) ? 1 : 0
            ];

            if ($this->utilisateurModel->modifierUtilisateur($data)) {
                enregistrerLog("Modification de l'utilisateur ID: $id (" . $data['email'] . ")");
                rediriger('utilisateurs');
            } else { die('Erreur de modification.'); }

        } else {
            $roleModel = $this->model('RoleModel');
            $data = [
                'titre' => 'Modifier un Utilisateur',
                'utilisateur' => $this->utilisateurModel->trouverUtilisateurParId($id),
                'roles' => $roleModel->listerRoles()
            ];
            $this->view('utilisateurs/modifier', $data);
        }
    }

    /**
     * NOUVEAU: Gère la suppression d'un utilisateur.
     */
    public function supprimer($id) {
        // Empêcher l'utilisateur de se supprimer lui-même
        if ($id == $_SESSION['user_id']) {
            rediriger('utilisateurs');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $user = $this->utilisateurModel->trouverUtilisateurParId($id); // Pour le log
            if ($this->utilisateurModel->supprimerUtilisateur($id)) {
                enregistrerLog("Suppression de l'utilisateur: " . $user['email'], 'WARNING');
                rediriger('utilisateurs');
            } else { die('Erreur de suppression.'); }
        }
    }


     /**
     * NOUVEAU: Affiche les détails d'un utilisateur et ses activités.
     */
    public function voir($id) {
        $utilisateur = $this->utilisateurModel->trouverUtilisateurParId($id);
        if (!$utilisateur) {
            rediriger('utilisateurs');
        }

        // On va chercher les logs pour cet utilisateur
        $logModel = $this->model('LogModel');
        $logs = $logModel->listerLogsParUtilisateur($id);

        // On récupère aussi le nom du rôle
        $roleModel = $this->model('RoleModel');
        $roles = $roleModel->listerRoles();
        $nom_role = '';
        foreach($roles as $role) {
            if ($role['id'] == $utilisateur['role_id']) {
                $nom_role = $role['nom_role'];
                break;
            }
        }

        $data = [
            'titre' => 'Détails de l\'Utilisateur',
            'utilisateur' => $utilisateur,
            'nom_role' => $nom_role,
            'logs' => $logs
        ];

        $this->view('utilisateurs/voir', $data);
    }

   

    // Affiche le formulaire de connexion et traite la soumission
    public function login() {
        // Si le formulaire est soumis (méthode POST)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Nettoyer les données POST
            $_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

            $data = [
                'email' => trim($_POST['email']),
                'mot_de_passe' => trim($_POST['mot_de_passe']),
                'erreur_email' => '',
                'erreur_mdp' => ''
            ];

            // Valider l'email
            if (empty($data['email'])) {
                $data['erreur_email'] = 'Veuillez entrer votre email.';
            }

            // Valider le mot de passe
            if (empty($data['mot_de_passe'])) {
                $data['erreur_mdp'] = 'Veuillez entrer votre mot de passe.';
            }

            // Vérifier si l'utilisateur existe
            $utilisateur = $this->utilisateurModel->trouverUtilisateurParEmail($data['email']);
            if (!$utilisateur) {
                $data['erreur_email'] = 'Aucun utilisateur trouvé avec cet email.';
            }

            // Si pas d'erreurs jusqu'ici, on vérifie le mot de passe
            if (empty($data['erreur_email']) && empty($data['erreur_mdp'])) {
                $mdp_hache = $utilisateur['mot_de_passe'];
                if (password_verify($data['mot_de_passe'], $mdp_hache)) {
                    // Mot de passe correct ! On crée la session
                    $this->creerSessionUtilisateur($utilisateur);
                    // Rediriger vers le tableau de bord (qu'on créera plus tard)
                    header('location: ' . '/dashboard');
                } else {
                    $data['erreur_mdp'] = 'Mot de passe incorrect.';
                    $this->view('utilisateurs/login', $data);
                }
            } else {
                // Afficher la vue avec les erreurs
                $this->view('utilisateurs/login', $data);
            }

        } else {
            // Afficher le formulaire de connexion vide
            $data = ['email' => '', 'mot_de_passe' => ''];
            $this->view('utilisateurs/login', $data);
        }
    }

    // Gère l'inscription (pour le premier utilisateur par exemple)
    public function register() {
         // Logique d'inscription similaire à celle du login :
         // 1. Vérifier si POST
         // 2. Nettoyer et valider les données (nom, email, mdp, confirmation mdp)
         // 3. Vérifier si l'email n'existe pas déjà avec le model
         // 4. Si tout est OK, appeler $this->utilisateurModel->creerUtilisateur($data)
         // 5. Rediriger vers la page de connexion avec un message de succès.
         
         // Pour l'instant, on affiche juste la vue
         $this->view('utilisateurs/register');
    }

    // Crée les variables de session
     private function creerSessionUtilisateur($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom_complet'];
        $_SESSION['user_role_id'] = $user['role_id'];

        // NOUVELLE PARTIE : On charge les permissions en session
        $permissions = $this->utilisateurModel->getPermissionsPourRole($user['role_id']);
        $_SESSION['user_permissions'] = $permissions;
    }

    // Détruit la session
    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_nom']);
        unset($_SESSION['user_role_id']);
        session_destroy();
        header('location: /utilisateurs/login');
    }
}