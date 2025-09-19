<?php

/**
 * Modern, Unified Employee Profile Page.
 *
 * This page uses the user's `id` from the URL to display a profile.
 * It features a two-column layout for profile info, security settings,
 * and a paginated login history.
 *
 * @version 4.0
 * @last-updated 2025-07-12
 */

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

redirectIfNotLoggedIn();

// --- 2. Get Identifier and Check Permissions ---
$viewed_user_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$viewed_user_id) {
    $_SESSION['error'] = "Identifiant de profil invalide.";
    header("Location: " . route('dashboard'));
    exit();
}

$viewer_id = $_SESSION['user_id'] ?? null;
$is_own_profile = ($viewer_id === $viewed_user_id);
$can_view_page = isAdminOrHR() || $is_own_profile;

if (!$can_view_page) {
    http_response_code(403);
    $_SESSION['error'] = "Accès non autorisé à ce profil.";
    header("Location: " . route('dashboard'));
    exit();
}

// --- 3. Fetch Primary Profile Data ---
$stmt = $db->prepare(
    "SELECT e.first_name, e.last_name, e.position, e.photo_path,
            u.username, u.email, u.role, u.is_active, u.is_email_otp_enabled, u.is_app_otp_enabled, u.created_at as registration_date
     FROM employees e
     JOIN users u ON e.user_id = u.id
     WHERE u.id = ?"
);
$stmt->execute([$viewed_user_id]);
$profile_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile_data) {
    $_SESSION['error'] = "Profil d'employé non trouvé.";
    header("Location: " . Proute('home'));
    exit();
}

// ===================================================================================
// --- ⭐️ POST HANDLER (Security & Password Update) ---
// ===================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_security') {
    if (!$is_own_profile && !isAdmin()) {
        $_SESSION['error'] = "Permission non accordée.";
    } else {
        $db->beginTransaction();
        try {
            if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                throw new Exception("Erreur de sécurité. Veuillez réessayer.");
            }

            $is_active = (isset($_POST['is_active']) && isAdmin()) ? 1 : $profile_data['is_active'];
            $email_otp = isset($_POST['is_email_otp_enabled']) ? 1 : 0;
            $app_otp = isset($_POST['is_app_otp_enabled']) ? 1 : 0;

            $stmt_sec = $db->prepare("UPDATE users SET is_active = ?, is_email_otp_enabled = ?, is_app_otp_enabled = ? WHERE id = ?");
            $stmt_sec->execute([$is_active, $email_otp, $app_otp, $viewed_user_id]);

            $new_password = $_POST['new_password'] ?? '';
            if (!empty($new_password)) {
                if ($new_password !== ($_POST['confirm_password'] ?? '')) throw new Exception("Les mots de passe ne correspondent pas.");
                if (strlen($new_password) < 8) throw new Exception("Le mot de passe doit faire au moins 8 caractères.");
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed_password, $viewed_user_id]);
            }
            
            $db->commit();
            $_SESSION['success'] = "Paramètres de sécurité mis à jour !";

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $_SESSION['error'] = "Erreur: " . $e->getMessage();
        }
    }
    header("Location: " . route('profile', ['id' => $viewed_user_id]));
    exit();
}

// --- 4. Fetch Login History with Pagination ---
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ["options" => ["default" => 1, "min_range" => 1]]);
$items_per_page = 5;
$offset = ($page - 1) * $items_per_page;

$total_logins_stmt = $db->prepare("SELECT COUNT(*) FROM user_logins WHERE user_id = ?");
$total_logins_stmt->execute([$viewed_user_id]);
$total_logins = $total_logins_stmt->fetchColumn();
$total_pages = ceil($total_logins / $items_per_page);

// ✅ CORRECTED QUERY: All placeholders are now named.
$logins_stmt = $db->prepare("SELECT * FROM user_logins WHERE user_id = :user_id ORDER BY login_time DESC LIMIT :limit OFFSET :offset");
$logins_stmt->bindValue(':user_id', $viewed_user_id, PDO::PARAM_INT); // Bind the new named placeholder
$logins_stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$logins_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logins_stmt->execute();
$login_history = $logins_stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = "Profil de " . htmlspecialchars($profile_data['first_name'] . " " . $profile_data['last_name']);
include __DIR__.'/../includes/header.php';
?>

<div class="container-fluid mt-4">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Profile Card -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="<?= htmlspecialchars($profile_data['photo_path'] ?? '/assets/img/default-avatar.png') ?>" class="rounded-circle mb-3" width="120" height="120" alt="Photo de profil" style="object-fit: cover;">
                    <h4 class="card-title mb-1"><?= htmlspecialchars($profile_data['first_name'] . " " . $profile_data['last_name']) ?></h4>
                    <p class="text-muted mb-3"><?= htmlspecialchars($profile_data['position']) ?></p>
                    
                    <div class="d-flex justify-content-center align-items-center mb-4">
                        <span class="badge me-2 <?= $profile_data['is_active'] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>">
                            <i class="bi bi-circle-fill me-1"></i> <?= $profile_data['is_active'] ? 'Actif' : 'Inactif' ?>
                        </span>
                        <span class="badge <?= ($profile_data['is_email_otp_enabled'] || $profile_data['is_app_otp_enabled']) ? 'bg-primary-soft text-primary' : 'bg-secondary-soft text-secondary' ?>">
                            <i class="bi bi-shield-check me-1"></i> 2FA <?= ($profile_data['is_email_otp_enabled'] || $profile_data['is_app_otp_enabled']) ? 'Activé' : 'Désactivé' ?>
                        </span>
                    </div>

                    <ul class="list-group list-group-flush text-start">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0"><strong>Email</strong><span><?= htmlspecialchars($profile_data['email']) ?></span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0"><strong>Rôle</strong><span><?= ucfirst(htmlspecialchars($profile_data['role'])) ?></span></li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0"><strong>Actif depuis</strong><span><?= (new DateTime($profile_data['registration_date']))->format('d M Y') ?></span></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Column: Security and History -->
        <div class="col-lg-8">
            <!-- Security Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0">
                    <h5 class="card-title mb-0">Sécurité du Compte</h5>
                </div>
                <div class="card-body">
                    <form action="<?= route('profile', ['id' => $viewed_user_id]) ?>" method="post">
                        <input type="hidden" name="action" value="update_security">
                        <?php csrf_input(); ?>
                        
                        <div class="row align-items-center mb-3 pb-3 border-bottom">
                            <div class="col-md-4"><label for="is_active" class="form-label mb-0"><strong>Compte Actif</strong></label></div>
                            <div class="col-md-8"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= !empty($profile_data['is_active']) ? 'checked' : '' ?> <?= !isAdmin() ? 'disabled' : '' ?>><small class="text-muted ms-2"><?= !isAdmin() ? 'Seul un admin peut modifier.' : '' ?></small></div></div>
                        </div>

                        <div class="row align-items-center mb-3 pb-3 border-bottom">
                            <div class="col-md-4"><label class="form-label mb-0"><strong>Authentification 2FA</strong></label></div>
                            <div class="col-md-8">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_email_otp_enabled" id="is_email_otp_enabled" value="1" <?= !empty($profile_data['is_email_otp_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="is_email_otp_enabled">Par Email</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_app_otp_enabled" id="is_app_otp_enabled" value="1" <?= !empty($profile_data['is_app_otp_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="is_app_otp_enabled">Par Application </label></div>
                                <a href="<?= route('users_setup_otp_app', ['id' => $viewed_user_id]) ?>" class="ms-3" title="Configurer l'application pour cet utilisateur">
                           <i class="bi bi-gear"></i> Configurer OTP
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4"><label class="form-label mb-0"><strong>Mot de passe</strong><br><small class="text-muted">Laisser vide pour ne pas changer</small></label></div>
                            <div class="col-md-4"><input type="password" class="form-control" name="new_password" placeholder="Nouveau mot de passe" autocomplete="new-password"></div>
                            <div class="col-md-4"><input type="password" class="form-control" name="confirm_password" placeholder="Confirmer" autocomplete="new-password"></div>
                        </div>

                        <div class="text-end mt-4"><button type="submit" class="btn btn-primary">Enregistrer les modifications</button></div>
                    </form>
                </div>
            </div>

            <!-- Connection History Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom-0">
                    <h5 class="card-title mb-0">Historique de Connexion</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Heure</th>
                                    <th>Adresse IP</th>
                                    <th>Agent Utilisateur</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($login_history)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Aucun historique de connexion trouvé.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($login_history as $login): ?>
                                        <tr>
                                            <td><?= (new DateTime($login['login_time']))->format('d/m/Y H:i:s') ?></td>
                                            <td><?= htmlspecialchars($login['ip_address']) ?></td>
                                            <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($login['user_agent']) ?>"><?= htmlspecialchars($login['user_agent']) ?></td>
                                            <td><span class="badge <?= $login['success'] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>"><?= $login['success'] ? 'Succès' : 'Échec' ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-transparent">
                    <nav aria-label="Pagination de l'historique">
                        <ul class="pagination pagination-sm justify-content-end mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= route('profile', ['id' => $viewed_user_id, 'page' => $page - 1]) ?>">Précédent</a></li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('profile', ['id' => $viewed_user_id, 'page' => $i]) ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= route('profile', ['id' => $viewed_user_id, 'page' => $page + 1]) ?>">Suivant</a></li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
    .text-success { color: #198754 !important; }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); }
    .text-danger { color: #dc3545 !important; }
    .bg-primary-soft { background-color: rgba(13, 110, 253, 0.1); }
    .text-primary { color: #0d6efd !important; }
    .bg-secondary-soft { background-color: rgba(108, 117, 125, 0.1); }
    .text-secondary { color: #6c757d !important; }
    .badge { padding: 0.5em 0.75em; font-weight: 500; }
</style>

<?php include __DIR__.'/../includes/footer.php'; ?>