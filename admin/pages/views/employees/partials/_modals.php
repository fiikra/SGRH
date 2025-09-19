<?php // /admin/pages/views/employees/partials/_modals.php ?>

<div class="modal fade" id="departureModal" tabindex="-1" aria-labelledby="departureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <form action="<?= route('employees_process_departure') ?>" method="post">
            <?php csrf_input(); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="departureModalLabel">Enregistrer le Départ de l'Employé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                <div class="mb-3">
                    <label for="departure_date" class="form-label">Date de Sortie</label>
                    <input type="date" class="form-control" id="departure_date" name="departure_date" required>
                </div>
                <div class="mb-3">
                    <label for="departure_reason" class="form-label">Motif du Départ</label>
                    <select class="form-select" id="departure_reason" name="departure_reason" required onchange="toggleCustomReason(this.value)">
                        <option value="" selected disabled>-- Choisir un motif --</option>
                        <option value="Fin de contrat CDD">Fin de contrat (CDD)</option>
                        <option value="Période d'essai non concluante">Période d'essai non concluante</option>
                        <option value="Rupture de contrat à l'amiable">Rupture de contrat à l'amiable</option>
                        <option value="Démission">Démission</option>
                        <option value="Retraite">Retraite</option>
                        <option value="Licenciement">Licenciement (suite à une sanction)</option>
                        <option value="Décès">Décès</option>
                        <option value="Autre">Autre (préciser)</option>
                    </select>
                </div>
                <div class="mb-3" id="custom_reason_wrapper" style="display: none;">
                    <label for="custom_reason" class="form-label">Préciser le motif</label>
                    <input type="text" class="form-control" id="custom_reason" name="custom_reason">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le Départ</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addSanctionModal" tabindex="-1" aria-labelledby="addSanctionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <form action="<?= route('sanctions_add_handler') ?>" method="post" enctype="multipart/form-data">
            <?php csrf_input(); ?>
        <div class="modal-header">
                <h5 class="modal-title" id="addSanctionModalLabel">Nouvelle Sanction Disciplinaire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">
                <div class="alert alert-info">Pour appliquer une sanction, veuillez d'abord sélectionner un questionnaire clôturé qui servira de base à votre décision.</div>
                
                <div class="mb-3">
                    <label for="questionnaire_id" class="form-label">Questionnaire Lié</label>
                    <select class="form-select" id="questionnaire_id" name="questionnaire_id" required>
                        <option value="" selected disabled>-- Sélectionner un questionnaire clôturé --</option>
                        <?php while($q_avail = $available_questionnaires_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?= $q_avail['id'] ?>"><?= htmlspecialchars($q_avail['reference_number'] . ' - ' . $q_avail['questionnaire_type'] . ' (' . formatDate($q_avail['issue_date']) . ')') ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="sanction_type" class="form-label">Type de Sanction</label>
                    <select class="form-select" name="sanction_type" required>
                           <option value="" selected disabled>-- Choisir le type --</option>
                        <optgroup label="1er Degré">
                           <option value="avertissement_ecrit">Avertissement Écrit</option>
                        </optgroup>
                        <optgroup label="2e Degré">
                           <option value="mise_a_pied_1">Mise à pied (1 jour)</option>
                           <option value="mise_a_pied_2">Mise à pied (2 jours)</option>
                           <option value="mise_a_pied_3">Mise à pied (3 jours)</option>
                        </optgroup>
                        <optgroup label="3e Degré">
                           <option value="licenciement">Licenciement</option>
                        </optgroup>
                    </select>
                </div>
                    <div class="mb-3">
                            <label for="sanction_date" class="form-label">Date de la Sanction</label>
                            <input type="date" class="form-control" name="sanction_date" value="<?= date('Y-m-d') ?>" required>
                       </div>
                <div class="mb-3">
                    <label for="reason" class="form-label">Motif / Décision finale</label>
                    <textarea class="form-control" name="reason" rows="4" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer la Sanction</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updateQuestionnaireModal" tabindex="-1" aria-labelledby="updateQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <form action="<?= route('questionnaires_update_handler') ?>" method="post">
            <?php csrf_input(); ?>
        <div class="modal-header">
                <h5 class="modal-title" id="updateQuestionnaireModalLabel">Mettre à jour le Questionnaire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="reference_number" id="update_q_ref">
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">

                <div class="mb-3">
                    <label for="update_status" class="form-label">Statut du Questionnaire</label>
                    <select class="form-select" id="update_status" name="status" required>
                        <option value="pending_response">En attente de réponse</option>
                        <option value="responded">Répondu</option>
                        <option value="decision_made">Décision prise</option>
                        <option value="closed">Clôturé</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="update_response_summary" class="form-label">Résumé de la Réponse de l'Employé (optionnel)</label>
                    <textarea name="response_summary" id="update_response_summary" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="update_decision" class="form-label">Décision Finale (optionnel)</label>
                    <textarea name="decision" id="update_decision" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer les Modifications</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addQuestionnaireModal" tabindex="-1" aria-labelledby="addQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?= route('questionnaires_questionnaire_handler') ?>" method="post">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addQuestionnaireModalLabel">Nouveau Questionnaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="questionnaire_type" class="form-label">Type de Questionnaire *</label>
                            <select class="form-select" id="questionnaire_type" name="questionnaire_type" required>
                                <option value="Entretien préalable à une sanction">Disciplinaire (Entretien préalable)</option>
                                <option value="Evaluation de performance">Evaluation de performance</option>
                                <option value="Entretien Annuel" selected>Entretien Annuel</option>
                                <option value="Autre">Autre (Personnalisé)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="issue_date" class="form-label">Date d'Émission *</label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Sujet / Contexte *</label>
                        <textarea name="subject" id="subject" class="form-control" rows="2" required></textarea>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">Questions du questionnaire</h6>
                    <div id="questions_container"></div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Générer le Questionnaire</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="voucherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Identifiants d'Accès Générés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="voucherContent" class="p-4 border rounded bg-light">
                    <div class="text-center mb-4"><h3>Vos Identifiants d'Accès</h3><p class="text-muted">Veuillez conserver ce document en lieu sûr.</p></div>
                    <div class="row mb-4"><div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">Employé</h5><p class="card-text" id="voucherEmployeeName"></p></div></div></div><div class="col-md-6"><div class="card"><div class="card-body"><h5 class="card-title">Date de Création</h5><p class="card-text" id="voucherCreationDate"></p></div></div></div></div>
                    <div class="alert alert-info"><h5 class="alert-heading">Instructions</h5><p>Ces identifiants sont à transmettre à l'employé. Il devra changer son mot de passe lors de sa première connexion.</p></div>
                    <div class="credentials-box p-3 bg-white border rounded text-center"><h4 class="mb-3">Identifiants de Connexion</h4><div class="d-flex justify-content-around my-3"><div><h6>Nom d'utilisateur</h6><div class="p-2 bg-light rounded"><code id="voucherUsername" class="fs-4"></code></div></div><div><h6>Mot de passe</h6><div class="p-2 bg-light rounded"><code id="voucherPassword" class="fs-4"></code></div></div></div><p class="text-muted mt-2">URL d'accès: <?= defined('APP_LINK') ? htmlspecialchars(APP_LINK) : '#' ?>/login</p></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="printVoucher()"><i class="bi bi-printer"></i> Imprimer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width:90vw;">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="pdfPreviewLabel">Aperçu du Document PDF</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
            <div class="modal-body" style="height:80vh;"><iframe id="pdfPreviewFrame" src="about:blank" style="width:100%;height:100%;" frameborder="0"></iframe></div>
        </div>
    </div>
</div>

<div class="modal fade" id="newAddLeaveModal" tabindex="-1" aria-labelledby="newAddLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <form method="post" action="<?= route('leave_add') ?>" id="newLeaveForm">
               <?php csrf_input(); ?>
        <div class="modal-header">
                <h5 class="modal-title" id="newAddLeaveModalLabel">Nouvelle Demande de Congé pour <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                <input type="hidden" name="source_page" value="employee_view">
                <input type="hidden" name="redirect_nin" value="<?= htmlspecialchars($employee['nin']) ?>">

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Type de Congé*</label>
                            <select name="leave_type" class="form-select" required id="modal_leave_type" onchange="updateModalLeaveTypeUI()">
                                <?php foreach ($detailed_leave_types_view as $key => $type): ?>
                                    <option value="<?= $key ?>"><?= $type['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="modal_soldes_display">
                            <label class="form-label">Soldes disponibles</label>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">Annuel: <span class="badge bg-primary rounded-pill" id="modal_annual_leave_balance"></span></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">Reliquat: <span class="badge bg-secondary rounded-pill" id="modal_remaining_leave_balance"></span></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">Récup: <span class="badge bg-info rounded-pill" id="modal_recup_balance"></span></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Date Début*</label>
                            <input type="date" name="start_date" class="form-control" required id="modal_start_date">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Fin*</label>
                            <input type="date" name="end_date" class="form-control" required id="modal_end_date">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motif*</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <form action="<?= route('promotions_handle_decision') ?>" method="POST" id="promotionForm">
               <?php csrf_input(); ?>
        <div class="modal-header">
                <h5 class="modal-title" id="promotionModalLabel">Enregistrer une Décision de Carrière</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">

                <div class="mb-3">
                    <label for="decision_type" class="form-label">Type de Décision *</label>
                    <select name="decision_type" id="decision_type" class="form-select" required onchange="togglePromotionFields()">
                        <option value="" selected disabled>-- Choisir le type d'action --</option>
                        <option value="promotion_only">Promotion sans augmentation</option>
                        <option value="promotion_salary">Promotion avec augmentation</option>
                        <option value="salary_only">Augmentation de salaire seule</option>
                    </select>
                </div>

                <div class="mb-3" id="position_fields" style="display:none;">
                    <label class="form-label">Nouveau Poste *</label>
                    <select name="new_position" id="new_position" class="form-select">
                        <option value="" disabled selected>-- Choisir un poste --</option>
                        <?php foreach($positions_list as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['nom']) ?>"><?= htmlspecialchars($pos['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3" id="salary_field" style="display:none;">
                    <label class="form-label">Nouveau Salaire Brut Mensuel (DZD) *</label>
                    <input type="number" step="0.01" name="new_salary" id="new_salary" class="form-control" placeholder="ex: 65000.00">
                </div>

                <div class="mb-3">
                    <label class="form-label">Date d'effet de la décision *</label>
                    <input type="date" name="effective_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                    <div class="mb-3">
                            <label class="form-label">Motif / Justification</label>
                            <textarea name="reason" class="form-control" rows="3"></textarea>
                       </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer la Décision</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="trialDecisionModal" tabindex="-1" aria-labelledby="trialDecisionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trialDecisionModalLabel">Décision sur la Période d'Essai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= route('trial_notifications_process_trial_decision') ?>" method="POST">
               <?php csrf_input(); ?>
            <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                    <p>Que souhaitez-vous faire concernant la période d'essai de cet employé ?</p>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="decision" id="decision_confirm" value="confirm" checked>
                        <label class="form-check-label" for="decision_confirm">
                            <strong>Confirmer l'employé</strong><br>
                            <small>Le statut "Période d'essai" sera retiré.</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="decision" id="decision_renew" value="renew">
                        <label class="form-check-label" for="decision_renew">
                            <strong>Renouveler la période d'essai</strong>
                        </label>
                        <div id="renew_options" class="mt-2" style="display:none;">
                            <label for="renewal_duration_months">Durée du renouvellement (mois):</label>
                            <select name="renewal_duration_months" class="form-select form-select-sm">
                                <option value="3">3 mois</option>
                                <option value="6">6 mois</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="decision" id="decision_terminate" value="terminate">
                        <label class="form-check-label" for="decision_terminate">
                            <strong>Terminer le contrat</strong><br>
                            <small>Motif: Période d'essai non concluante.</small>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Valider la décision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pdfModalLabel">Notification PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body" style="height:80vh;">
        <?php if (isset($pdf_url) && !empty($pdf_url)): ?>
        <iframe src="<?= htmlspecialchars($pdf_url) ?>" width="100%" height="100%" style="border:0; min-height:70vh;"></iframe>
        <?php else: ?>
        <div class="alert alert-warning">Aucun PDF à afficher.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>





<!-- NOUVEAU : Modale de Renouvellement de Contrat -->
<div class="modal fade" id="renewContractModal" tabindex="-1" aria-labelledby="renewContractModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= route('employees_renew_contract') ?>" method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="renewContractModalLabel">Renouveler le Contrat (CDD)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Le contrat de <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong> se termine actuellement le 
                        <strong><?= formatDate($employee['end_date']) ?></strong>.
                    </p>
                    <div class="mb-3">
                        <label for="renewal_duration_months" class="form-label">Sélectionnez la durée du renouvellement :</label>
                        <select class="form-select" id="renewal_duration_months" name="renewal_duration_months" required>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> mois</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        La nouvelle date de fin sera calculée en ajoutant la durée sélectionnée à la date de fin actuelle.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle-fill"></i> Confirmer le Renouvellement</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="changeToCdiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= route('employees_edit', ['nin' => $nin_to_edit]) ?>" method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="change_to_cdi">
                <div class="modal-header">
                    <h5 class="modal-title">Passage en Contrat CDI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Vous êtes sur le point de transformer le contrat de <strong><?= htmlspecialchars($employee_original['first_name'] . ' ' . $employee_original['last_name']) ?></strong> en CDI.</p>
                    <div class="mb-3">
                        <label for="effective_date_cdi" class="form-label">Date d'effet du CDI*</label>
                        <input type="date" class="form-control" name="effective_date" id="effective_date_cdi" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="alert alert-info small">
                        La date de fin de contrat sera automatiquement annulée. Cette action sera enregistrée dans l'historique de carrière.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Confirmer le passage en CDI</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="reintegrateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= route('employees_edit', ['nin' => $nin_to_edit]) ?>" method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="reintegrate">
                <div class="modal-header">
                    <h5 class="modal-title">Réintégration de l'Employé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Vous êtes sur le point de réintégrer <strong><?= htmlspecialchars($employee_original['first_name'] . ' ' . $employee_original['last_name']) ?></strong> comme employé actif.</p>
                    <div class="mb-3">
                        <label for="new_hire_date" class="form-label">Nouvelle date d'embauche / de réintégration*</label>
                        <input type="date" class="form-control" name="new_hire_date" id="new_hire_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="alert alert-info small">
                        Le statut de l'employé passera à "Actif" et ses informations de départ seront effacées. Cette action sera enregistrée dans son historique de carrière.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Confirmer la Réintégration</button>
                </div>
            </form>
        </div>
    </div>
</div>