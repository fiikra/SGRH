<?php
// pages/reports/history.php

// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- Input Processing & Filtering ---
$filters = [
    'type' => sanitize($_GET['filter_type'] ?? ''),
    'employee' => sanitize($_GET['filter_employee'] ?? ''),
    'date_from' => sanitize($_GET['filter_date_from'] ?? ''),
    'date_to' => sanitize($_GET['filter_date_to'] ?? '')
];

// --- Pagination Setup ---
$itemsPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// --- Build SQL Query ---
$whereClauses = [];
$params = [];

if (!empty($filters['type'])) {
    $whereClauses[] = "c.certificate_type = ?";
    $params[] = $filters['type'];
}
if (!empty($filters['employee'])) {
    $whereClauses[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.nin LIKE ?)";
    $searchTerm = "%{$filters['employee']}%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}
if (!empty($filters['date_from'])) {
    $whereClauses[] = "c.issue_date >= ?";
    $params[] = $filters['date_from'];
}
if (!empty($filters['date_to'])) {
    $whereClauses[] = "c.issue_date <= ?";
    $params[] = $filters['date_to'] . ' 23:59:59';
}

$whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$areFiltersActive = !empty(array_filter($filters));

// --- Get Total Count for Pagination ---
$countSql = "SELECT COUNT(*) as total 
             FROM certificates c
             JOIN employees e ON c.employee_nin = e.nin
             LEFT JOIN users u ON c.prepared_by = u.id
             $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// --- Get Paginated Results ---
$sql = "SELECT c.id, c.reference_number, c.certificate_type, c.issue_date,
               e.first_name, e.last_name, e.nin, 
               u.username as prepared_by_username
        FROM certificates c
        JOIN employees e ON c.employee_nin = e.nin
        LEFT JOIN users u ON c.prepared_by = u.id
        $whereClause
        ORDER BY c.issue_date DESC
        LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for styling
function getCertTypeInfo($type) {
    switch ($type) {
        case 'Attestation':
            return ['label' => 'Attestation de Travail', 'badge' => 'primary'];
        case 'Attestation_sold':
            return ['label' => 'Attestation de Salaire', 'badge' => 'success'];
        case 'Certficate':
            return ['label' => 'Certificat de Travail', 'badge' => 'info'];
        default:
            return ['label' => htmlspecialchars($type), 'badge' => 'secondary'];
    }
}

$pageTitle = "Historique des Attestations";
include __DIR__. '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0">Historique des Documents</h1>
        
    </div>

    <div class="accordion mb-4" id="filterAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button <?= $areFiltersActive ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                    <i class="bi bi-filter me-2"></i> Filtrer l'Historique
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse <?= $areFiltersActive ? 'show' : '' ?>">
                <div class="accordion-body">
                    <form method="get" action="<?= route('reports_certificates') ?>" class="row g-3 align-items-end">
                        <input type="hidden" name="route" value="reports_certificates">
                        
                        <div class="col-md-6 col-lg-3">
                            <label for="filter_type" class="form-label">Type</label>
                            <select class="form-select" id="filter_type" name="filter_type">
                                <option value="">Tous les types</option>
                                <option value="Attestation" <?= $filters['type'] === 'Attestation' ? 'selected' : '' ?>>Attestation de Travail</option>
                                <option value="Attestation_sold" <?= $filters['type'] === 'Attestation_sold' ? 'selected' : '' ?>>Attestation de Salaire</option>
                                <option value="Certficate" <?= $filters['type'] === 'Certficate' ? 'selected' : '' ?>>Certificat de Travail</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label for="filter_employee" class="form-label">Employé</label>
                            <input type="text" class="form-control" id="filter_employee" name="filter_employee" value="<?= htmlspecialchars($filters['employee']) ?>" placeholder="Nom, Prénom ou NIN">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label for="filter_date_from" class="form-label">Date de Début</label>
                            <input type="date" class="form-control" id="filter_date_from" name="filter_date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label for="filter_date_to" class="form-label">Date de Fin</label>
                            <input type="date" class="form-control" id="filter_date_to" name="filter_date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> Filtrer</button>
                            <a href="<?= route('reports_certificates') ?>" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Réinitialiser</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0">
                Résultats 
                <span class="badge bg-secondary">
                    <?= $totalItems ?> total (Page <?= $currentPage ?> sur <?= $totalPages ?>)
                </span>
            </h5>
            
        </div>
        <div class="card-body p-0">
            <?php if (count($certificates) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0" id="certificatesTable">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Employé</th>
                                <th scope="col">Type</th>
                                <th scope="col" class="d-none d-md-table-cell">Référence</th>
                                <th scope="col">Date</th>
                                <th scope="col" class="d-none d-sm-table-cell">Préparé par</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $index => $cert): ?>
                                <?php $typeInfo = getCertTypeInfo($cert['certificate_type']); ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td>
                                        <a href="<?= route('employees_view', ['nin' => $cert['nin']]) ?>">
                                            <?= htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']) ?>
                                        </a>
                                        <small class="d-block text-muted">NIN: <?= htmlspecialchars($cert['nin']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $typeInfo['badge'] ?>"><?= $typeInfo['label'] ?></span>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($cert['reference_number']) ?></td>
                                    <td>
                                        <span class="d-inline d-sm-none"><?= formatDate($cert['issue_date'], 'd/m/y') ?></span>
                                        <span class="d-none d-sm-inline"><?= formatDate($cert['issue_date'], 'd/m/Y H:i') ?></span>
                                    </td>
                                    <td class="d-none d-sm-table-cell"><?= htmlspecialchars($cert['prepared_by_username'] ?? 'N/A') ?></td>
                                    <td class="text-end">
                                        <a href="<?= route('reports_view_certificate', ['ref' => $cert['reference_number']]) ?>" class="btn btn-sm btn-outline-primary" target="_blank" data-bs-toggle="tooltip" title="Voir le PDF">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav class="d-flex justify-content-center mt-3">
                    <ul class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl(1) ?>" aria-label="First">
                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($currentPage - 1) ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Show page numbers
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildPaginationUrl($i) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; 
                        
                        if ($endPage < $totalPages) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($currentPage + 1) ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildPaginationUrl($totalPages) ?>" aria-label="Last">
                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="alert alert-info m-3">Aucun document ne correspond à vos critères de recherche.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function exportTableToExcel(tableID, filename = 'historique_documents.xlsx'){
    // Select the table
    const table = document.getElementById(tableID);
    
    // Clone the table to modify it for export
    const tableClone = table.cloneNode(true);
    
    // Remove action buttons from the cloned table
    const actionCells = tableClone.querySelectorAll('td.text-end, th.text-end');
    actionCells.forEach(cell => cell.remove());
    
    // Create workbook
    const wb = XLSX.utils.table_to_book(tableClone, {
        sheet: "Historique",
        raw: true // Preserve raw values (don't convert dates/numbers)
    });
    
    // Generate file and download
    XLSX.writeFile(wb, filename, {
        bookType: 'xlsx',
        type: 'array'
    });
}
</script>

<?php 
// Helper function to build pagination URLs
function buildPaginationUrl($page) {
    $queryParams = $_GET;
    $queryParams['page'] = $page;
    // Remove the existing 'route' parameter if it exists to avoid duplication
    unset($queryParams['route']);
    // Build the URL with the base route and new query parameters
    return route('reports_certificates') . '&' . http_build_query($queryParams);
}

include __DIR__. '../../../../includes/footer.php'; 
?>