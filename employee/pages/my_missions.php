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
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/flash.php';
require_once '../includes/flash_messages.php';

if (!isLoggedIn()) {
    $_SESSION['error'] = "Vous devez être connecté pour accéder à cette page.";
    header("Location: " . APP_LINK . "/auth/login.php");
    exit();
}
$employee_nin_session = $_SESSION['user_nin'] ?? '';
if (empty($employee_nin_session)) {
    $_SESSION['error'] = "NIN non trouvé. Veuillez vous reconnecter.";
    header("Location: dashboard.php"); 
    exit();
}

$pageTitle = "Mes Ordres de Mission";

$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination_start = ($page - 1) * $limit;

$total_missions_stmt = $db->prepare("SELECT COUNT(id) FROM mission_orders WHERE employee_nin = ?");
$total_missions_stmt->execute([$employee_nin_session]);
$total_missions = $total_missions_stmt->fetchColumn();
$totalPages = ceil($total_missions / $limit);

$stmt = $db->prepare("
    SELECT mo.* FROM mission_orders mo
    WHERE mo.employee_nin = :employee_nin
    ORDER BY mo.created_at DESC
    LIMIT :start, :limit
");
$stmt->bindParam(':employee_nin', $employee_nin_session, PDO::PARAM_STR);
$stmt->bindParam(':start', $pagination_start, PDO::PARAM_INT);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-list-stars me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="request_mission.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle-fill me-1"></i> Nouvelle Demande de Mission
        </a>
    </div>

    <?php display_flash_messages(); ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <?php if (empty($missions)): ?>
                <div class="alert alert-info text-center">Vous n'avez aucune demande d'ordre de mission pour le moment.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="myMissionsTable">
                        <thead class="table-light">
                            <tr>
                                <th>N° Référence</th>
                                <th>Destination</th>
                                <th>Départ</th>
                                <th>Retour</th>
                                <th style="min-width:150px;">Objectif</th>
                                <th>Statut</th>
                                <th class="text-center">PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missions as $mission): ?>
                                <tr>
                                    <td><?= htmlspecialchars($mission['reference_number']) ?></td>
                                    <td><?= htmlspecialchars($mission['destination']) ?></td>
                                    <td><?= formatDate($mission['departure_date'], 'd/m/Y H:i') ?></td>
                                    <td><?= formatDate($mission['return_date'], 'd/m/Y H:i') ?></td>
                                    <td><?= htmlspecialchars(substr($mission['objective'], 0, 40)) . (strlen($mission['objective']) > 40 ? '...' : '') ?></td>
                                    <td>
                                        <?php
                                        $status_badge_class = 'secondary'; // Default
                                        switch ($mission['status']) {
                                            case 'approved': $status_badge_class = 'success'; break;
                                            case 'pending': $status_badge_class = 'warning text-dark'; break;
                                            case 'rejected': $status_badge_class = 'danger'; break;
                                            case 'completed': $status_badge_class = 'primary'; break;
                                            case 'cancelled': $status_badge_class = 'dark'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $status_badge_class ?>"><?= ucfirst(htmlspecialchars($mission['status'])) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($mission['status'] === 'approved' || $mission['status'] === 'completed'): ?>
                                            <a href="<?= APP_LINK ?>/admin/missions/generate_mission_order_pdf.php?id=<?= $mission['id'] ?>" class="btn btn-danger btn-sm" target="_blank" title="Télécharger PDF">
                                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                            </a>
                                        <?php else: echo "-"; endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">Précédent</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Précédent</span></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">Suivant</a></li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Suivant</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
<style> /* Basic styles for consistency with admin dashboard cards */
    .card .border-left-primary { border-left: .25rem solid #4e73df!important; }
    .text-xs { font-size: .8rem; }
    .text-gray-300 { color: #dddfeb!important; }
    .text-gray-800 { color: #5a5c69!important; }
    .font-weight-bold { font-weight: 700!important; }
    .shadow-sm { box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)!important; }
    .no-gutters { margin-right: 0; margin-left: 0; }
    .no-gutters > .col, .no-gutters > [class*="col-"] { padding-right: 0; padding-left: 0; }
    .card-body .fs-1 { font-size: 2.5rem !important; } 
    .card-body .fs-2 { font-size: 2rem !important; } 
   </style>
<?php include '../includes/footer.php'; ?>