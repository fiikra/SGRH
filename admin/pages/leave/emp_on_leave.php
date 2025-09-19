<?php
// --- Security Headers and Session handling would be in your main router file ---

// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

$pageTitle = "Employés actuellement en congé";
include __dir__. '../../../../includes/header.php';

// --- Date du jour ---
$today = date('Y-m-d');

// --- Filtrage avancé ---
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// --- Pagination ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- Requête principale pour employés en congé ---
$query = "
    SELECT l.id, e.nin, e.first_name, e.last_name, e.position, e.department, e.hire_date, e.status, e.remaining_leave_balance, l.start_date, l.end_date, l.leave_type
    FROM employees e
    INNER JOIN leave_requests l ON e.nin = l.employee_nin
    WHERE l.status = 'approved'
      AND l.start_date <= :today1
      AND l.end_date >= :today2
      AND l.id NOT IN (
        SELECT leave_request_id
        FROM leave_pauses
        WHERE pause_start_date <= :today3 AND pause_end_date >= :today4
      )
";

$params = [
    'today1' => $today,
    'today2' => $today,
    'today3' => $today,
    'today4' => $today
];

if (!empty($search)) {
    $query .= " AND (e.first_name LIKE :search OR e.last_name LIKE :search OR e.nin LIKE :search)";
    $params['search'] = "%$search%";
}

// --- Total pour pagination ---
// For COUNT, we need a slightly different query without LIMIT/OFFSET
$countStmt = $db->prepare("SELECT COUNT(*) FROM ($query) as temp_count");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// --- Récupération avec pagination ---
$query .= " ORDER BY l.start_date DESC LIMIT :perPage OFFSET :offset";
$stmt = $db->prepare($query);
$stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
// Bind other params
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->execute();
$employeesOnLeave = $stmt->fetchAll();
?>

<div class="container">
    <h1 class="mb-4"><i class="bi bi-person-walking"></i> Employés actuellement en congé</h1>

    <form method="get" action="<?= route('leave_emp_on_leave') ?>" class="mb-3 row g-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Rechercher par nom ou NIN..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Rechercher</button>
        </div>
    </form>

    <?php if (count($employeesOnLeave) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>NIN</th>
                        <th>Nom</th>
                        <th>Département</th>
                        <th>Poste</th>
                        <th>Date Embauche</th>
                        <th>Type de congé</th>
                        <th>Période du congé</th>
                        <th>Jours restants</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employeesOnLeave as $emp): ?>
                    <tr>
                        <td><?= htmlspecialchars($emp['nin']) ?></td>
                        <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                        <td><?= htmlspecialchars($emp['department']) ?></td>
                        <td><?= htmlspecialchars($emp['position']) ?></td>
                        <td><?= formatDate($emp['hire_date']) ?></td>
                        <td><?= htmlspecialchars($emp['leave_type']) ?></td>
                        <td><?= formatDate($emp['start_date']) . ' au ' . formatDate($emp['end_date']) ?></td>
                        <td>
                            <?php
                                $dateFin = new DateTime($emp['end_date']);
                                $dateAuj = new DateTime('today');
                                $interval = $dateAuj->diff($dateFin);
                                $joursAvantReprise = intval($interval->format('%r%a'));
                                
                                $color = 'success';
                                if ($joursAvantReprise <= 1) {
                                    $color = 'danger';
                                } elseif ($joursAvantReprise <= 5) {
                                    $color = 'warning';
                                }
                            ?>
                            <span class="badge bg-<?= $color ?>">
                                <?= $joursAvantReprise ?> jour<?= $joursAvantReprise > 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= route('leave_view', ['id' => $emp['id']]) ?>" class="btn btn-sm btn-info" title="Voir le détail du congé">
                                <i class="bi bi-eye"></i>
                            </a>
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
                        <a class="page-link" href="<?= route('leave_emp_on_leave', ['page' => $page - 1, 'search' => $search]) ?>">Précédent</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= route('leave_emp_on_leave', ['page' => $i, 'search' => $search]) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= route('leave_emp_on_leave', ['page' => $page + 1, 'search' => $search]) ?>">Suivant</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php else: ?>
        <div class="alert alert-info">Aucun employé actuellement en congé.</div>
    <?php endif; ?>
</div>

<?php include __DIR__. '../../../../includes/footer.php'; ?>