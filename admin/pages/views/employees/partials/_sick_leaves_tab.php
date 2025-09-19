<?php // /admin/pages/views/employees/partials/_sick_leaves_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Congés Maladie</h5>
    <?php if ($employee['status'] === 'active'): ?>
    <a href="<?= route('leave_add_sick_leave', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Ajouter un congé maladie</a>
    <?php endif; ?>
</div>
<?php if ($sick_leaves_stmt->rowCount() > 0): ?>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
    <thead><tr><th>Période</th><th>Durée (jours)</th><th>Statut</th><th>Justificatif</th></tr></thead><tbody>
    <?php while ($sick_leave = $sick_leaves_stmt->fetch(PDO::FETCH_ASSOC)): ?>
    <tr>
        <td>Du <?= formatDate($sick_leave['start_date']) ?> au <?= formatDate($sick_leave['end_date']) ?></td>
        <td class="text-center"><?= htmlspecialchars($sick_leave['days_requested']) ?></td>
        <td><span class="badge bg-<?= $sick_leave['status'] === 'approved' ? 'success' : ($sick_leave['status'] === 'rejected' ? 'danger' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($sick_leave['status'])) ?></span></td>
        <td>
            <?php if (!empty($sick_leave['justification_path'])): 
                $justification_url = '/assets/uploads/sick_justifications/' . basename($sick_leave['justification_path']);
            ?>
                <a href="#" onclick="showPdfPreview('<?= htmlspecialchars($justification_url) ?>'); return false;" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Voir</a>
            <?php else: ?>
                <span class="text-muted">N/A</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody></table></div>
<?php else: ?>
    <div class="alert alert-info">Aucun congé maladie trouvé pour cet employé.</div>
<?php endif; ?>