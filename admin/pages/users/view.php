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
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

redirectIfNotHR();

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$userId = (int)$_GET['id'];

// Get user details
$stmt = $db->prepare("SELECT u.*, e.first_name, e.last_name, e.nin, e.department, e.position 
                      FROM users u
                      LEFT JOIN employees e ON u.id = e.user_id
                      WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "Utilisateur non trouvé";
    header("Location: users.php");
    exit();
}

// Get login history
$logins = $db->prepare("SELECT * FROM users WHERE id = ? ");
$logins->execute([$userId]);

$pageTitle = "Détails de l'utilisateur: " . $user['username'];
include '../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Détails de l'Utilisateur</h1>
        <div>
            <a href="\admin/employees/view.php?nin=<?=$user['nin'] ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-primary ms-2">
                <i class="bi bi-pencil"></i> Modifier
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="avatar-lg mx-auto mb-3">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                    
                    <h3><?= htmlspecialchars($user['username']) ?></h3>
                    <h5 class="text-muted">
                        <?= ucfirst($user['role']) ?>
                    </h5>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-danger" 
                            onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>')">
                            <i class="bi bi-trash"></i> Supprimer le compte
                        </button>
                        
                        <?php if (!empty($user['nin'])): ?>
                            <a href="/admin/employees/view.php?nin=<?= $user['nin'] ?>" class="btn btn-outline-info">
                                <i class="bi bi-person"></i> Voir profil employé
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Informations de Connexion</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($user['email']) ?></dd>
                        
                        <dt class="col-sm-5">Dernière connexion</dt>
                        <dd class="col-sm-7">
                            <?= $user['last_login'] ? formatDate($user['last_login'], 'd/m/Y H:i') : 'Jamais' ?>
                        </dd>
                        
                        <dt class="col-sm-5">Statut</dt>
                        <dd class="col-sm-7">
                            <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-5">Crée le</dt>
                        <dd class="col-sm-7"><?= formatDate($user['created_at'], 'd/m/Y H:i') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#profile">Profil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#activity">Activité</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#security">Sécurité</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="profile">
                            <?php if (!empty($user['first_name'])): ?>
                                <h5 class="mb-3">Informations Employé</h5>
                                <dl class="row">
                                    <dt class="col-sm-3">Nom Complet</dt>
                                    <dd class="col-sm-9">
                                        <?= htmlspecialchars($user['first_name'] . ' ' . htmlspecialchars($user['last_name'])) ?>
                                    </dd>
                                    
                                    <dt class="col-sm-3">Poste</dt>
                                    <dd class="col-sm-9"><?= htmlspecialchars($user['position']) ?></dd>
                                    
                                    <dt class="col-sm-3">Département</dt>
                                    <dd class="col-sm-9"><?= htmlspecialchars($user['department']) ?></dd>
                                    
                                    <dt class="col-sm-3">NIN</dt>
                                    <dd class="col-sm-9"><?= htmlspecialchars($user['nin']) ?></dd>
                                </dl>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    Cet utilisateur n'est pas associé à un employé.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="activity">
                            <h5 class="mb-3">Historique de Connexion</h5>
                            
                            <?php if ($logins->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date/Heure</th>
                                                <th>Adresse IP</th>
                                                <th>User Agent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($login = $logins->fetch()): ?>
                                                <tr>
                                                    <td><?= formatDate($login['login_time'], 'd/m/Y H:i') ?></td>
                                                    <td><?= htmlspecialchars($login['ip_address']) ?></td>
                                                    <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($login['user_agent']) ?>">
                                                        <?= htmlspecialchars($login['user_agent']) ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    Aucune activité de connexion enregistrée.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="security">
                            <div class="alert alert-warning">
                                <h5 class="alert-heading">
                                    <i class="bi bi-shield-lock"></i> Actions de Sécurité
                                </h5>
                                <p>Ces actions affectent directement la sécurité du compte.</p>
                            </div>
                            
                            <div class="list-group">
                                <a href="reset_password.php?id=<?= $user['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">Réinitialiser le mot de passe</h6>
                                            <p class="mb-1 small text-muted">
                                                Définir un nouveau mot de passe pour cet utilisateur
                                            </p>
                                        </div>
                                        <i class="bi bi-arrow-right"></i>
                                    </div>
                                </a>
                                
                                <a href="toggle_status.php?id=<?= $user['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= $user['is_active'] ? 'Désactiver' : 'Activer' ?> le compte
                                            </h6>
                                            <p class="mb-1 small text-muted">
                                                <?= $user['is_active'] ? 'Empêchera la connexion' : 'Permettra à nouveau la connexion' ?>
                                            </p>
                                        </div>
                                        <i class="bi bi-arrow-right"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId, username) {
    if (confirm(`Voulez-vous vraiment supprimer l'utilisateur "${username}" ?\n\nCette action est irréversible.`)) {
        window.location.href = `delete.php?id=${userId}`;
    }
}
</script>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    background-color: #0d6efd;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto;
}
</style>

<?php include '../../includes/footer.php'; ?>