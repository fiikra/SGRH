<?php
/**
 * Page: List Questionnaires
 *
 * This page displays a paginated and filterable list of employee questionnaires.
 * It follows a basic Controller/View pattern within a single file.
 * - The "Controller" logic at the top handles input, database interaction, and prepares data.
 * - The "View" logic at the bottom handles the HTML presentation.
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

const QUESTIONNAIRES_PER_PAGE = 10;

// =========================================================================
// == MODEL / DATA LOGIC
// =========================================================================

/**
 * Fetches a paginated list of questionnaires based on filters, inspired by the employee list pattern.
 *
 * @param PDO $db The database connection object.
 * @param array $filters An associative array of filters (e.g., 'search').
 * @param int $currentPage The current page number for pagination.
 * @param int $limit The number of records per page.
 * @return array An array containing the list of questionnaires, total records, and total pages.
 */
function getFilteredQuestionnaires(PDO $db, array $filters, int $currentPage, int $limit): array
{
    // --- Base Query and Parameters ---
    $baseSql = "FROM employee_questionnaires q JOIN employees e ON q.employee_nin = e.nin";
    $whereConditions = " WHERE 1=1";
    $params = [];

    // --- Apply Filters ---
    if (!empty($filters['search'])) {
        $searchTerm = "%{$filters['search']}%";
        $whereConditions .= " AND (q.reference_number LIKE ? OR q.subject LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
        // Add the search term for each placeholder
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    // --- Get total record count for pagination ---
    $countSql = "SELECT COUNT(q.id) " . $baseSql . $whereConditions;
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
    $dataSql = "SELECT q.*, e.first_name, e.last_name " . $baseSql . $whereConditions . " ORDER BY q.issue_date DESC LIMIT ? OFFSET ?";
    
    // Add limit and offset to the parameters array for the final query
    $dataParams = array_merge($params, [$limit, $offset]);
    
    $stmt = $db->prepare($dataSql);
    
    // PDO cannot bind integers directly in execute with LIMIT/OFFSET, so we bind them manually.
    // We bind all string parameters first.
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
    }
    // Then we bind the integer parameters for LIMIT and OFFSET.
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $questionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'questionnaires' => $questionnaires,
        'totalRecords' => $totalRecords,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage
    ];
}

// =========================================================================
// == VIEW HELPERS
// =========================================================================

/**
 * Gets display details (label, badge class) for a questionnaire status.
 *
 * @param string $status The status identifier.
 * @return array An array with 'label' and 'badge' keys.
 */
function getQuestionnaireStatusDetails(string $status): array
{
    $map = [
        'pending_response' => ['label' => 'En attente', 'badge' => 'warning text-dark'],
        'responded'        => ['label' => 'Répondu', 'badge' => 'info'],
        'decision_made'    => ['label' => 'Décision prise', 'badge' => 'primary'],
        'closed'           => ['label' => 'Clôturé', 'badge' => 'success']
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'badge' => 'secondary'];
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
    echo "<li class='page-item $prevDisabled'><a class='page-link' href='" . route('questionnaires_index', array_merge($queryParams, ['page' => $prevPage])) . "'>Précédent</a></li>";
    
    // Page number buttons
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $currentPage) ? 'active' : '';
        echo "<li class='page-item $activeClass'><a class='page-link' href='" . route('questionnaires_index', array_merge($queryParams, ['page' => $i])) . "'>$i</a></li>";
    }
    
    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
    $nextPage = $currentPage + 1;
    echo "<li class='page-item $nextDisabled'><a class='page-link' href='" . route('questionnaires_index', array_merge($queryParams, ['page' => $nextPage])) . "'>Suivant</a></li>";
    
    echo '</ul></nav>';
}

// =========================================================================
// == CONTROLLER LOGIC
// =========================================================================

$pageTitle = "Liste des Questionnaires";
$questionnaireData = [
    'questionnaires' => [],
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
    // Fetch main questionnaire data using the abstracted function
    $questionnaireData = getFilteredQuestionnaires($db, $filters, $currentPageInput, QUESTIONNAIRES_PER_PAGE);

} catch (PDOException $e) {
    error_log("Database error on questionnaires page: " . $e->getMessage());
    flash('error', "Une erreur critique de base de données est survenue. Impossible de charger les données.");
    // Data is already initialized to empty arrays, so the page can render safely without data.
}

// Extract variables for easier access in the view (e.g., $questionnaires, $totalRecords)
extract($questionnaireData);

// =========================================================================
// == VIEW: RENDER HTML
// =========================================================================

include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= route('employees_list') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Créer un questionnaire
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-filter"></i> Filtres</h5></div>
        <div class="card-body">
            <form method="get" action="" class="row g-3 align-items-end">
                <input type="hidden" name="route" value="questionnaires_index">
                <div class="col-md-10">
                    <label for="search" class="form-label">Recherche Générale</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Réf, sujet, nom..." value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100" data-bs-toggle="tooltip" title="Appliquer les filtres"><i class="bi bi-search"></i></button>
                    <a href="<?= route('questionnaires_index') ?>" class="btn btn-secondary w-100" data-bs-toggle="tooltip" title="Réinitialiser les filtres"><i class="bi bi-x-lg"></i></a>
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
            <?php if (empty($questionnaires)): ?>
                <div class="alert alert-info m-3">
                    Aucun questionnaire trouvé correspondant à vos critères de recherche.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Employé</th>
                                <th>Type</th>
                                <th>Date d'émission</th>
                                <th>Statut</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questionnaires as $q): ?>
                                <?php $statusDetails = getQuestionnaireStatusDetails($q['status']); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($q['reference_number']) ?></strong></td>
                                    <td>
                                        <a href="<?= route('employees_view', ['nin' => $q['employee_nin']]) ?>">
                                            <?= htmlspecialchars($q['first_name'] . ' ' . $q['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($q['questionnaire_type']) ?></td>
                                    <td><?= htmlspecialchars(formatDate($q['issue_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= htmlspecialchars($statusDetails['badge']) ?>">
                                            <?= htmlspecialchars($statusDetails['label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= route('questionnaires_view_questionnaire', ['id' => $q['id']]) ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="Voir les détails">
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
