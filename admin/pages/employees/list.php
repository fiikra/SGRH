<?php


if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

$pageTitle = "Liste des Employés";
include __DIR__.'../../../../includes/header.php';


// --- Advanced Filtering Options ---
$filters = [
    'search' => isset($_GET['search']) ? sanitize($_GET['search']) : '',
    'status' => isset($_GET['status']) ? sanitize($_GET['status']) : '',
    'department' => isset($_GET['department']) ? sanitize($_GET['department']) : '',
    'position' => isset($_GET['position']) ? sanitize($_GET['position']) : '',
    'hire_date_start' => isset($_GET['hire_date_start']) ? sanitize($_GET['hire_date_start']) : '',
    'hire_date_end' => isset($_GET['hire_date_end']) ? sanitize($_GET['hire_date_end']) : '',
    'salary_min' => isset($_GET['salary_min']) ? sanitize($_GET['salary_min']) : '',
    'salary_max' => isset($_GET['salary_max']) ? sanitize($_GET['salary_max']) : '',
];

// --- Pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- Database Query ---
$query = "SELECT * FROM employees WHERE 1=1";
$params = [];

if (!empty($filters['search'])) {
    $searchTerm = "%{$filters['search']}%";
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR nin LIKE ? OR nss LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'suspended', 'terminated'])) {
    $query .= " AND status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['department'])) {
    $query .= " AND department = ?";
    $params[] = $filters['department'];
}

if (!empty($filters['position'])) {
    $query .= " AND position = ?";
    $params[] = $filters['position'];
}

if (!empty($filters['hire_date_start'])) {
    $query .= " AND hire_date >= ?";
    $params[] = $filters['hire_date_start'];
}

if (!empty($filters['hire_date_end'])) {
    $query .= " AND hire_date <= ?";
    $params[] = $filters['hire_date_end'];
}

if (!empty($filters['salary_min']) && is_numeric($filters['salary_min'])) {
    $query .= " AND salary >= ?";
    $params[] = (float)$filters['salary_min'];
}

if (!empty($filters['salary_max']) && is_numeric($filters['salary_max'])) {
    $query .= " AND salary <= ?";
    $params[] = (float)$filters['salary_max'];
}

// --- Total Number of Employees (for pagination) ---
$totalQuery = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
$totalStmt = $db->prepare($totalQuery);
$totalStmt->execute($params);
$total = $totalStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// --- Fetch Employees with Pagination ---
$query .= " ORDER BY hire_date DESC LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// --- Fetch Data for Filter Dropdowns ---
$departments = fetchDistinct($db, 'employees', 'department');
$positions = fetchDistinct($db, 'employees', 'position');

// --- Handle Export to CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportQuery = "SELECT nin, first_name, last_name, position, department, hire_date, status, salary FROM employees WHERE 1=1";
    $exportParams = [];

    // Apply the same filters to the export query
    if (!empty($filters['search'])) {
        $searchTerm = "%{$filters['search']}%";
        $exportQuery .= " AND (first_name LIKE ? OR last_name LIKE ? OR nin LIKE ? OR nss LIKE ?)";
        $exportParams = array_merge($exportParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'suspended', 'terminated'])) {
        $exportQuery .= " AND status = ?";
        $exportParams[] = $filters['status'];
    }
    if (!empty($filters['department'])) {
        $exportQuery .= " AND department = ?";
        $exportParams[] = $filters['department'];
    }
    if (!empty($filters['position'])) {
        $exportQuery .= " AND position = ?";
        $exportParams[] = $filters['position'];
    }
    if (!empty($filters['hire_date_start'])) {
        $exportQuery .= " AND hire_date >= ?";
        $exportParams[] = $filters['hire_date_start'];
    }
    if (!empty($filters['hire_date_end'])) {
        $exportQuery .= " AND hire_date <= ?";
        $exportParams[] = $filters['hire_date_end'];
    }
    if (!empty($filters['salary_min']) && is_numeric($filters['salary_min'])) {
        $exportQuery .= " AND salary >= ?";
        $exportParams[] = (float)$filters['salary_min'];
    }
    if (!empty($filters['salary_max']) && is_numeric($filters['salary_max'])) {
        $exportQuery .= " AND salary <= ?";
        $exportParams[] = (float)$filters['salary_max'];
    }

    $exportStmt = $db->prepare($exportQuery);
    $exportStmt->execute($exportParams);
    $exportEmployees = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'liste_employes_' . date('YmdHis') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add headers
    fputcsv($output, ['NIN', 'Nom', 'Poste', 'Département', 'Date Embauche', 'Statut', 'Salaire']);

    // Add data
    foreach ($exportEmployees as $employee) {
        fputcsv($output, [
            $employee['nin'],
            $employee['first_name'] . ' ' . $employee['last_name'],
            $employee['position'],
            $employee['department'],
            formatDate($employee['hire_date']),
            ucfirst($employee['status']),
            $employee['salary'],
        ]);
    }

    fclose($output);
    exit();
}

// --- Function to fetch distinct values from a table column ---
function fetchDistinct($db, $table, $column) {
    $stmt = $db->query("SELECT DISTINCT $column FROM $table ORDER BY $column ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>
<div class="container mt-5">
    <h1 class="mb-4">Liste des Employés</h1>
    
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= APP_LINK ?>/admin/index.php">
                <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                <input type="hidden" name="route" value="employees_list">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= $filters['search'] ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                            <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                            <option value="terminated" <?= $filters['status'] === 'terminated' ? 'selected' : '' ?>>Terminé</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="department" class="form-select">
                            <option value="">Tous les départements</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept ?>" <?= $filters['department'] === $dept ? 'selected' : '' ?>><?= $dept ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="position" class="form-select">
                            <option value="">Tous les postes</option>
                            <?php foreach ($positions as $pos): ?>
                                <option value="<?= $pos ?>" <?= $filters['position'] === $pos ? 'selected' : '' ?>><?= $pos ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="hire_date_start" class="form-label">Date Embauche (Début)</label>
                        <input type="date" name="hire_date_start" id="hire_date_start" class="form-control" value="<?= $filters['hire_date_start'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="hire_date_end" class="form-label">Date Embauche (Fin)</label>
                        <input type="date" name="hire_date_end" id="hire_date_end" class="form-control" value="<?= $filters['hire_date_end'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="salary_min" class="form-label">Salaire Minimum</label>
                        <input type="number" step="0.01" name="salary_min" id="salary_min" class="form-control" value="<?= $filters['salary_min'] ?>" placeholder="Min">
                    </div>
                    <div class="col-md-3">
                        <label for="salary_max" class="form-label">Salaire Maximum</label>
                        <input type="number" step="0.01" name="salary_max" id="salary_max" class="form-control" value="<?= $filters['salary_max'] ?>" placeholder="Max">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= route('employees_add') ?>" class="btn btn-success w-100">
                            <i class="bi bi-plus"></i> Ajouter
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= route('employees_list', array_merge($filters, ['export' => 'csv'])) ?>" class="btn btn-secondary w-100">
                            <i class="bi bi-file-earmark-text"></i> CSV
                        </a>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-info w-100" onclick="window.print()">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (count($employees) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>NIN</th>
                        <th>Nom</th>
                        <th>Poste</th>
                        <th>Département</th>
                        <th>Date Embauche</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?= $employee['nin'] ?></td>
                            <td><?= $employee['first_name'] ?> <?= $employee['last_name'] ?></td>
                            <td><?= $employee['position'] ?></td>
                            <td><?= $employee['department'] ?></td>
                            <td><?= formatDate($employee['hire_date']) ?></td>
                            <td>
                                <span class="badge bg-<?=
                                    $employee['status'] === 'active' ? 'success' :
                                    ($employee['status'] === 'inactive' ? 'secondary' :
                                    ($employee['status'] === 'suspended' ? 'warning' : 'danger'))
                                ?>">
                                    <?= ucfirst($employee['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= route('employees_view', ['nin' => $employee['nin']]) ?>" class="btn btn-sm btn-info" title="Voir">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= route('employees_edit', ['nin' => $employee['nin']]) ?>" class="btn btn-sm btn-primary" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= route('employees_documents', ['nin' => $employee['nin']]) ?>" class="btn btn-sm btn-secondary" title="Documents">
                                    <i class="bi bi-folder"></i>
                                </a>
                                <a href="<?= route('employees_history', ['nin' => $employee['nin']]) ?>"
                                   class="btn btn-sm btn-secondary"
                                   title="Voir l'historique">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= route('employees_list', array_merge($filters, ['page' => $page - 1])) ?>">Précédent</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= route('employees_list', array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= route('employees_list', array_merge($filters, ['page' => $page + 1])) ?>">Suivant</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php else: ?>
        <div class="alert alert-info">Aucun employé trouvé.</div>
    <?php endif; ?>
</div>

<?php include __DIR__.'../../../../includes/footer.php'; ?>