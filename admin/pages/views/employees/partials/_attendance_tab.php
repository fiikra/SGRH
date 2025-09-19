<?php // /admin/pages/views/employees/partials/_attendance_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <?php
        $month_year_title_att = "Mois Inconnu";
        if ($current_dt) { 
            $month_year_title_att = formatMonthYear($current_dt);
        }
    ?>
    <h5>Registre de Présence (<?= htmlspecialchars($month_year_title_att) ?>)</h5>
    <div>
        <a href="<?= route('reports_generate_attendance_report_pdf', ['nin' => $nin, 'month' => $attendance_filter_month_str]) ?>" class="btn btn-sm btn-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> Imprimer Relevé
        </a>
        <a href="<?= route('attendance_history', ['employee_nin' => $employee['nin'], 'year' => date('Y'), 'month' => date('n')]) ?>" class="btn btn-sm btn-info">
            <i class="bi bi-calendar-range"></i> Historique Complet
        </a>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded border">
    <a href="<?= $previous_month_link ?>" class="btn btn-sm btn-outline-secondary <?= $disable_prev ? 'disabled' : '' ?>" aria-disabled="<?= $disable_prev ? 'true' : 'false' ?>">
        <i class="bi bi-arrow-left"></i> Mois Précédent
    </a>
    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($month_year_title_att) ?></h6>
    <a href="<?= $next_month_link ?>" class="btn btn-sm btn-outline-secondary <?= $disable_next ? 'disabled' : '' ?>" aria-disabled="<?= $disable_next ? 'true' : 'false' ?>">
        Mois Suivant <i class="bi bi-arrow-right"></i>
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <?php if (empty($attendance_records_for_month)): ?>
            <div class="alert alert-info d-flex align-items-center" style="height: 100%;"><i class="bi bi-info-circle-fill me-2"></i> Aucun pointage pour le mois sélectionné.</div>
        <?php else: ?>
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-sm table-hover table-bordered table-sticky-header">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Statut</th><th class="text-center">Travail WE/JF</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $attendance_code_map_display = [
                        'present' => ['label' => 'Présent (P)', 'badge' => 'bg-success text-white'],
                        'present_offday' => ['label' => 'Présent Jour Férié (TF)', 'badge' => 'bg-danger text-white'],
                        'present_weekend' => ['label' => 'Présent Weekend (TW)', 'badge' => 'bg-orange text-white'],
                        'annual_leave' => ['label' => 'Congé Annuel (C)', 'badge' => 'bg-info text-dark'],
                        'sick_leave' => ['label' => 'Maladie (M)', 'badge' => 'bg-purple text-white'],
                        'maladie' => ['label' => 'Maladie (M)', 'badge' => 'bg-purple text-white'],
                        'weekend' => ['label' => 'Repos (RC)', 'badge' => 'bg-light text-dark border'],
                        'holiday' => ['label' => 'Jour Férié (JF)', 'badge' => 'bg-light text-dark border'],
                        'absent_unjustified' => ['label' => 'Absent NJ (ANJ)', 'badge' => 'bg-danger text-white'],
                        'absent_authorized_paid' => ['label' => 'Absent AP (AAP)', 'badge' => 'bg-warning text-dark'],
                        'absent_authorized_unpaid' => ['label' => 'Absent ANP (AANP)', 'badge' => 'bg-secondary text-white'],
                        'maternity_leave' => ['label' => 'Maternité (MT)', 'badge' => 'bg-pink text-dark'],
                        'training' => ['label' => 'Formation (F)', 'badge' => 'bg-teal text-white'],
                        'mission' => ['label' => 'Mission (MS)', 'badge' => 'bg-primary text-white'],
                        'other_leave' => ['label' => 'Autre Congé (X)', 'badge' => 'bg-indigo text-white'],
                        'on_leave_from_excel_c' => ['label' => 'Congé (C) (Excel)', 'badge' => 'bg-info text-dark'],
                        'on_leave_from_excel_m' => ['label' => 'Maladie (M) (Excel)', 'badge' => 'bg-purple text-white'],
                        'on_leave_from_excel_mt' => ['label' => 'Maternité (MT) (Excel)', 'badge' => 'bg-pink text-dark'],
                        'on_leave_from_excel_f' => ['label' => 'Formation (F) (Excel)', 'badge' => 'bg-teal text-white'],
                        'on_leave_from_excel_ms' => ['label' => 'Mission (MS) (Excel)', 'badge' => 'bg-primary text-white'],
                        'on_leave_from_excel_x' => ['label' => 'Autre Congé (X) (Excel)', 'badge' => 'bg-indigo text-white'],
                        'absent_from_excel_anj' => ['label' => 'Absent NJ (ANJ) (Excel)', 'badge' => 'bg-danger text-white'],
                        'absent_from_excel_aap' => ['label' => 'Absent AP (AAP) (Excel)', 'badge' => 'bg-warning text-dark'],
                        'absent_from_excel_aanp' => ['label' => 'Absent ANP (AANP) (Excel)', 'badge' => 'bg-secondary text-white'],
                    ];
                    foreach ($attendance_records_for_month as $record):
                        $db_status_lc = strtolower($record['status'] ?? '');
                        $status_info = $attendance_code_map_display[$db_status_lc] ?? ['label' => htmlspecialchars(ucfirst(str_replace('_', ' ', $record['status'] ?? 'N/A'))), 'badge' => 'bg-light text-dark'];
                        $status_badge_class = $status_info['badge'];
                        $status_display_text = $status_info['label'];
                    ?>
                        <tr>
                            <td><?= formatDate($record['attendance_date'] ?? null, 'd/m/Y (D)') ?></td>
                            <td><span class="badge <?= $status_badge_class ?>"><?= $status_display_text ?></span></td>
                            <td class="text-center">
                                 <?php if(!empty($record['is_weekend_work'])): ?><span class="badge bg-warning text-dark" title="Travail Weekend">WE</span>
                                 <?php elseif(!empty($record['is_holiday_work'])): ?><span class="badge bg-info text-white" title="Travail Jour Férié">JF</span>
                                 <?php else: ?><small class="text-muted">Normal</small><?php endif; ?>
                            </td>
                             <td><small><?= nl2br(htmlspecialchars($record['notes'] ?? ($record['leave_type_if_absent'] ?? ''))) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 class="card-title mb-0 fw-bold">Résumé Mensuel</h6>
            </div>
            <div class="card-body p-3">
            <dl class="row mb-0 small gy-2">
                <dt class="col-sm-8">Jours Travaillés (P) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_worked_days ?> jour(s)</dd>
                <dt class="col-sm-8">Jours Travaillés Jour Férié (TF):</dt>
                <dd class="col-sm-4 text-end"><?= $total_tf_days ?> jour(s)</dd>
                <dt class="col-sm-8">Jours Travaillés Weekend (TW):</dt>
                <dd class="col-sm-4 text-end"><?= $total_tw_days ?> jour(s)</dd>
                <dt class="col-sm-8">Congés annuels (C) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_annual_leave ?> jour(s)</dd>
                <dt class="col-sm-8">Maladie (M) :</dt>
                <dd class="col-sm-4 text-end"><span class="badge bg-purple"><?= $total_sick_leave ?> jour(s)</span></dd>
                <dt class="col-sm-8">Maternité (MT) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_maternity_leave ?> jour(s)</dd>
                <dt class="col-sm-8">Formation (F) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_training_leave ?> jour(s)</dd>
                <dt class="col-sm-8">Mission (MS) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_mission_leave ?> jour(s)</dd>
                <dt class="col-sm-8">Autre absence (X) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_other_leave ?> jour(s)</dd>
                <dt class="col-sm-8">Absent autorisé payé (AAP) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_absent_justified_paid ?> jour(s)</dd>
                <dt class="col-sm-8">Absent autorisé non payé (AANP) :</dt>
                <dd class="col-sm-4 text-end"><?= $total_absent_justified_unpaid ?> jour(s)</dd>
                <dt class="col-sm-8 text-danger">Absent non justifié (ANJ) :</dt>
                <dd class="col-sm-4 text-end text-danger"><strong><?= $total_absent_unjustified ?> jour(s)</strong></dd>
                
                <dt class="col-sm-12"><hr class="my-2"></dt>
                
                <dt class="col-sm-8">Heures Supplémentaires (HS) Mois :</dt>
                <dd class="col-sm-4 text-end fw-bold"><?= number_format($monthly_hs_total_for_display, 2, ',', ' ') ?> h</dd>
                <dt class="col-sm-8">Heures de Retenue Mois :</dt>
                <dd class="col-sm-4 text-end fw-bold"><?= number_format($monthly_retenue_total_for_display, 2, ',', ' ') ?> h</dd>
            </dl>
            </div>
        </div>
    </div>
</div>
<style>
.bg-purple { background-color: #6f42c1 !important; }
.bg-pink { background-color: #e83e8c !important; }
.bg-orange { background-color: #fd7e14 !important; }
.bg-teal { background-color: #20c997 !important; }
.bg-indigo { background-color: #6610f2 !important; }
.badge.bg-info.text-dark { color: #000 !important; }
.badge.bg-warning.text-dark { color: #000 !important; }
.table-sticky-header thead th { position: sticky; top: 0; background-color: #f8f9fa; z-index: 10; box-shadow: inset 0 -2px 0 #dee2e6;}
</style>