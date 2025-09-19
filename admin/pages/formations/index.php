<?php
if (!defined('APP_SECURE_INCLUDE')) { http_response_code(403); die('Direct access not allowed.'); }
redirectIfNotAdminOrHR();

$pageTitle = "Liste des Formations";
include __DIR__ . '../../../../includes/header.php';

// --- Filtering & Searching Logic ---

// Get filter values from the URL, providing default empty values
$search_term = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Base SQL query
$sql = "
    SELECT f.*, COUNT(fp.id) as participant_count
    FROM formations f
    LEFT JOIN formation_participants fp ON f.id = fp.formation_id
";

// Dynamically build the WHERE clause
$whereClauses = [];
$params = [];

if (!empty($search_term)) {
    $whereClauses[] = "(f.title LIKE ? OR f.trainer_name LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if (!empty($status_filter)) {
    $whereClauses[] = "f.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $whereClauses[] = "f.start_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereClauses[] = "f.end_date <= ?";
    $params[] = $date_to;
}

// Append WHERE clause if any filters are active
if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

// Add GROUP BY and ORDER BY clauses
$sql .= " GROUP BY f.id ORDER BY f.start_date DESC";

// Prepare and execute the final query
$stmt = $db->prepare($sql);
$stmt->execute($params);
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-mortarboard-fill me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="index.php?route=formations_add" class="btn btn-primary">
            <i class="bi bi-plus-circle-fill me-2"></i>Ajouter une Formation
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>Filtrer et Rechercher</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="index.php">
                <input type="hidden" name="route" value="formations_list">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Rechercher (Titre, Formateur)</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Ex: Sécurité, Informatique...">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Statut</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">Tous</option>
                            <option value="Planifiée" <?= ($status_filter === 'Planifiée') ? 'selected' : '' ?>>Planifiée</option>
                            <option value="Terminée" <?= ($status_filter === 'Terminée') ? 'selected' : '' ?>>Terminée</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Début Après Le</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Fin Avant Le</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <a href="index.php?route=formations_list" class="btn btn-outline-secondary">Réinitialiser</a>
                    <button type="submit" class="btn btn-primary">Appliquer les Filtres</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Titre de la Formation</th>
                            <th>Formateur / École</th>
                            <th>Dates</th>
                            <th>Participants</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($formations)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted p-4">
                                    Aucune formation ne correspond à vos critères de recherche.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($formations as $formation): ?>
                                <tr>
                                    <td><span class="badge bg-dark"><?= htmlspecialchars($formation['reference_number']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($formation['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($formation['trainer_name']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($formation['start_date'])) ?> au <?= date('d/m/Y', strtotime($formation['end_date'])) ?></td>
                                    <td><span class="badge bg-secondary"><?= $formation['participant_count'] ?></span></td>
                                    <td>
                                        <?php if ($formation['status'] === 'Terminée'): ?>
                                            <span class="badge bg-success">Terminée</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Planifiée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?route=formations_view&id=<?= $formation['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye-fill"></i> Voir les Détails
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>