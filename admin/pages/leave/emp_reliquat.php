<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

$pageTitle = "Employés avec reliquat de congé";
include __DIR__.'../../../../includes/header.php';

// --- Filtering and Pagination ---
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- Base Query ---
$query = "
    SELECT e.nin, e.first_name, e.last_name, e.position, e.department, e.hire_date, e.status, e.remaining_leave_balance
    FROM employees e
    WHERE e.remaining_leave_balance > 0
";
$params = [];
if (!empty($search)) {
    $query .= " AND (e.first_name LIKE :search OR e.last_name LIKE :search OR e.nin LIKE :search)";
    $params[':search'] = "%$search%";
}

// --- Get Total Count for Pagination ---
$countStmt = $db->prepare("SELECT COUNT(*) FROM ($query) as temp_count");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// --- Get Paginated Results ---
$query .= " ORDER BY e.last_name ASC, e.first_name ASC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
// Bind search params if they exist
if (!empty($search)) {
    $stmt->bindParam(':search', $params[':search']);
}
$stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$employeesWithReliquat = $stmt->fetchAll();
?>

<div class="container">
    <h1 class="mb-4"><i class="bi bi-hourglass-split"></i> Employés avec reliquat de congé</h1>

    <form method="get" action="<?= route('leave_emp_reliquat') ?>" class="mb-3 row g-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Rechercher par nom ou NIN..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Rechercher</button>
        </div>
    </form>

    <?php if (count($employeesWithReliquat) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>NIN</th>
                        <th>Nom</th>
                        <th>Département</th>
                        <th>Poste</th>
                        <th>Date Embauche</th>
                        <th>Statut</th>
                        <th>Reliquat (jours)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employeesWithReliquat as $emp): ?>
                    <tr>
                        <td><?= htmlspecialchars($emp['nin']) ?></td>
                        <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                        <td><?= htmlspecialchars($emp['department']) ?></td>
                        <td><?= htmlspecialchars($emp['position']) ?></td>
                        <td><?= formatDate($emp['hire_date']) ?></td>
                        <td>
                            <span class="badge bg-<?= $emp['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst(htmlspecialchars($emp['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($emp['remaining_leave_balance']) ?></span>
                        </td>
                        <td>
                            <a href="<?= route('employees_view', ['nin' => $emp['nin']]) ?>" class="btn btn-sm btn-secondary" title="Fiche employé">
                                <i class="bi bi-person"></i>
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
                        <a class="page-link" href="<?= route('leave_emp_reliquat', ['page' => $page - 1, 'search' => $search]) ?>">Précédent</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= route('leave_emp_reliquat', ['page' => $i, 'search' => $search]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= route('leave_emp_reliquat', ['page' => $page + 1, 'search' => $search]) ?>">Suivant</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php else: ?>
        <div class="alert alert-info">Aucun employé avec reliquat de congé.</div>
    <?php endif; ?>
</div>

<?php include __DIR__.'../../../../includes/footer.php'; ?>