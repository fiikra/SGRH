<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- 1. SETUP FILTERS AND PAGINATION ---
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// --- 2. CONSOLIDATE QUERY LOGIC ---
$fromJoin = "FROM employees e LEFT JOIN employee_attendance a ON e.nin = a.employee_nin AND a.status IN ('sick_leave', 'maladie')";
$whereClause = '';
$params = [];
if (!empty($search)) {
    $whereClause = "WHERE (e.first_name LIKE ? OR e.last_name LIKE ? OR e.nin LIKE ? OR e.department LIKE ? OR e.position LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
}
$groupBy = "GROUP BY e.nin, e.first_name, e.last_name, e.position, e.department";

// --- 3. EXPORT LOGIC (USES CONSOLIDATED QUERY) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportQuery = "SELECT e.nin, e.first_name, e.last_name, e.position, e.department, COUNT(a.id) AS sick_leave_days, MIN(a.attendance_date) as first_sick, MAX(a.attendance_date) as last_sick
                    $fromJoin $whereClause $groupBy ORDER BY sick_leave_days DESC, e.last_name ASC";
    $exportStmt = $db->prepare($exportQuery);
    $exportStmt->execute($params);
    $exportEmployees = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analyse_sickleave_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NIN', 'Nom', 'Prénom', 'Département', 'Poste', 'Jours Maladie', 'Premier Jour', 'Dernier Jour']);
    foreach ($exportEmployees as $emp) {
        fputcsv($output, array_values($emp));
    }
    fclose($output);
    exit();
}

// --- 4. OPTIMIZED STATISTICS QUERY ---
// This single query gets all stats needed for the page. It's much faster.
$statsSql = "
    SELECT
        COUNT(DISTINCT T.nin) as total_employees,
        AVG(T.sick_leave_days) as avg_sick,
        MIN(T.sick_leave_days) as min_sick,
        MAX(T.sick_leave_days) as max_sick
    FROM (
        SELECT e.nin, COUNT(a.id) as sick_leave_days
        $fromJoin $whereClause $groupBy
    ) AS T
";
$statsStmt = $db->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$total = $stats['total_employees'] ?? 0;
$totalPages = ceil($total / $perPage);

// --- 5. PAGINATED DATA QUERY (USES CONSOLIDATED QUERY) ---
$dataQuery = "SELECT e.nin, e.first_name, e.last_name, e.position, e.department, COUNT(a.id) AS sick_leave_days, MIN(a.attendance_date) as first_sick, MAX(a.attendance_date) as last_sick
              $fromJoin $whereClause $groupBy
              ORDER BY sick_leave_days DESC, e.last_name ASC, e.first_name ASC
              LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($dataQuery);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- PAGE SETUP ---
$pageTitle = "Analyse des Congés Maladie";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4"><i class="bi bi-bar-chart-line-fill"></i> <?= htmlspecialchars($pageTitle) ?></h1>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="leave_List_sick_leave">
                <div class="col-md-6">
                    <label for="search" class="form-label">Rechercher</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Par nom, NIN, département, poste..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill"></i> Filtrer</button>
                    <a href="<?= route('leave_List_sick_leave', ['export' => 'csv', 'search' => $search]) ?>" class="btn btn-success w-100"><i class="bi bi-file-earmark-excel"></i> Export</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat bg-primary text-white h-100">
                <div class="card-body"><i class="bi bi-people-fill"></i><div class="stat-info"><h2><?= htmlspecialchars($total) ?></h2><p>Employé(s) concerné(s)</p></div></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat bg-info text-white h-100">
                <div class="card-body"><i class="bi bi-calculator-fill"></i><div class="stat-info"><h2><?= htmlspecialchars(number_format($stats['avg_sick'] ?? 0, 2)) ?></h2><p>Jours Moyens / Employé</p></div></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat bg-warning h-100">
                <div class="card-body"><i class="bi bi-arrow-down-short"></i><div class="stat-info"><h2><?= htmlspecialchars($stats['min_sick'] ?? 0) ?></h2><p>Jours Min / Employé</p></div></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card card-stat bg-danger text-white h-100">
                <div class="card-body"><i class="bi bi-arrow-up-short"></i><div class="stat-info"><h2><?= htmlspecialchars($stats['max_sick'] ?? 0) ?></h2><p>Jours Max / Employé</p></div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Employé</th>
                            <th>Département / Poste</th>
                            <th class="text-center">Total Jours Maladie</th>
                            <th>Première Absence</th>
                            <th>Dernière Absence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr><td colspan="5" class="text-center p-4">Aucun employé trouvé pour cette analyse.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                                    <small class="d-block text-muted">NIN: <?= htmlspecialchars($emp['nin']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($emp['department']) ?></strong>
                                    <small class="d-block text-muted"><?= htmlspecialchars($emp['position']) ?></small>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $days = intval($emp['sick_leave_days']);
                                    $color = 'success';
                                    if ($days >= 15) $color = 'danger';
                                    elseif ($days >= 5) $color = 'warning text-dark';
                                    ?>
                                    <span class="badge fs-6 bg-<?= $color ?>"><?= $days ?></span>
                                </td>
                                <td><?= $emp['first_sick'] ? formatDate($emp['first_sick']) : 'N/A' ?></td>
                                <td><?= $emp['last_sick'] ? formatDate($emp['last_sick']) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= route('leave_List_sick_leave', ['page' => $page - 1, 'search' => $search]) ?>">Précédent</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('leave_List_sick_leave', ['page' => $i, 'search' => $search]) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="<?= route('leave_List_sick_leave', ['page' => $page + 1, 'search' => $search]) ?>">Suivant</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>