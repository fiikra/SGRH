<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR(); // Ensure user is HR

// --- Get Route Parameters ---
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20; // Results per page
$offset = ($page - 1) * $perPage;

// --- Base Query & Parameters for Filtering ---
$baseQuery = "FROM employees WHERE status = 'active'"; // Let's analyze active employees
$params = [];
if (!empty($search)) {
    $baseQuery .= " AND (first_name LIKE ? OR last_name LIKE ? OR nin LIKE ? OR department LIKE ? OR position LIKE ?)";
    $searchTerm = "%$search%";
    // The search term will be used for both queries
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// --- CSV Export Logic (Remains largely the same, as it was well-implemented) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Note: We don't paginate the export, which is correct.
    $exportQuery = "SELECT nin, last_name, first_name, department, position, annual_leave_balance, remaining_leave_balance " . $baseQuery . " ORDER BY department ASC, last_name ASC, first_name ASC";
    $exportStmt = $db->prepare($exportQuery);
    $exportStmt->execute($params);
    $exportEmployees = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analyse_employes_conge_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    
    fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($output, ['NIN', 'Nom', 'Prénom', 'Département', 'Poste', 'Solde Annuel', 'Reliquat'], ';');
    
    foreach ($exportEmployees as $emp) {
        fputcsv($output, [
            $emp['nin'], $emp['last_name'], $emp['first_name'], $emp['department'],
            $emp['position'], $emp['annual_leave_balance'], $emp['remaining_leave_balance']
        ], ';');
    }
    fclose($output);
    exit();
}

// --- 1. OPTIMIZED STATISTICS QUERY ---
// This single query replaces the need for getStats() and a separate COUNT query.
$statsQuery = "SELECT
    COUNT(*) as total,
    AVG(annual_leave_balance) as avg_annual,
    AVG(remaining_leave_balance) as avg_remaining
" . $baseQuery;

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalPages = ceil($stats['total'] / $perPage);

// --- 2. PAGINATED DATA QUERY ---
// This query fetches only the data needed for the current page.
$dataQuery = "SELECT nin, first_name, last_name, position, department, annual_leave_balance, remaining_leave_balance " . $baseQuery . " ORDER BY department ASC, last_name ASC, first_name ASC LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($dataQuery);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Page Setup ---
$pageTitle = "Analyse des Droits de Congé";
include __DIR__ . '../../../../includes/header.php'; // This includes the new sidebar
?>

<style>
@media print {
    .no-print { display: none !important; }
    .printable-area { padding: 0; margin: 0; }
    body { font-size: 10pt; }
    .table th, .table td { border: 1px solid #dee2e6 !important; padding: 4px; }
    .badge { border: 1px solid #ccc; color: #000 !important; background-color: #fff !important; }
}
</style>

<div class="container-fluid">
    <div class="printable-area">
        <h1 class="mb-4"><i class="bi bi-bar-chart-line-fill"></i> <?= htmlspecialchars($pageTitle) ?></h1>

        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="get" action="" class="row g-3 align-items-center">
                    <input type="hidden" name="route" value="leave_report">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher par nom, NIN, département..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill"></i> Filtrer</button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= route('leave_report') ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i> Réinitialiser</a>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <a href="<?= route('leave_report', ['export' => 'csv', 'search' => $search]) ?>" class="btn btn-success flex-fill"><i class="bi bi-file-earmark-excel"></i> Export</a>
                        <button type="button" class="btn btn-info flex-fill" onclick="window.print()"><i class="bi bi-printer"></i> Imprimer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card card-stat bg-primary text-white h-100">
                    <div class="card-body">
                        <i class="bi bi-people-fill"></i>
                        <div class="stat-info">
                            <h2><?= htmlspecialchars($stats['total']) ?></h2>
                            <p>Employé(s) Trouvé(s)</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stat bg-info text-white h-100">
                    <div class="card-body">
                        <i class="bi bi-calendar-day"></i>
                        <div class="stat-info">
                            <h2><?= htmlspecialchars(number_format($stats['avg_annual'] ?? 0, 2)) ?></h2>
                            <p>Solde Annuel Moyen</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stat bg-success text-white h-100">
                     <div class="card-body">
                        <i class="bi bi-calendar-plus"></i>
                        <div class="stat-info">
                           <h2><?= htmlspecialchars(number_format($stats['avg_remaining'] ?? 0, 2)) ?></h2>
                           <p>Reliquat Moyen</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (count($employees) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>NIN</th>
                                    <th>Nom Complet</th>
                                    <th>Département</th>
                                    <th>Poste</th>
                                    <th class="text-center">Solde Annuel</th>
                                    <th class="text-center">Reliquat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($emp['nin']) ?></td>
                                        <td><strong><?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($emp['department']) ?></td>
                                        <td><?= htmlspecialchars($emp['position']) ?></td>
                                        <td class="text-center"><span class="badge fs-6 bg-primary"><?= htmlspecialchars($emp['annual_leave_balance']) ?> jrs</span></td>
                                        <td class="text-center">
                                            <?php
                                            $reliquat = floatval($emp['remaining_leave_balance']);
                                            $color = 'success'; // Default to green
                                            if ($reliquat <= 0) {
                                                $color = 'danger';
                                            } elseif ($reliquat < 10) {
                                                $color = 'warning text-dark';
                                            }
                                            ?>
                                            <span class="badge fs-6 bg-<?= $color ?>"><?= htmlspecialchars($reliquat) ?> jrs</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Page navigation" class="mt-4 no-print">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= route('leave_report', ['page' => $page - 1, 'search' => $search]) ?>">Précédent</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('leave_report', ['page' => $i, 'search' => $search]) ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="<?= route('leave_report', ['page' => $page + 1, 'search' => $search]) ?>">Suivant</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-info mt-4">Aucun employé trouvé pour les critères de recherche.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>