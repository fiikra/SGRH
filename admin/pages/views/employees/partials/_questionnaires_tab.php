<?php // /admin/pages/views/employees/partials/_questionnaires_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Questionnaires</h5>
    <?php if ($employee['status'] === 'active'): ?>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionnaireModal"><i class="bi bi-patch-question-fill"></i> Générer un questionnaire</button>
    <?php endif; ?>
</div>
<?php if ($questionnaires_stmt->rowCount() > 0): ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Type</th><th>Référence</th><th>Date d'émission</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while ($q = $questionnaires_stmt->fetch(PDO::FETCH_ASSOC)): 
                    $status_labels = ['pending_response' => 'En attente', 'responded' => 'Répondu', 'decision_made' => 'Décision prise', 'closed' => 'Clôturé'];
                    $status_badges = ['pending_response' => 'warning', 'responded' => 'info', 'decision_made' => 'primary', 'closed' => 'success'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($q['questionnaire_type']) ?></td>
                    <td><?= htmlspecialchars($q['reference_number']) ?></td>
                    <td><?= formatDate($q['issue_date']) ?></td>
                    <td><span class="badge bg-<?= $status_badges[$q['status']] ?? 'secondary' ?>"><?= $status_labels[$q['status']] ?? 'Inconnu' ?></span></td>
                    <td>
                        <a href="<?= route('questionnaires_generate_questionnaire_pdf', ['id' => $q['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Imprimer le questionnaire"><i class="bi bi-file-earmark-pdf"></i></a>
                        <a href="<?= route('questionnaires_view_questionnaire', ['id' => $q['id']]) ?>" class="btn btn-sm btn-info" title="Voir le questionnaire"><i class="bi bi-eye"></i></a>
                    </td>

                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">Aucun questionnaire trouvé pour cet employé.</div>
<?php endif; ?>