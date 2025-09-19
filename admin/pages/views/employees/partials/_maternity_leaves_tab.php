<?php // /admin/pages/views/employees/partials/_maternity_leaves_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Congés Maternité</h5>
    <?php if ($employee['status'] === 'active'): ?>
    <a href="<?= route('leave_add_maternity_leave', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Ajouter un congé maternité</a>
    <?php endif; ?>
</div>
<?php if (isset($maternity_leaves_stmt) && $maternity_leaves_stmt->rowCount() > 0): ?>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
    <thead><tr><th>Période</th><th>Durée (jours)</th><th>Statut</th></tr></thead><tbody>
    <?php while ($maternity_leave = $maternity_leaves_stmt->fetch(PDO::FETCH_ASSOC)): ?>
    <tr>
        <td>Du <?= formatDate($maternity_leave['start_date']) ?> au <?= formatDate($maternity_leave['end_date']) ?></td>
        <td class="text-center"><?= htmlspecialchars($maternity_leave['days_requested']) ?></td>
        <td><span class="badge bg-<?= $maternity_leave['status'] === 'approved' ? 'success' : ($maternity_leave['status'] === 'rejected' ? 'danger' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($maternity_leave['status'])) ?></span></td>
    </tr>
    <?php endwhile; ?>
    </tbody></table></div>
<?php else: ?>
    <div class="alert alert-info">Aucun congé de maternité trouvé pour cette employée.</div>
<?php endif; ?>