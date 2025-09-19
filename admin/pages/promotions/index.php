<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- Setup search and pagination ---
$search_term = sanitize($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// --- Build Query (REFACTORED) ---
// This block now uses unique placeholders to prevent PDO errors.
$sql_base = "FROM promotion_decisions d JOIN employees e ON d.employee_nin = e.nin";
$where_clause = "";
$params = [];

if (!empty($search_term)) {
    $search_like = "%" . $search_term . "%";
    $conditions = [];

    // Use a unique placeholder for each column being searched
    $conditions[] = "e.first_name LIKE :search_fname";
    $params[':search_fname'] = $search_like;

    $conditions[] = "e.last_name LIKE :search_lname";
    $params[':search_lname'] = $search_like;

    $conditions[] = "e.nin LIKE :search_nin";
    $params[':search_nin'] = $search_like;

    $conditions[] = "d.reference_number LIKE :search_ref";
    $params[':search_ref'] = $search_like;
    
    // Combine the individual conditions with OR
    if (!empty($conditions)) {
        $where_clause = " WHERE (" . implode(' OR ', $conditions) . ")";
    }
}

// --- Fetch Data (REFACTORED) ---
// Count total records for pagination
$count_stmt = $db->prepare("SELECT COUNT(d.id) " . $sql_base . $where_clause);
if (!empty($params)) {
    $count_stmt->execute($params);
} else {
    $count_stmt->execute();
}
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch the paginated records for display
$decisions_stmt = $db->prepare("SELECT d.*, e.first_name, e.last_name " . $sql_base . $where_clause . " ORDER BY d.issue_date DESC, d.id DESC LIMIT :limit OFFSET :offset");

// Consolidate all parameters into one array for execution
$exec_params = $params;
$exec_params[':limit'] = $limit;
$exec_params[':offset'] = $offset;

$decisions_stmt->execute($exec_params);
$decisions = $decisions_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Historique des Décisions de Carrière";
include __DIR__ . '../../../../includes/header.php';
?>

<style>
    /* Responsive Table Styling for screens smaller than 768px */
    @media screen and (max-width: 767px) {
        .table-responsive-stack thead {
            display: none; /* Hide table headers on mobile */
        }
        .table-responsive-stack tbody tr {
            display: block;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
        }
        .table-responsive-stack tbody td {
            display: block;
            text-align: right; /* Align cell content to the right */
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 50%; /* Make space for the label */
        }
        .table-responsive-stack tbody tr:last-child {
             margin-bottom: 0;
        }
        .table-responsive-stack tbody td:last-child {
            border-bottom: none;
        }
        .table-responsive-stack tbody td::before {
            content: attr(data-label); /* Use data-label for the heading */
            position: absolute;
            left: 0;
            width: 45%;
            padding-left: 1rem;
            font-weight: bold;
            text-align: left;
            white-space: nowrap;
        }
        /* Center align the action button cell content on mobile */
        .table-responsive-stack td.action-cell {
             text-align: center;
             padding-left: 1rem; /* Restore padding for centered content */
        }
    }
</style>

<div class="container-fluid">
    <h1 class="h3 mb-4"><i class="bi bi-gavel me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>

    <div class="card shadow-sm">
        <div class="card-header py-3">
            <form method="get" action="<?= route('promotions_index') ?>" class="d-flex flex-wrap flex-sm-nowrap">
                <input type="hidden" name="route" value="promotions_index">
                <input type="text" name="search" class="form-control me-2 mb-2 mb-sm-0" placeholder="Rechercher par nom, NIN, ou référence..." value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit" class="btn btn-info me-2"><i class="bi bi-search"></i></button>
                <a href="<?= route('promotions_index') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></a>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 table-responsive-stack">
                    <thead class="table-light">
                        <tr>
                            <th>Employé</th>
                            <th>Référence</th>
                            <th>Type</th>
                            <th>Date d'Effet</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($decisions)): ?>
                            <tr><td colspan="5" class="text-center p-4">Aucune décision trouvée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($decisions as $decision): ?>
                                <tr>
                                    <td data-label="Employé">
                                        <a href="<?= route('employees_view', ['nin' => $decision['employee_nin']]) ?>#career"><?= htmlspecialchars($decision['first_name'] . ' ' . $decision['last_name']) ?></a>
                                    </td>
                                    <td data-label="Référence">
                                        <strong><?= htmlspecialchars($decision['reference_number']) ?></strong>
                                    </td>
                                    <td data-label="Type">
                                        <?php
                                            $decision_labels = ['promotion_only' => 'Promotion', 'promotion_salary' => 'Promotion & Augmentation', 'salary_only' => 'Augmentation'];
                                            echo $decision_labels[$decision['decision_type']] ?? 'N/A';
                                        ?>
                                    </td>
                                    <td data-label="Date d'Effet"><?= htmlspecialchars(formatDate($decision['effective_date'])) ?></td>
                                    <td class="text-center action-cell">
                                        <a href="<?= route('promotions_generate_decision_pdf', ['id' => $decision['id']]) ?>" class="btn btn-sm btn-danger" target="_blank" title="Générer la Notification PDF">
                                            <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= route('promotions_index', ['page' => $page - 1, 'search' => $search_term]) ?>">Précédent</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>" aria-current="page">
                            <a class="page-link" href="<?= route('promotions_index', ['page' => $i, 'search' => $search_term]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= route('promotions_index', ['page' => $page + 1, 'search' => $search_term]) ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>