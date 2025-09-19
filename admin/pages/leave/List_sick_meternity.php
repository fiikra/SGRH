<?php
// --- Security Headers: Set before any output ---
// --- Prevent session fixation ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

// Get filter
$employee_nin = isset($_GET['employee_nin']) ? sanitize($_GET['employee_nin']) : '';

// Employees for filter dropdown
$employees = $db->query("SELECT nin, first_name, last_name FROM employees ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);

// Build query
$query = "SELECT l.*, e.first_name, e.last_name 
          FROM leave_requests l
          JOIN employees e ON l.employee_nin = e.nin
          WHERE l.leave_type = 'Maternite'";
$params = [];
if ($employee_nin) {
    $query .= " AND l.employee_nin = ?";
    $params[] = $employee_nin;
}
$query .= " ORDER BY l.start_date DESC, l.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sick_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Maternite - Liste des Maternites";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Maternite</h1>
        <a href="<?= route('leave_add_maternity_leave') ?>" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Ajouter une Maternite
        </a>
    </div>

    <form class="row g-3 mb-4" method="get" action="<?= route('leaves_list_sick') ?>">
        <div class="col-md-4">
            <label for="employee_nin" class="form-label">Filtrer par employé</label>
            <select name="employee_nin" id="employee_nin" class="form-select" onchange="this.form.submit()">
                <option value="">-- Tous les employés --</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= ($employee_nin === $emp['nin']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['last_name']) . ' ' . htmlspecialchars($emp['first_name']) . ' (' . htmlspecialchars($emp['nin']) . ')' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search"></i> Filtrer</button>
        </div>
        <?php if ($employee_nin): ?>
        <div class="col-auto align-self-end">
            <a href="<?= route('leaves_list_sick') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i> Réinitialiser</a>
        </div>
        <?php endif; ?>
    </form>

    <?php if (!empty($_SESSION['leave_success'])): ?>
        <div class="alert alert-success">Arrêt maladie enregistré avec succès.</div>
        <?php unset($_SESSION['leave_success']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Employé</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Jours</th>
                            <th>Justificatif</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Ajouté le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sick_leaves)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun Maternite trouvé.</td>
                            </tr>
                        <?php else: foreach ($sick_leaves as $leave): ?>
                            <tr>
                                <td><?= htmlspecialchars($leave['last_name'] . ' ' . $leave['first_name']) ?></td>
                                <td><?= formatDate($leave['start_date'], 'd/m/Y') ?></td>
                                <td><?= formatDate($leave['end_date'], 'd/m/Y') ?></td>
                                <td><?= (int)$leave['days_requested'] ?> j.</td>
                                <td>
                                    <?php if (!empty($leave['justification_path'])): ?>
                                        <a href="<?= route('leave_Leave_certificate', ['leave_id' => $leave['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-arrow-down"></i> Voir
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($leave['reason'])) ?></td>
                                <td>
                                    <span class="badge bg-success">Maternite</span>
                                </td>
                                <td><?= formatDate($leave['created_at'], 'd/m/Y H:i') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>