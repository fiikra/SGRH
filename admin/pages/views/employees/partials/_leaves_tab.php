<?php // /admin/pages/views/employees/partials/_leaves_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Congés</h5>
    <?php if ($employee['status'] === 'active'): ?>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newAddLeaveModal">
            <i class="bi bi-plus-circle"></i> Nouvelle Demande
        </button>
        <a href="<?= route('leave_adjust_leave', ['nin' => $nin]) ?>" class="btn btn-sm btn-warning ms-1"><i class="bi bi-pencil-square"></i> Ajuster Solde</a>
    </div>
    <?php endif; ?>
</div>
<?php if ($leaves_stmt->rowCount() > 0): ?>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
    <thead><tr><th>Type de Congé</th><th>Période</th><th>Durée (jours)</th><th>Statut</th><th>Date de Demande</th><th>Actions</th></tr></thead><tbody>
    <?php while ($leave = $leaves_stmt->fetch(PDO::FETCH_ASSOC)): ?>
    <tr>
        <td>
            <strong>
                <?php
                // Assumes $leave_types_config is available or defined in a helper
                $leave_types_config = [
                    'annuel' => 'Annuel', 'reliquat' => 'Reliquat', 'recup' => 'Récupération',
                    'anticipe' => 'Anticipé', 'unpaid' => 'Sans Solde',
                    'special_mariage' => 'Spécial - Mariage', 'special_naissance' => 'Spécial - Naissance',
                    'special_deces' => 'Spécial - Décès', 'special_mariage_enf' => 'Spécial - Mariage Enfant',
                    'special_circoncision' => 'Spécial - Circoncision'
                ];
                echo htmlspecialchars($leave_types_config[$leave['leave_type']] ?? ucfirst($leave['leave_type']));
                ?>
            </strong>
        </td>
        <td>Du <?= formatDate($leave['start_date']) ?> au <?= formatDate($leave['end_date']) ?></td>
        <td class="text-center"><?= htmlspecialchars($leave['days_requested']) ?></td>
        <td><span class="badge bg-<?= getStatusBadgeClass($leave['status']) ?>"><?= ucfirst(htmlspecialchars($leave['status'])) ?></span></td>
        <td><?= formatDate($leave['created_at'], 'd/m/Y H:i') ?></td>
        <td><a href="<?= route('leave_view', ['id' => $leave['id']]) ?>" class="btn btn-sm btn-info" title="Voir la demande"><i class="bi bi-eye"></i></a>
        <?php if ($leave['status'] === 'approved' || $leave['status'] === 'prise'): ?>
            <a href="<?= route('leave_Leave_certificate', ['leave_id' => $leave['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimer l'attestation de congé"><i class="bi bi-file-earmark-pdf"></i></a>
        <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody></table></div>
<?php else: ?>
    <div class="alert alert-info">Aucune demande de congé correspondante n'a été trouvée pour cet employé.</div>
<?php endif; ?>