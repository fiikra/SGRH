<?php

/**
 * Modern, Unified Employee Dashboard.
 *
 * This page serves as the main hub for logged-in employees, displaying
 * key profile information, request statuses, and quick actions in a
 * responsive, two-column layout.
 *
 * @version 2.0
 * @last-updated 2025-07-12
 */

// --- 1. Error Handling & Security ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

redirectIfNotLoggedIn(); // Assumes this function checks session and redirects if not logged in

// --- 2. Fetch Core Employee Data ---
$user_id = $_SESSION['user_id'] ?? null;

// Fetch primary employee and user data in one go
$stmt = $db->prepare(
    "SELECT
        e.nin, e.nss, e.first_name, e.last_name, e.position, e.photo_path,
        e.department, e.hire_date, e.annual_leave_balance, e.remaining_leave_balance,
        u.email, u.role, u.is_active
     FROM employees e
     JOIN users u ON e.user_id = u.id
     WHERE u.id = ?"
);
$stmt->execute([$user_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// If no employee profile is linked to the user account, it's a critical error.
if (!$employee) {
    $_SESSION['error'] = "Votre compte utilisateur n'est pas lié à un profil d'employé. Veuillez contacter l'administration.";
    header("Location: " . route('logout')); // Redirect to logout or an error page
    exit();
}

$employee_nin = $employee['nin'];

// --- 3. Fetch Dependent Data (Leaves & Missions) ---

// Get pending leave and mission counts
$stmt_pending_leave = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_nin = ? AND status = 'pending'");
$stmt_pending_leave->execute([$employee_nin]);
$pending_leave_count = $stmt_pending_leave->fetchColumn();

$stmt_pending_mission = $db->prepare("SELECT COUNT(*) FROM mission_orders WHERE employee_nin = ? AND status = 'pending'");
$stmt_pending_mission->execute([$employee_nin]);
$pending_mission_count = $stmt_pending_mission->fetchColumn();

// Get the 3 most recent leave requests
$leaves_stmt = $db->prepare("SELECT * FROM leave_requests WHERE employee_nin = ? ORDER BY created_at DESC LIMIT 3");
$leaves_stmt->execute([$employee_nin]);
$recent_leaves = $leaves_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- 4. Page Setup ---
$pageTitle = "Tableau de Bord";
include __DIR__ . '../../../includes/header.php'; // Corrected path assumption
?>

<div class="container-fluid mt-4">

    <?php display_flash_messages(); ?>

    <div class="row">
        <div class="col-lg-4 mb-4">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    <img src="<?= APP_LINK."/".htmlspecialchars($employee['photo_path'] ?? '/assets/img/default-avatar.png') ?>" class="rounded-circle mb-3" width="120" height="120" alt="Photo de profil" style="object-fit: cover;">
                    <h4 class="card-title mb-1"><?= htmlspecialchars($employee['first_name'] . " " . $employee['last_name']) ?></h4>
                    <p class="text-muted mb-3"><?= htmlspecialchars($employee['position']) ?></p>
                    <a href="<?= route('profile', ['id' => $user_id]) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-person me-1"></i> Voir Mon Profil Complet
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent"><h5 class="card-title mb-0">Mon Statut</h5></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <strong>Solde Congé (Annuel)</strong>
                            <span class="badge bg-primary rounded-pill fs-6"><?= number_format($employee['annual_leave_balance'] ?? 0, 1) ?> jrs</span>
                        </li>
                        <?php if (isset($employee['remaining_leave_balance']) && (float)$employee['remaining_leave_balance'] > 0): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <strong>Solde Congé (Reliquat)</strong>
                            <span class="badge bg-info-soft text-info rounded-pill fs-6"><?= number_format($employee['remaining_leave_balance'], 1) ?> jrs</span>
                        </li>
                        <?php endif; ?>
                         <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <strong>Département</strong>
                            <span><?= htmlspecialchars($employee['department'] ?? 'N/A') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <strong>Date d'Embauche</strong>
                            <span><?= isset($employee['hire_date']) ? (new DateTime($employee['hire_date']))->format('d M Y') : 'N/A' ?></span>
                        </li>
                         <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <strong>Statut du Compte</strong>
                             <span class="badge <?= $employee['is_active'] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' ?>">
                                <i class="bi bi-circle-fill me-1"></i> <?= $employee['is_active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row mb-4">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="card border-0 shadow-sm h-100">
                        <a href="<?= route('leave_requests', ['status_filter' => 'pending']) ?>" class="stretched-link" title="Voir les demandes en attente"></a>
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-hourglass-split text-warning fs-1"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted text-uppercase small">Congés en Attente</div>
                                <div class="h3 fw-bold mb-0"><?= $pending_leave_count ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                         <a href="<?= route('mission_orders', ['status_filter' => 'pending']) ?>" class="stretched-link" title="Voir les missions en attente"></a>
                        <div class="card-body d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-briefcase text-info fs-1"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-muted text-uppercase small">Missions en Attente</div>
                                <div class="h3 fw-bold mb-0"><?= $pending_mission_count ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent"><h5 class="card-title mb-0">Actions Rapides</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4"><a href="<?= route('request_leave') ?>" class="btn btn-primary w-100 h-100 py-3 d-flex flex-column justify-content-center"><i class="bi bi-calendar-plus fs-2"></i><span>Demander Congé</span></a></div>
                        <div class="col-sm-6 col-md-4"><a href="<?= route('request_mission') ?>" class="btn btn-success w-100 h-100 py-3 d-flex flex-column justify-content-center"><i class="bi bi-airplane fs-2"></i><span>Demander Mission</span></a></div>
                        <div class="col-sm-6 col-md-4"><a href="<?= route('documents') ?>" class="btn btn-info w-100 h-100 py-3 d-flex flex-column justify-content-center"><i class="bi bi-folder2-open fs-2"></i><span>Mes Documents</span></a></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Mes Demandes de Congé Récentes</h5>
                    <a href="<?= route('leave_requests') ?>" class="btn btn-sm btn-outline-secondary">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0">
                           <tbody>
                                <?php if (empty($recent_leaves)): ?>
                                    <tr><td class="text-center text-muted py-4">Aucune demande de congé récente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_leaves as $leave): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold">Congé <?= htmlspecialchars(ucfirst($leave['leave_type'])) ?></div>
                                            <div class="small text-muted">Du <?= (new DateTime($leave['start_date']))->format('d/m/y') ?> au <?= (new DateTime($leave['end_date']))->format('d/m/y') ?></div>
                                        </td>
                                        <td class="text-center align-middle"><?= htmlspecialchars($leave['days_requested']) ?> jour(s)</td>
                                        <td class="text-end align-middle">
                                            <span class="badge fs-6 <?= getStatusBadgeClass($leave['status']) ?>"><?= ucfirst(htmlspecialchars($leave['status'])) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                           </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    /* Soft Badge Styles from Inspiration Template */
    .bg-success-soft { background-color: rgba(25, 135, 84, 0.1); }
    .text-success { color: #198754 !important; }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.1); }
    .text-danger { color: #dc3545 !important; }
    .bg-primary-soft { background-color: rgba(13, 110, 253, 0.1); }
    .text-primary { color: #0d6efd !important; }
    .bg-info-soft { background-color: rgba(13, 202, 240, 0.1); }
    .text-info { color: #0dcaf0 !important; }
    .bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); }
    .text-warning { color: #ffc107 !important; }
    .badge { padding: 0.5em 0.75em; font-weight: 500; }
    .fs-6 { font-size: 0.9rem !important; }
</style>

<?php
// Helper function to map status to a badge class (place this in your global functions file)
if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        switch (strtolower($status)) {
            case 'approved':
            case 'approuvé':
                return 'bg-success-soft text-success';
            case 'rejected':
            case 'rejeté':
                return 'bg-danger-soft text-danger';
            case 'pending':
            case 'en attente':
                return 'bg-warning-soft text-warning';
            default:
                return 'bg-secondary text-white';
        }
    }
}

include __DIR__ . '../../../includes/footer.php';
?>