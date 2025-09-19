<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- 1. SERVER-SIDE FILTERING & PAGINATION SETUP ---
$search_filter = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// --- 2. DYNAMICALLY BUILD THE QUERY ---
// We start the WHERE clauses with the mandatory exclusion of 'maladie' and filter for approved leaves
$whereClauses = ["l.leave_type <> 'maladie'", "l.status = 'prise'"];
$params = [];

// Add the search filter to the query if it's not empty
if (!empty($search_filter)) {
    $searchTerm = '%' . $search_filter . '%';
    // Search in NIN, first name, and last name fields
    $whereClauses[] = '(e.nin LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)';
    // Add the parameter three times for each placeholder
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Combine all clauses into the final WHERE statement
$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

// Define the base of the query
$baseQuery = "FROM leave_requests l
              JOIN employees e ON l.employee_nin = e.nin
              LEFT JOIN users u ON l.approved_by = u.id
              $whereSql";

// --- 3. EXECUTE QUERIES ---

// Get the total count of records matching the filters
$countStmt = $db->prepare("SELECT COUNT(l.id) " . $baseQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Get only the records for the current page
$dataStmt = $db->prepare(
    "SELECT l.*, e.nin, e.first_name, e.last_name, e.position, e.department, u.username AS approver_name " .
    $baseQuery .
    " ORDER BY l.start_date DESC LIMIT $perPage OFFSET $offset"
);
$dataStmt->execute($params);
$leaves = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Historique des Congés Pris";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-clock-history"></i> <?= htmlspecialchars($pageTitle) ?></h1>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get">
                <input type="hidden" name="route" value="leave_leave_historique">
                <div class="col-md-6">
                    <label for="search" class="form-label">Filtrer par NIN ou Nom</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_filter) ?>" class="form-control" placeholder="Entrez le NIN ou le nom de l'employé...">
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel-fill"></i> Filtrer</button>
                    <a href="<?= route('leave_leave_historique') ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i> Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Affichage de <?= count($leaves) ?> sur <?= $totalRecords ?> enregistrement(s) (congés pris, hors maladie)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employé</th>
                            <th>Département</th>
                            <th>Type de Congé</th>
                            <th>Dates</th>
                            <th class="text-center">Jours</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaves)) : ?>
                            <tr>
                                <td colspan="7" class="text-center p-4">Aucun historique de congé trouvé.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($leaves as $leave) : ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($leave['last_name'] . ' ' . $leave['first_name']) ?></strong>
                                    <small class="d-block text-muted">NIN: <?= htmlspecialchars($leave['nin']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($leave['department']) ?></td>
                                <td><?= htmlspecialchars($leave['leave_type']) ?></td>
                                <td><?= formatDate($leave['start_date']) ?> au <?= formatDate($leave['end_date']) ?></td>
                                <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($leave['days_requested']) ?></span></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-gear-fill"></i> Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?= route('leave_view', ['id' => $leave['id']]) ?>"><i class="bi bi-eye-fill me-2"></i>Détails</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="<?= route('leave_Leave_certificate', ['leave_id' => $leave['id']]) ?>" target="_blank"><i class="bi bi-printer-fill me-2"></i>Imprimer Certificat</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1) : ?>
            <div class="card-footer">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1) : ?>
                            <li class="page-item"><a class="page-link" href="<?= route('leave_leave_historique', ['search' => $search_filter, 'page' => $page - 1]) ?>">Précédent</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('leave_leave_historique', ['search' => $search_filter, 'page' => $i]) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages) : ?>
                            <li class="page-item"><a class="page-link" href="<?= route('leave_leave_historique', ['search' => $search_filter, 'page' => $page + 1]) ?>">Suivant</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>
