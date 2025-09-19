<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Input Processing for Search & Pagination ---
$searchTerm = sanitize($_GET['search'] ?? '');
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;

// --- Database Queries ---
try {
    // Base query parts
    $sqlBase = "FROM trial_notifications tn JOIN employees e ON tn.employee_nin = e.nin";
    $whereClause = "";
    $params = []; // This will hold our parameters in order

    // Build the WHERE clause with positional placeholders (?)
    if (!empty($searchTerm)) {
        $whereClause = " WHERE (e.first_name LIKE ? OR e.last_name LIKE ? OR CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR tn.reference_number LIKE ?)";
        $searchTermWildcard = "%" . $searchTerm . "%";
        // Add the search term for each placeholder
        array_push($params, $searchTermWildcard, $searchTermWildcard, $searchTermWildcard, $searchTermWildcard);
    }

    // 1. Get total record count for pagination
    $countStmt = $db->prepare("SELECT COUNT(tn.id) " . $sqlBase . $whereClause);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $limit;

    // 2. Fetch the data for the current page
    $dataSql = "SELECT tn.*, e.first_name, e.last_name " . $sqlBase . $whereClause . " ORDER BY tn.issue_date DESC LIMIT ? OFFSET ?";
    $notificationsStmt = $db->prepare($dataSql);
    
    // Add the LIMIT and OFFSET values to the end of our parameters array
    $params[] = $limit;
    $params[] = $offset;

    // Bind all parameters one by one
    $paramIndex = 1;
    foreach ($params as $param) {
        // The last two parameters (limit and offset) must be integers
        $paramType = ($paramIndex > (count($params) - 2)) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $notificationsStmt->bindValue($paramIndex++, $param, $paramType);
    }

    $notificationsStmt->execute();
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If you get here again, uncomment the next line to see the new error
    // die("Database Error: " . $e->getMessage()); 
    error_log("Failed to fetch trial notifications: " . $e->getMessage());
    flash('error', 'Une erreur est survenue lors de la récupération des données.');
    $notifications = [];
    $totalPages = 0;
}

// Helper function to get display info for each decision type
function getDecisionInfo($decision) {
    switch ($decision) {
        case 'confirm':
            return ['label' => 'Confirmé', 'badge' => 'success'];
        case 'renew':
            return ['label' => 'Renouvelé', 'badge' => 'warning text-dark'];
        case 'terminate':
            return ['label' => 'Terminé', 'badge' => 'danger'];
        default:
            return ['label' => 'Inconnu', 'badge' => 'secondary'];
    }
}

$pageTitle = "Historique des Notifications d'Essai";
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4"><?= $pageTitle ?></h1>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">
                <i class="bi bi-list-ul me-2"></i>Liste des Notifications
            </h5>
            <form method="get" action="<?= route('trial_notifications_index') ?>" class="d-flex" style="max-width: 300px;">
                <input type="hidden" name="route" value="trial_notifications_index">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Rechercher..." value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit" class="btn btn-sm btn-primary ms-2"><i class="bi bi-search"></i></button>
                <?php if (!empty($searchTerm)): ?>
                    <a href="<?= route('trial_notifications_index') ?>" class="btn btn-sm btn-secondary ms-2"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Employé</th>
                            <th scope="col">Référence</th>
                            <th scope="col">Décision</th>
                            <th scope="col">Date</th>
                            <th scope="col" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr><td colspan="5" class="text-center p-4">Aucune notification trouvée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <?php $decisionInfo = getDecisionInfo($notif['decision']); ?>
                                <tr>
                                    <td>
                                        <a href="<?= route('employees_view', ['nin' => $notif['employee_nin']]) ?>">
                                            <?= htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><strong><?= htmlspecialchars($notif['reference_number']) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= $decisionInfo['badge'] ?>"><?= $decisionInfo['label'] ?></span>
                                    </td>
                                    <td><?= formatDate($notif['issue_date']) ?></td>
                                   <td class="text-center">
    <div class="btn-group btn-group-sm" role="group">
        <a href="<?= route('trial_notifications_trial_notification_view', ['ref' => $notif['reference_number']]) ?>" class="btn btn-info" data-bs-toggle="tooltip" title="Voir les détails">
            <i class="bi bi-search"></i> Détails
        </a>
        
        <a href="<?= route('trial_notifications_generate_notification_pdf', ['ref' => $notif['reference_number']]) ?>" class="btn btn-danger" target="_blank" data-bs-toggle="tooltip" title="Générer le PDF">
            <i class="bi bi-file-earmark-pdf-fill"></i> PDF
        </a>
    </div>
</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= route('trial_notifications_index', ['page' => $currentPage - 1, 'search' => $searchTerm]) ?>">Précédent</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= route('trial_notifications_index', ['page' => $i, 'search' => $searchTerm]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= route('trial_notifications_index', ['page' => $currentPage + 1, 'search' => $searchTerm]) ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>