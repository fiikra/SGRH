<?php // /admin/pages/views/employees/partials/_profile_summary.php ?>

<div class="card shadow-sm mb-4">
    <div class="card-body p-lg-4">
        <div class="row align-items-center">

            <div class="col-lg-1 d-none d-lg-flex align-items-center justify-content-start">
                <?php if ($prev_employee): ?>
                    <a href="<?= route('employees_view', ['nin' => $prev_employee['nin']]) ?>" class="btn btn-outline-secondary btn-lg" title="Précédent: <?= htmlspecialchars($prev_employee['name']) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="col-lg-10 text-center">
                
                <div class="d-flex d-lg-none justify-content-between align-items-center mb-3">
                    <?php if ($prev_employee): ?>
                        <a href="<?= route('employees_view', ['nin' => $prev_employee['nin']]) ?>" class="btn btn-outline-secondary" title="Précédent: <?= htmlspecialchars($prev_employee['name']) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary disabled"><i class="bi bi-chevron-left"></i></span>
                    <?php endif; ?>
                    <?php if ($next_employee): ?>
                        <a href="<?= route('employees_view', ['nin' => $next_employee['nin']]) ?>" class="btn btn-outline-secondary" title="Suivant: <?= htmlspecialchars($next_employee['name']) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php else: ?>
                         <span class="btn btn-outline-secondary disabled"><i class="bi bi-chevron-right"></i></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($employee['photo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $employee['photo_path'])): ?>
                    <img src="/<?= htmlspecialchars($employee['photo_path']) ?>" class="rounded-circle shadow-lg mx-auto" width="120" height="120" alt="Photo" style="object-fit: cover; border: 4px solid #fff;">
                <?php else: ?>
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px; border: 4px solid #fff;"><i class="bi bi-person" style="font-size: 4rem;"></i></div>
                <?php endif; ?>
                
                <h2 class="mb-1 mt-3"><?= $pageTitle ?></h2>
                <h5 class="text-muted fw-normal mb-3"><?= htmlspecialchars($employee['position']) ?> / <?= htmlspecialchars($employee['department']) ?></h5>

                <div class="d-flex justify-content-center align-items-center gap-2 mb-4">
                    <span class="badge fs-6 rounded-pill bg-<?= $employee['status'] === 'active' ? 'success' : 'danger' ?>-soft text-<?= $employee['status'] === 'active' ? 'success' : 'danger' ?>">
                        <i class="bi bi-<?= $employee['status'] === 'active' ? 'check-circle' : 'pause-circle' ?> me-1"></i>
                        <?= ucfirst(htmlspecialchars($employee['status'])) ?>
                    </span>
                    <?php if ($employee['on_trial'] == 1): ?>
                        <span class="badge fs-6 rounded-pill bg-warning-soft text-warning">
                            <i class="bi bi-stopwatch"></i> Période d'essai
                        </span>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap justify-content-center align-items-center gap-2">
                    <a href="<?= route('employees_list') ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
                    <a href="<?= route('employees_edit', ['nin' => $nin]) ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Modifier</a>
                    
                    <?php if ($employee['on_trial'] == 1): ?>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#trialDecisionModal">
                            <i class="bi bi-gavel"></i> Décision P. Essai
                        </button>
                    <?php endif; ?>

                    <?php if ($employee['contract_type'] === 'cdd' && $employee['status'] === 'active'): ?>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#renewContractModal">
                            <i class="bi bi-calendar-plus-fill"></i> Renouveler Contrat
                        </button>
                    <?php endif; ?>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-printer"></i> Générer
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                            <li><a class="dropdown-item" href="<?= route('employees_generate_pdf', ['nin' => $nin]) ?>" target="_blank"><i class="bi bi-file-earmark-person me-2"></i>Fiche Employé</a></li>
                            <li><a class="dropdown-item" href="<?= route('employees_badge', ['nin' => $nin]) ?>" target="_blank"><i class="bi bi-credit-card-2-front me-2"></i>Badge</a></li>
                        </ul>
                    </div>

                    <?php if (empty($employee['user_id'])): ?>
                        <button class="btn btn-success" onclick="generateQuickAccess('<?= route('users_quick_create', ['nin' => $nin]) ?>', '<?= route('users_view') ?>', event)"><i class="bi bi-person-plus"></i> Créer Accès</button>
                    <?php else: ?>
                        <a href="<?= route('users_view', ['id' => $employee['user_id']]) ?>" class="btn btn-outline-success"><i class="bi bi-person-check"></i> Voir Accès</a>
                    <?php endif; ?>
                </div>

            </div>

            <div class="col-lg-1 d-none d-lg-flex align-items-center justify-content-end">
                <?php if ($next_employee): ?>
                    <a href="<?= route('employees_view', ['nin' => $next_employee['nin']]) ?>" class="btn btn-outline-secondary btn-lg" title="Suivant: <?= htmlspecialchars($next_employee['name']) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<style>
    .bg-success-soft {
        background-color: rgba(25, 135, 84, 0.15);
    }
    .bg-warning-soft {
        background-color: rgba(255, 193, 7, 0.15);
    }
    .bg-danger-soft {
        background-color: rgba(220, 53, 69, 0.15);
    }
</style>