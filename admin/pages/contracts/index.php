<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}



redirectIfNotHR();


// --- 1. SEARCH AND PAGINATION SETUP ---
$search_term = sanitize($_GET['search'] ?? '');
$page = isset($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 10; // Max 10 contracts per page
$offset = ($page - 1) * $limit;

// --- 2. DATABASE QUERIES ---
$sql_base = "FROM contrats c JOIN employees e ON c.employe_nin = e.nin";
$where_clause = "";
$params = [];

// Improved search: allow by NIN as well, and case-insensitive
if (!empty($search_term)) {
    $where_clause = " WHERE (
        e.first_name LIKE :search 
        OR e.last_name LIKE :search 
        OR CONCAT(e.first_name, ' ', e.last_name) LIKE :search
        OR c.reference_number LIKE :search
        OR e.nin LIKE :search
    )";
    $params[':search'] = "%" . $search_term . "%";
}

// Query to get the total count of contracts for pagination
$count_stmt = $db->prepare("SELECT COUNT(c.id) " . $sql_base . $where_clause);
$count_stmt->execute($params);
$total_contracts = $count_stmt->fetchColumn();
$total_pages = $total_contracts > 0 ? ceil($total_contracts / $limit) : 1;

// Query to fetch the contracts for the current page
$contracts_stmt = $db->prepare("SELECT c.*, e.first_name, e.last_name, e.nin " . $sql_base . $where_clause . " ORDER BY c.date_debut DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $contracts_stmt->bindValue($key, $value);
}
$contracts_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$contracts_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$contracts_stmt->execute();
$contracts = $contracts_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. EXPIRING CONTRACT ALERTS (this logic remains the same) ---
$today = new DateTime();
$alert_contracts = [];
$all_contracts_stmt = $db->query("SELECT c.*, e.first_name, e.last_name FROM contrats c JOIN employees e ON c.employe_nin = e.nin");
while ($contract = $all_contracts_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (in_array($contract['type_contrat'], ['cdd', 'stage', 'interim']) && !empty($contract['date_fin'])) {
        try {
            $date_fin = new DateTime($contract['date_fin']);
            if ($date_fin > $today && $today->diff($date_fin)->days <= 30) {
                $alert_contracts[] = $contract;
            }
        } catch (Exception $e) { /* Ignore */ }
    }
}

$pageTitle = "Gestion des Contrats";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestion des Contrats</h2>
        <a href="<?= route('contracts_add') ?>" class="btn btn-primary"><i class="bi bi-plus-circle-fill me-2"></i>Générer un Contrat</a>
    </div>

    

    <?php if (!empty($alert_contracts)): ?>
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Alerte: Contrats Arrivant à Expiration</h5>
            <ul class="mb-0">
                <?php foreach ($alert_contracts as $ac): ?>
                    <li>
                        <strong><?= htmlspecialchars($ac['first_name'] . ' ' . $ac['last_name']) ?></strong> - Le contrat expire le <strong><?= htmlspecialchars(formatDate($ac['date_fin'])) ?></strong>.
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="row justify-content-between align-items-center">
                <div class="col-md-auto">
                    <i class="bi bi-list-ul me-2"></i>Contrats Enregistrés
                </div>
                <div class="col-md-4">
                    <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                    <form method="get" action="<?= route('contracts_index') ?>" class="d-flex">
                        <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Rechercher par nom, NIN, référence..." value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="btn btn-sm btn-info"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Employé</th>
                            <th>Poste</th>
                            <th>Type</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Réf. Contrat</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contracts)): ?>
                            <tr><td colspan="7" class="text-center">Aucun contrat trouvé. <?= !empty($search_term) ? 'Essayez une autre recherche.' : '' ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($contracts as $contract): ?>
                                <tr>
                                    <td>
                                        <a href="<?= route('employees_view', ['nin' => $contract['nin']]) ?>" class="text-decoration-none" title="Voir le profil de l'employé">
                                            <?= htmlspecialchars($contract['first_name'] . ' ' . $contract['last_name']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($contract['poste']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= strtoupper(htmlspecialchars($contract['type_contrat'])) ?></span></td>
                                    <td><?= htmlspecialchars(formatDate($contract['date_debut'])) ?></td>
                                    <td><?= !empty($contract['date_fin']) ? htmlspecialchars(formatDate($contract['date_fin'])) : 'N/A' ?></td>
                                    <td><strong><?= htmlspecialchars($contract['reference_number'] ?? 'N/G') ?></strong></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <a href="<?= route('contracts_edit', ['id' => $contract['id']]) ?>" class="btn btn-sm btn-secondary" title="Modifier"><i class="bi bi-pencil-fill"></i></a>
                                            <a href="<?= route('contracts_generate_pdf', ['id' => $contract['id']]) ?>" class="btn btn-sm btn-danger" target="_blank" title="PDF Contrat"><i class="bi bi-file-earmark-pdf-fill"></i></a>
                                            <a href="<?= route('contracts_generate_pv', ['id' => $contract['id']]) ?>" class="btn btn-sm btn-warning" target="_blank" title="PDF PV d'Installation"><i class="bi bi-file-person-fill"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= route('contracts_index', ['page' => $page - 1, 'search' => $search_term]) ?>">Précédent</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= route('contracts_index', ['page' => $i, 'search' => $search_term]) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= route('contracts_index', ['page' => $page + 1, 'search' => $search_term]) ?>">Suivant</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>