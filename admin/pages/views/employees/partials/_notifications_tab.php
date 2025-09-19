<?php // /admin/pages/views/employees/partials/_notifications_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Notifications (Période d'essai)</h5>
</div>
<?php if ($notifications_stmt->rowCount() > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Décision</th>
                    <th class="text-end">PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($noti = $notifications_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($noti['reference_number']) ?></td>
                        <td><?= formatDate($noti['issue_date'], 'd/m/Y') ?></td>
                        <td>
                            <?php
                            if ($noti['decision'] === 'confirm') echo "<span class='badge bg-success'>Confirmation</span>";
                            elseif ($noti['decision'] === 'renew') echo "<span class='badge bg-warning text-dark'>Renouvellement</span>";
                            else echo "<span class='badge bg-danger'>Fin de contrat</span>";
                            ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= route('trial_notifications_trial_notification_view', ['ref' => $noti['reference_number']]) ?>" class="btn btn-info" data-bs-toggle="tooltip" title="Voir les détails">
                                <i class="bi bi-search"></i> Détails
                            </a>
                           <a href="<?= route('trial_notifications_generate_notification_pdf', ['ref' => $noti['reference_number']]) ?>" class="btn btn-sm btn-outline-danger" title="Générer le PDF" target="_blank">
                           <i class="bi bi-file-earmark-pdf"></i> PDF
                           </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">Aucune notification de période d'essai trouvée pour cet employé.</div>
<?php endif; ?>