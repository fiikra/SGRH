<?php // /admin/pages/views/employees/partials/_formations_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Formations Suivies</h5>
</div>
<?php if (empty($formations_history)): ?>
    <div class="alert alert-info">Cet employé n'est inscrit à aucune formation pour le moment.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Titre de la Formation</th>
                    <th>Formateur / École</th>
                    <th>Période</th>
                    <th>Statut Formation</th>
                    <th>Statut Participant</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formations_history as $formation): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($formation['title']) ?></strong></td>
                        <td><?= htmlspecialchars($formation['trainer_name']) ?></td>
                        <td>Du <?= formatDate($formation['start_date']) ?> au <?= formatDate($formation['end_date']) ?></td>
                        <td>
                            <?php if ($formation['formation_status'] === 'Terminée'): ?>
                                <span class="badge bg-success">Terminée</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Planifiée</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $p_status = $formation['participant_status'];
                                $p_badge = 'secondary'; // Default for 'Inscrit'
                                if ($p_status === 'Complété') $p_badge = 'success';
                                if ($p_status === 'Annulé') $p_badge = 'danger';
                            ?>
                            <span class="badge bg-<?= $p_badge ?>"><?= htmlspecialchars($p_status) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= route('formations_view', ['id' => $formation['id']]) ?>" class="btn btn-sm btn-info" title="Voir les détails de la formation">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>