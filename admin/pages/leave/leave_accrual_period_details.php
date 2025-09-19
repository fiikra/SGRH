<?php
// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

// Get and validate year/month from URL
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

if (!$year || !$month) {
    // Redirect if parameters are missing or invalid
    header('Location: ' . APP_LINK . '/admin/index.php?route=leave_accrual_log_details');
    exit;
}

$pageTitle = "Détails - " . strftime('%B %Y', mktime(0, 0, 0, $month, 1, $year));
include __DIR__ . '../../../../includes/header.php';

// Fetch detailed data for the specified period from the `leave_balance_updates` table
$detailsStmt = $db->prepare("
    SELECT 
        lbu.update_date,
        lbu.employee_nin,
        lbu.days_added,
        e.first_name,
        e.last_name
    FROM leave_balance_updates lbu
    JOIN employees e ON lbu.employee_nin = e.nin
    WHERE lbu.year = :year AND lbu.month = :month AND lbu.operation_type = 'acquisition'
    ORDER BY e.last_name, e.first_name
");
$detailsStmt->execute([':year' => $year, ':month' => $month]);
$updates = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-people-fill me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Liste des employés mis à jour (Solde +<?= htmlspecialchars('2.5') ?> jours)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($updates)): ?>
                <div class="alert alert-warning">Aucun détail de mise à jour trouvé pour cette période.</div>
            <?php else: ?>
                <p class="text-muted">Total d'employés mis à jour pour cette période : <strong><?= count($updates) ?></strong></p>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>NIN de l'employé</th>
                                <th>Nom Complet</th>
                                <th>Date de la Mise à Jour</th>
                                <th>Jours Ajoutés</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($updates as $update): ?>
                                <tr>
                                    <td><?= htmlspecialchars($update['employee_nin']) ?></td>
                                    <td>
                                        <a href="<?= APP_LINK ?>/admin/index.php?route=employees_view&nin=<?= htmlspecialchars($update['employee_nin']) ?>">
                                            <?= htmlspecialchars($update['first_name'] . ' ' . $update['last_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($update['update_date']))) ?></td>
                                    <td>+<?= htmlspecialchars($update['days_added']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <a href="<?= APP_LINK ?>/admin/index.php?route=leave_accrual_log_details" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour à l'historique
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>