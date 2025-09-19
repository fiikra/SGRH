<?php // /admin/pages/views/employees/partials/_sanctions_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Sanctions Disciplinaires</h5>
    <?php if ($employee['status'] === 'active'): ?>
    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#addSanctionModal"><i class="bi bi-exclamation-triangle-fill"></i> Nouvelle Sanction</button>
    <?php endif; ?>
</div>
<?php if ($sanctions_stmt->rowCount() > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Type de Sanction</th>
                    <th>ID</th>
                    <th>Date de la Sanction</th>
                    <th>Motif</th>
                    <th>Document</th>
                    <th>Questionnaire Référence</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($sanction = $sanctions_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                <tr>
                    <td>
                        <?php 
                            $sanction_labels = [
                                'avertissement_verbal' => 'Avertissement Verbal',
                                'avertissement_ecrit' => 'Avertissement Écrit (1er degré)',
                                'mise_a_pied_1' => 'Mise à pied 1 jour (2e degré)',
                                'mise_a_pied_2' => 'Mise à pied 2 jours (2e degré)',
                                'mise_a_pied_3' => 'Mise à pied 3 jours (2e degré)',
                                'licenciement' => 'Licenciement (3e degré)'
                            ];
                            echo htmlspecialchars($sanction_labels[$sanction['sanction_type']] ?? $sanction['sanction_type']);
                        ?>
                    </td>
                    <td><?= formatDate($sanction['reference_number']) ?></td>
                    <td><?= formatDate($sanction['sanction_date']) ?></td>
                    <td title="<?= htmlspecialchars($sanction['reason']) ?>"><?= htmlspecialchars(substr($sanction['reason'], 0, 50)) ?>...</td>
                    <td>
                        <?php if (!empty($sanction['notification_path'])): 
                            $notification_url = '/assets/uploads/sanctions/' . basename($sanction['notification_path']);
                        ?>
                            <a href="#" onclick="showPdfPreview('<?= htmlspecialchars($notification_url) ?>'); return false;" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Voir</a>
                        <?php else: ?>
                            <a href="<?= route('sanctions_generate_notification_pdf', ['ref' => $sanction['reference_number']]) ?>" class="btn btn-sm btn-outline-warning" target="_blank" title="Générer PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($sanction['questionnaire_ref'] ?? 'N/A') ?></td>
                    <td>
                        <a href="<?= route('sanctions_view_sanction', ['id' => $sanction['reference_number']]) ?>" class="btn btn-sm btn-info" title="Détails"><i class="bi bi-search"></i> Détails</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">Aucune sanction disciplinaire trouvée pour cet employé.</div>
<?php endif; ?>