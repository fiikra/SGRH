<?php
/**
 * Page: List Employee Sanctions
 *
 * This page displays a paginated and filterable list of employee sanctions.
 * It follows the same Controller/View pattern as the questionnaires page.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================

// Ensure the file is not accessed directly
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}

// Redirect if the user does not have HR privileges
redirectIfNotHR();

// =========================================================================
// == CONFIGURATION
// =========================================================================

const SANCTIONS_PER_PAGE = 10;

// =========================================================================
// == MODEL / DATA LOGIC
// =========================================================================

/**
 * Fetches a paginated list of sanctions based on filters.
 *
 * @param PDO $db The database connection object.
 * @param array $filters An associative array of filters (e.g., 'search').
 * @param int $currentPage The current page number for pagination.
 * @param int $limit The number of records per page.
 * @return array An array containing the list of sanctions, total records, and total pages.
 */
function getFilteredSanctions(PDO $db, array $filters, int $currentPage, int $limit): array
{
    // --- Base Query and Parameters ---
    $baseSql = "FROM employee_sanctions s JOIN employees e ON s.employee_nin = e.nin";
    $whereConditions = " WHERE 1=1";
    $params = [];

    // --- Apply Filters ---
    if (!empty($filters['search'])) {
        $searchTerm = "%{$filters['search']}%";
        $whereConditions .= " AND (s.reference_number LIKE ? OR s.sanction_type LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
        // Add the search term for each placeholder
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    // --- Get total record count for pagination ---
    $countSql = "SELECT COUNT(s.id) " . $baseSql . $whereConditions;
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();

    // --- Calculate Pagination ---
    $totalPages = $totalRecords > 0 ? ceil($totalRecords / $limit) : 0;
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $limit;

    // --- Fetch the data for the current page ---
    $dataSql = "SELECT s.*, e.first_name, e.last_name " . $baseSql . $whereConditions . " ORDER BY s.sanction_date DESC LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($dataSql);
    
    // Bind all string parameters first.
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
    }
    // Then bind the integer parameters for LIMIT and OFFSET.
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $sanctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'sanctions' => $sanctions,
        'totalRecords' => $totalRecords,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage
    ];
}

// =========================================================================
// == VIEW HELPERS
// =========================================================================

/**
 * Gets display details (label, badge class) for a sanction type.
 *
 * @param string $type The sanction type identifier.
 * @return array An array with 'label' and 'badge' keys.
 */
function getSanctionTypeDetails(string $type): array
{
    $map = [
        'avertissement_verbal' => ['label' => 'Avertissement Verbal', 'badge' => 'secondary'],
        'avertissement_ecrit'  => ['label' => 'Avertissement Écrit', 'badge' => 'warning text-dark'],
        'mise_a_pied_1'        => ['label' => 'Mise à pied (1-3 jours)', 'badge' => 'danger'],
        'mise_a_pied_2'        => ['label' => 'Mise à pied (4-8 jours)', 'badge' => 'danger fw-bold'],
        'mise_a_pied_3'        => ['label' => 'Mise à pied (3ème degré)', 'badge' => 'dark'],
        'licenciement'         => ['label' => 'Licenciement', 'badge' => 'dark fw-bolder'],
    ];
    return $map[$type] ?? ['label' => ucfirst(str_replace('_', ' ', $type)), 'badge' => 'light'];
}

/**
 * Renders the pagination controls.
 *
 * @param int $currentPage The current active page.
 * @param int $totalPages The total number of pages.
 * @param array $queryParams Optional query parameters to preserve in links.
 * @return void
 */
function renderPagination(int $currentPage, int $totalPages, array $queryParams = []): void
{
    if ($totalPages <= 1) {
        return;
    }
    
    echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center flex-wrap">';
    
    // Previous button
    $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
    $prevPage = $currentPage - 1;
    echo "<li class='page-item $prevDisabled'><a class='page-link' href='" . route('sanctions_index', array_merge($queryParams, ['page' => $prevPage])) . "'>Précédent</a></li>";
    
    // Page number buttons
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $currentPage) ? 'active' : '';
        echo "<li class='page-item $activeClass'><a class='page-link' href='" . route('sanctions_index', array_merge($queryParams, ['page' => $i])) . "'>$i</a></li>";
    }
    
    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPage = $currentPage + 1;
    echo "<li class='page-item $nextDisabled'><a class='page-link' href='" . route('sanctions_index', array_merge($queryParams, ['page' => $nextPage])) . "'>Suivant</a></li>";
    
    echo '</ul></nav>';
}

// =========================================================================
// == CONTROLLER LOGIC
// =========================================================================

$pageTitle = "Liste des Sanctions";
$sanctionsData = [
    'sanctions' => [],
    'totalRecords' => 0,
    'totalPages' => 0,
    'currentPage' => 1
];

// --- Input Processing: Sanitize and retrieve GET parameters ---
$currentPageInput = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$filters = [
    // Ensure all inputs are sanitized before use
    'search' => sanitize($_GET['search'] ?? '')
];

// --- Data Fetching: Interact with the database ---
try {
    // Fetch main sanctions data using the abstracted function
    $sanctionsData = getFilteredSanctions($db, $filters, $currentPageInput, SANCTIONS_PER_PAGE);

} catch (PDOException $e) {
    error_log("Database error on sanctions page: " . $e->getMessage());
    flash('error', "Une erreur critique de base de données est survenue. Impossible de charger les données.");
    // Data is already initialized to empty arrays, so the page can render safely without data.
}

// Extract variables for easier access in the view
extract($sanctionsData);

// =========================================================================
// == VIEW: RENDER HTML
// =========================================================================

include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= route('sanctions_add') // Assuming a route to add sanctions exists ?>" class="btn btn-danger">
            <i class="bi bi-plus-circle"></i> Ajouter une sanction
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-filter"></i> Filtres</h5></div>
        <div class="card-body">
            <form method="get" action="" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="sanctions_index">
                <div class="col-md-10">
                    <label for="search" class="form-label">Recherche Générale</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Réf, type, nom..." value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100" data-bs-toggle="tooltip" title="Appliquer les filtres"><i class="bi bi-search"></i></button>
                    <a href="<?= route('sanctions_index') ?>" class="btn btn-secondary w-100" data-bs-toggle="tooltip" title="Réinitialiser les filtres"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Résultats <span class="badge bg-secondary rounded-pill"><?= $totalRecords ?></span></h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($sanctions)): ?>
                <div class="alert alert-info m-3">
                    Aucune sanction trouvée correspondant à vos critères de recherche.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Employé</th>
                                <th>Type de Sanction</th>
                                <th>Date de la Sanction</th>
                                <th>Motif</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sanctions as $s): ?>
                                <?php $sanctionDetails = getSanctionTypeDetails($s['sanction_type']); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($s['reference_number']) ?></strong></td>
                                    <td>
                                        <a href="<?= route('employees_view', ['nin' => $s['employee_nin']]) ?>">
                                            <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= htmlspecialchars($sanctionDetails['badge']) ?>">
                                            <?= htmlspecialchars($sanctionDetails['label']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(formatDate($s['sanction_date'])) ?></td>
                                    <td><?= htmlspecialchars(substr($s['reason'], 0, 50)) . (strlen($s['reason']) > 50 ? '...' : '') ?></td>
                                    <td class="text-end">
                                        <a href="<?= route('sanctions_view_sanction', ['id' => $s['reference_number']]) ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Voir les détails">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <?php renderPagination($currentPage, $totalPages, $filters); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '../../../../includes/footer.php';
?>
