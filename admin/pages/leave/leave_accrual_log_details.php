<?php
// ... (haut du fichier inchangé)

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

$pageTitle = "Historique des Mises à Jour Mensuelles des Congés";
include __DIR__ . '../../../../includes/header.php';

$logsStmt = $db->query("
    SELECT 
        log.accrual_year, 
        log.accrual_month, 
        log.executed_at, 
        log.notes,
        usr.username AS executed_by
    FROM monthly_leave_accruals_log log
    LEFT JOIN users usr ON log.executed_by_user_id = usr.id
    ORDER BY log.executed_at DESC
");
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-clock-history me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Journal des Exécutions</h5>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info">Aucun enregistrement de mise à jour mensuelle n'a été trouvé.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Période</th>
                                <th>Date d'Exécution</th>
                                <th>Exécuté Par</th>
                                <th>Détails</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars(strftime('%B %Y', mktime(0, 0, 0, $log['accrual_month'], 1, $log['accrual_year']))) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars(date('d/m/Y à H:i:s', strtotime($log['executed_at']))) ?></td>
                                    <td><?= htmlspecialchars($log['executed_by'] ?? 'Système') ?></td>
                                    <td><?= htmlspecialchars($log['notes'] ?? 'Mise à jour effectuée.') ?></td>
                                    <td class="text-end">
                                        <a href="<?= APP_LINK ?>/admin/index.php?route=leave_accrual_period_details&year=<?= $log['accrual_year'] ?>&month=<?= $log['accrual_month'] ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-list-ul"></i> Voir Détails
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <a href="<?= APP_LINK ?>/admin/index.php?route=dashboard" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour au Tableau de Bord
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>