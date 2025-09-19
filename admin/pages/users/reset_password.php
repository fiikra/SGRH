<?php
// --- Security Headers: Set before any output ---
header('X-Frame-Options: DENY'); // Prevent clickjacking
header('X-Content-Type-Options: nosniff'); // Prevent MIME sniffing
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload'); // Only works over HTTPS
/*
// --- Force HTTPS if not already (optional, best to do in server config) ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $httpsUrl", true, 301);
    exit();
}
*/
// --- Harden Session Handling ---
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '', // Set to your production domain if needed
    'secure' => true,   // Only send cookie over HTTPS
    'httponly' => true, // JavaScript can't access
    'samesite' => 'Strict' // CSRF protection
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);
// --- Generic error handler (don't leak errors to users in production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Prevent session fixation ---
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Only admin/HR can access
redirectIfNotAdminOrHR();

$error = '';
$success = '';
$userData = null;
$showPrintButton = false;
$passwordToPrint = '';

// Get user ID from URL
if (isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    
    try {
        // Get user information
        $stmt = $db->prepare("SELECT u.id, u.username, u.email, u.role, 
                             e.first_name, e.last_name, e.nin 
                             FROM users u
                             LEFT JOIN employees e ON u.id = e.user_id
                             WHERE u.id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();
        
        if (!$userData) {
            $error = "Utilisateur non trouvé";
        }
    } catch (PDOException $e) {
        $error = "Erreur de base de données: " . $e->getMessage();
    }
} else {
    $error = "ID utilisateur manquant";
}
// Au début du fichier, après avoir récupéré $userId

// Process password reset/generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userData) {
    $action = $_POST['action'] ?? '';
    
    try {
        $db->beginTransaction();
        
        if ($action === 'generate') {
            // Generate a new random password
            $passwordToPrint = generateRandomPassword(8);
            $hashedPassword = password_hash($passwordToPrint, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            $success = "Nouveau mot de passe généré avec succès";
            $showPrintButton = true;
            
        } elseif ($action === 'reset') {
            // Manual password reset
            $password = sanitize($_POST['password']);
            $confirmPassword = sanitize($_POST['confirm_password']);
            
            if ($password !== $confirmPassword) {
                $error = "Les mots de passe ne correspondent pas";
            } elseif (strlen($password) < 8) {
                $error = "Le mot de passe doit contenir au moins 8 caractères";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                $passwordToPrint = $password; // Store the password for printing
                $success = "Mot de passe réinitialisé avec succès";
                $showPrintButton = true;
            }
        }
        
        $db->commit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Erreur lors de la réinitialisation: " . $e->getMessage();
    }
}

$pageTitle = "Réinitialisation de mot de passe";
include '../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Réinitialisation de mot de passe</h1>
        <a href="view.php?id=<?= $userData['id'] ?>" class="btn btn-secondary">
    <i class="bi bi-arrow-left"></i> Retour
</a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        
        <?php if ($showPrintButton): ?>
            <div class="text-center mb-4">
                <button class="btn btn-primary" onclick="printCredentials()">
                    <i class="bi bi-printer"></i> Imprimer les identifiants
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($userData): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Informations utilisateur</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">ID</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($userData['id']) ?></dd>
                    <dt class="col-sm-3">Nom d'utilisateur</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($userData['username']) ?></dd>
                    
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($userData['email']) ?></dd>
                    
                    <dt class="col-sm-3">Rôle</dt>
                    <dd class="col-sm-9">
                        <span class="badge bg-<?= $userData['role'] === 'admin' ? 'danger' : ($userData['role'] === 'hr' ? 'warning' : 'primary') ?>">
                            <?= ucfirst($userData['role']) ?>
                        </span>
                    </dd>
                    
                    <?php if ($userData['first_name']): ?>
                        <dt class="col-sm-3">Employé associé</dt>
                        <dd class="col-sm-9">
                            <?= htmlspecialchars($userData['first_name'] . ' ' . htmlspecialchars($userData['last_name'])) ?>
                            (<?= htmlspecialchars($userData['nin']) ?>)
                        </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Générer un nouveau mot de passe</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="generateForm">
                          <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                        <input type="hidden" name="action" value="generate">
                            <p>Génère un mot de passe aléatoire sécurisé de 8 caractères.</p>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key"></i> Générer mot de passe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Définir manuellement</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                           <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                        <input type="hidden" name="action" value="reset">
                            <div class="mb-3">
                                <label for="password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Enregistrer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hidden div for printing -->
        <div id="printContent" style="display:none;">
            <div style="padding:20px;font-family:Arial,sans-serif;">
                <h2 style="text-align:center;margin-bottom:30px;">Identifiants d'accès</h2>
                
                <table style="width:100%;margin-bottom:30px;">
                    <tr>
                        <td style="width:50%;padding:10px;vertical-align:top;">
                            <h4>Informations utilisateur</h4>
                            <p><strong>Nom d'utilisateur:</strong> <?= htmlspecialchars($userData['username']) ?></p>
                            <?php if ($userData['first_name']): ?>
                                <p><strong>Employé:</strong> <?= htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']) ?></p>
                                <p><strong>NIN:</strong> <?= htmlspecialchars($userData['nin']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td style="width:50%;padding:10px;vertical-align:top;">
                            <h4>Nouveaux identifiants</h4>
                            <p><strong>Date de génération:</strong> <?= date('d/m/Y H:i') ?></p>
                            <?php if (!empty($passwordToPrint)): ?>
                                <p><strong>Mot de passe:</strong> <?= htmlspecialchars($passwordToPrint) ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <div style="margin-top:30px;padding:15px;border:1px dashed #ccc;text-align:center;">
                    <h3>Instructions</h3>
                    <p>Ces identifiants sont à transmettre à l'utilisateur. Il devra changer son mot de passe lors de sa première connexion.</p>
                    <p><strong>URL de connexion:</strong> <?= htmlspecialchars(APP_LINK) ?>/login.php</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function printCredentials() {
    const printContent = document.getElementById('printContent').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php

?>