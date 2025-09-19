<?php // /admin/pages/views/employees/partials/_career_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Évolution de Carrière & Décisions</h4>
    <?php if ($employee['status'] === 'active'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promotionModal">
        <i class="bi bi-graph-up-arrow"></i> Enregistrer une Décision
    </button>
    <?php endif; ?>
</div>

<h5>Historique des Décisions de Carrière (Notifications)</h5>
<?php if (count($decisions_history) > 0): ?>
    <div class="table-responsive mb-4">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Type Décision</th>
                    <th>Date d'Effet</th>
                    <th class="text-end">Notification PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($decisions_history as $decision): ?>
                    <tr>
                        <td><?= htmlspecialchars($decision['reference_number']) ?></td>
                        <td>
                            <?php
                            $decision_labels = [
                                'promotion_only' => 'Promotion sans augmentation',
                                'promotion_salary' => 'Promotion avec augmentation',
                                'salary_only' => 'Augmentation seule'
                            ];
                            echo $decision_labels[$decision['decision_type']] ?? 'N/A';
                            ?>
                        </td>
                        <td><?= formatDate($decision['effective_date']) ?></td>
                        <td class="text-end"> 
                            <a href="<?= route('promotions_generate_decision_pdf', ['id' => $decision['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Générer la Notification PDF">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">Aucune décision de carrière formalisée n'a été trouvée pour cet employé.</div>
<?php endif; ?>

<hr class="my-4">

<h5>Historique des Postes</h5>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Poste</th>
            <th>Département</th>
            <th>Date de Début</th>
            <th>Date de Fin</th>
            <th>Motif</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($position_history as $pos): ?>
        <tr>
            <td><?= htmlspecialchars($pos['position_title']) ?></td>
            <td><?= htmlspecialchars($pos['department']) ?></td>
            <td><?= formatDate($pos['start_date']) ?></td>
            <td><?= $pos['end_date'] ? formatDate($pos['end_date']) : '<span class="badge bg-success">Actuel</span>' ?></td>
            <td><?= htmlspecialchars($pos['change_reason']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<hr class="my-4">

<h5>Historique des Salaires</h5>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Salaire Brut</th>
            <th>Date d'effet</th>
            <th>Type de changement</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($salary_history as $sal): ?>
        <tr>
            <td><?= number_format($sal['gross_salary'], 2, ',', ' ') ?> DZD</td>
            <td><?= formatDate($sal['effective_date']) ?></td>
            <td><?= htmlspecialchars($sal['change_type']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>