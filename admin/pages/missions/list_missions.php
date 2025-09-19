<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

$pageTitle = "Liste des Ordres de Mission";

// --- 1. SETUP FILTERS AND PAGINATION ---
$filters = [
    'status_filter' => isset($_GET['status_filter']) && in_array($_GET['status_filter'], ['pending', 'approved', 'rejected', 'completed', 'cancelled']) ? sanitize($_GET['status_filter']) : '',
    'employee_filter' => isset($_GET['employee_filter']) ? sanitize($_GET['employee_filter']) : '',
    'date_from' => isset($_GET['date_from']) && validateDate($_GET['date_from']) ? sanitize($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) && validateDate($_GET['date_to']) ? sanitize($_GET['date_to']) : '',
];

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// --- 2. BUILD DYNAMIC QUERY ---
$baseQuery = "FROM mission_orders mo JOIN employees e ON mo.employee_nin = e.nin";
$whereClauses = [];
$params = [];

if (!empty($filters['status_filter'])) {
    $whereClauses[] = "mo.status = :status";
    $params[':status'] = $filters['status_filter'];
}
if (!empty($filters['employee_filter'])) {
    $whereClauses[] = "mo.employee_nin = :employee_nin";
    $params[':employee_nin'] = $filters['employee_filter'];
}
if (!empty($filters['date_from'])) {
    $whereClauses[] = "mo.departure_date >= :date_from";
    $params[':date_from'] = $filters['date_from'];
}
if (!empty($filters['date_to'])) {
    $whereClauses[] = "mo.departure_date <= :date_to";
    $params[':date_to'] = $filters['date_to'];
}

$whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : '';

// --- 3. FETCH DATA ---

// CORRECTED SECTION
// Get total count for pagination by separating the calls
$countStmt = $db->prepare("SELECT COUNT(mo.id) " . $baseQuery . $whereSql);
$countStmt->execute($params);
$totalMissions = $countStmt->fetchColumn();
// END OF CORRECTION

$totalPages = ceil($totalMissions / $perPage);

// Fetch paginated data
$fetchSql = "SELECT mo.*, e.first_name, e.last_name " . $baseQuery . $whereSql . " ORDER BY mo.created_at DESC LIMIT :offset, :limit";
$stmt = $db->prepare($fetchSql);
// Bind filter params first
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
// Bind pagination params
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->execute();
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees for the filter dropdown
$employees_for_filter = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status = 'active' ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-briefcase-fill me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= route('missions_add_mission') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Nouvel Ordre
        </a>
    </div>

    <?php display_flash_messages(); ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= route('missions_list_missions') ?>" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="missions_list_missions">
                <div class="col-md-3">
                    <label for="employee_filter" class="form-label">Employé</label>
                    <select name="employee_filter" id="employee_filter" class="form-select">
                        <option value="">Tous les employés</option>
                        <?php foreach ($employees_for_filter as $emp) : ?>
                            <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= ($filters['employee_filter'] === $emp['nin']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status_filter" class="form-label">Statut</label>
                    <select name="status_filter" id="status_filter" class="form-select">
                        <option value="">Tous</option>
                        <option value="pending" <?= ($filters['status_filter'] === 'pending') ? 'selected' : '' ?>>En attente</option>
                        <option value="approved" <?= ($filters['status_filter'] === 'approved') ? 'selected' : '' ?>>Approuvé</option>
                        <option value="rejected" <?= ($filters['status_filter'] === 'rejected') ? 'selected' : '' ?>>Rejeté</option>
                        <option value="completed" <?= ($filters['status_filter'] === 'completed') ? 'selected' : '' ?>>Terminé</option>
                        <option value="cancelled" <?= ($filters['status_filter'] === 'cancelled') ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Départ (De)</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Départ (À)</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-info w-100"><i class="bi bi-funnel-fill"></i> Filtrer</button>
                    <a href="<?= route('missions_list_missions') ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Ordres de Mission (<?= $totalMissions ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Réf.</th>
                            <th>Employé</th>
                            <th>Destination</th>
                            <th>Période</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($missions)) : ?>
                            <tr><td colspan="6" class="text-center p-4">Aucun ordre de mission trouvé.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($missions as $mission) : ?>
                            <tr>
                                <td class="small"><?= htmlspecialchars($mission['reference_number']) ?></td>
                                <td><?= htmlspecialchars($mission['first_name'] . ' ' . $mission['last_name']) ?></td>
                                <td><?= htmlspecialchars($mission['destination']) ?></td>
                                <td class="small"><?= formatDate($mission['departure_date'], 'd/m/y H:i') ?> → <?= formatDate($mission['return_date'], 'd/m/y H:i') ?></td>
                                <td class="text-center"><?php getStatusBadge($mission['status']); ?></td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?= route('missions_view_mission', ['id' => $mission['id']]) ?>"><i class="bi bi-eye-fill me-2"></i>Détails</a></li>
                                            <li><a class="dropdown-item" href="<?= route('missions_edit_mission', ['id' => $mission['id']]) ?>"><i class="bi bi-pencil-fill me-2"></i>Modifier</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="<?= route('missions_generate_mission_order_pdf', ['id' => $mission['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-pdf-fill me-2"></i>Imprimer PDF</a></li>
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
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1) : ?>
                            <li class="page-item"><a class="page-link" href="<?= route('missions_list_missions', array_merge($filters, ['page' => $page - 1])) ?>">Précédent</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('missions_list_missions', array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages) : ?>
                            <li class="page-item"><a class="page-link" href="<?= route('missions_list_missions', array_merge($filters, ['page' => $page + 1])) ?>">Suivant</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $badges = [
            'approved' => 'success',
            'pending' => 'warning text-dark',
            'rejected' => 'danger',
            'completed' => 'primary',
            'cancelled' => 'dark',
        ];
        $class = $badges[strtolower($status)] ?? 'secondary';
        echo "<span class=\"badge bg-{$class}\">" . htmlspecialchars(ucfirst($status)) . "</span>";
    }
}
include __DIR__ . '/../../../includes/footer.php';
?>