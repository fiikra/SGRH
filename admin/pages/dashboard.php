<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403); exit('No direct access allowed');
}
redirectIfNotAdminOrHR();
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: ../auth/login.php"); exit;
}

// --- CHECK FOR MONTHLY LEAVE UPDATE ---
$currentYear = date('Y');
$currentMonth = date('m');
$updateLogStmt = $db->prepare(
    "SELECT notes FROM monthly_leave_accruals_log WHERE accrual_year = ? AND accrual_month = ?"
);
$updateLogStmt->execute([$currentYear, $currentMonth]);
$updateDone = $updateLogStmt->fetch(PDO::FETCH_ASSOC);
// --- END CHECK ---

// Fetch stats (your existing queries)...
// Assumes $stats = ['total_employees'=>..., 'on_leave_today'=>..., ...]
// and $lists = ['contracts'=>..., 'on_maternity'=>..., ...] are defined from your database queries.

include __DIR__.'../../../includes/header.php';
$pageTitle = "Tableau de Bord";
?>

<div class="container-fluid py-4">
  <h1 class="mb-4"><?= htmlspecialchars($pageTitle) ?></h1>
  <?php display_flash_messages(); ?>

  <div class="row mb-4">
      <div class="col-12">
          <?php if ($updateDone): ?>
              <div class="card bg-light-success border-success shadow-sm">
                  <div class="card-body d-flex align-items-center flex-wrap gap-3">
                      <i class="bi bi-check-circle-fill text-success fs-2"></i>
                      <div>
                          <h5 class="card-title text-success mb-1">Mise à Jour Mensuelle Effectuée</h5>
                          <p class="card-text mb-0">
                              L'ajout des 2.5 jours de congé pour <?= strftime('%B %Y') ?> a été complété.
                              <br><small class="text-muted"><?= htmlspecialchars($updateDone['notes']) ?></small>
                          </p>
                      </div>
                      <a href="<?= APP_LINK ?>/admin/index.php?route=leave_accrual_log_details" class="btn btn-outline-success ms-auto">
                          <i class="bi bi-clock-history me-2"></i>Voir l'Historique
                      </a>
                  </div>
              </div>
          <?php else: ?>
              <div class="card bg-light-warning border-warning shadow-sm">
                  <div class="card-body d-flex align-items-center flex-wrap gap-3">
                      <i class="bi bi-exclamation-triangle-fill text-warning fs-2"></i>
                      <div>
                          <h5 class="card-title text-warning mb-1">Action Requise : Mise à Jour des Congés</h5>
                          <p class="card-text mb-0">La mise à jour mensuelle des soldes de congé pour <strong><?= strftime('%B %Y') ?></strong> n'a pas encore été effectuée.</p>
                      </div>
                      <a href="<?= APP_LINK ?>/admin/index.php?route=update_system" class="btn btn-warning btn-lg ms-auto">
                          <i class="bi bi-play-circle-fill me-2"></i>Lancer la Mise à Jour
                      </a>
                  </div>
              </div>
          <?php endif; ?>
      </div>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ([
      ['bg'=>'primary','icon'=>'people-fill','count'=>$stats['total_employees'],'label'=>'Employés Actifs','route'=>'employees_list'],
      ['bg'=>'info','icon'=>'person-walking','count'=>$stats['on_leave_today'],'label'=>"En Congé (Aujourd'hui)",'route'=>'leave_emp_on_leave'],
      ['bg'=>'warning','icon'=>'hourglass-split','count'=>$stats['pending_leave_requests'],'label'=>'Demandes Congé en Attente','route'=>'leave_requests&status_filter=pending'],
      ['bg'=>'success','icon'=>'journal-arrow-up','count'=>$stats['pending_mission_orders'],'label'=>'Ordres de Mission en Attente','route'=>'missions_list_missions&status_filter=pending'],
    ] as $card): ?>
      <div class="col-6 col-md-3">
        <div class="card text-white bg-<?= $card['bg'] ?> h-100">
          <div class="card-body d-flex align-items-center">
            <i class="bi bi-<?= $card['icon'] ?> fs-1 me-3"></i>
            <div>
              <h2><?= htmlspecialchars($card['count']) ?></h2>
              <p class="mb-0"><?= htmlspecialchars($card['label']) ?></p>
            </div>
          </div>
          <a href="<?= APP_LINK ?>/admin/index.php?route=<?= $card['route'] ?>"
             class="card-footer text-white text-decoration-none d-flex justify-content-between align-items-center">
            Détails <i class="bi bi-arrow-right-circle-fill"></i>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<div class="row g-3 mb-4">
  <?php foreach ([
    ['title'=>'Contrats Expirant dans(30j)','icon'=>'calendar-x-fill','items'=>$lists['contracts'],'route'=>'employees_list&filter=expiring_contracts','empty'=>'Aucun contrat n\'expire dans les 30 prochains jours.'],
    ['title'=>'Périodes d\'Essai à Terminer','icon'=>'hourglass-bottom','items'=>$lists['on_trial'],'route'=>'employees_list&filter=on_trial','empty'=>'Aucune période d\'essai dans les 20 prochains jours.','badge_prefix'=>'J-'],
    ['title'=>'Solde des  Récupération','icon'=>'arrow-counterclockwise','items'=>$lists['with_recup'],'route'=>'employees_list&filter=recuperation','empty'=>'Aucun solde de récupération déja attribué .','badge_text'=>fn($i)=>$i.' jrs'],
    ['title'=>'Employés avec Reliquat','icon'=>'calendar-plus-fill','items'=>$lists['with_reliquat'],'route'=>'leave_emp_reliquat','empty'=>'Aucun reliquat existant pour les congé des employees.','badge_text'=>fn($i)=>$i.' jrs'],
    ['title'=>'Les Arrêt Maladie','icon'=>'thermometer-sun','items'=>$lists['on_sick_leave'],'route'=>'leave_List_sick_leave','empty'=>'Aucun arrêt maladie.','badge_prefix'=>'Fin: '],
    ['title'=>'Congé Maternité','icon'=>'person-hearts','items'=>$lists['on_maternity'],'route'=>'leave_requests&leave_type_filter=maternite&status_filter=approved','empty'=>'Aucune maternité.','badge_prefix'=>'Fin: '],
    ['title'=>'Absences Répétées','icon'=>'exclamation-triangle-fill','items'=>$lists['frequent_absences'],'route'=>'employees_list&filter=frequent_absences','empty'=>"Aucune absence > {$stats['absence_threshold']} ce mois-ci.",'badge_text'=>fn($i)=>$i.' absences'],
  ] as $list): ?>
  <div class="col-6 col-md-3">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0 flex-grow-1 d-flex align-items-center">
                <i class="bi bi-<?= $list['icon'] ?> text-info me-2"></i>
                <?= htmlspecialchars($list['title']) ?>
            </h3>
            <a href="<?= APP_LINK ?>/admin/index.php?route=<?= $list['route'] ?>" class="btn btn-sm btn-outline-primary ms-auto">
                Voir tout
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($list['items'])): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($list['items'] as $item): 
                        $name = htmlspecialchars("{$item['first_name']} {$item['last_name']}");
                        $nin = htmlspecialchars($item['nin']);
                        $badge = '';
                        // Note: This complex badge logic from your original file is preserved.
                        if (isset($list['badge_text']) && is_callable($list['badge_text'])) {
                            $data = $item['end_date'] ?? $item['days_left'] ?? $item['remaining_leave_balance'] ?? $item['absence_count'] ?? $item['recup_balance'] ?? '';
                            $badge = htmlspecialchars((isset($list['badge_prefix']) ? $list['badge_prefix'] : '') . $list['badge_text']($data));
                        } elseif (isset($list['badge_prefix'])) {
                            $data = $item['end_date'] ?? $item['days_left'] ?? $item['remaining_leave_balance'] ?? $item['absence_count'] ?? '';
                            $badge = htmlspecialchars($list['badge_prefix'] . $data);
                        }
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?= APP_LINK ?>/admin/index.php?route=employees_view&nin=<?= $nin ?>"><?= $name ?></a>
                        <?php if ($badge): ?>
                        <span class="badge bg-secondary rounded-pill"><?= $badge ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
            <p class="text-muted p-3"><?= htmlspecialchars($list['empty']) ?></p>
            <?php endif; ?>
        </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

  <div class="card">
    <div class="card-header"><i class="bi bi-lightning-charge-fill me-2"></i>Accès Rapides</div>
    <div class="card-body">
      <div class="row row-cols-2 row-cols-md-3 g-3">
        <?php foreach ([
          ['route'=>'employees_add','icon'=>'person-plus-fill','label'=>'Ajouter un Employé'],
          ['route'=>'attendance_manage','icon'=>'calendar-check','label'=>'Gérer les Pointages'],
          ['route'=>'leave_add','icon'=>'calendar-plus','label'=>'Enregistrer un Congé'],
          ['route'=>'missions_add_mission','icon'=>'briefcase-fill','label'=>'Créer Ordre'],
          ['route'=>'reports_certificates','icon'=>'file-earmark-text','label'=>'Générer Certificat'],
          ['route'=>'settings_company','icon'=>'gear-fill','label'=>'Paramètres'],
        ] as $link): ?>
          <div class="col">
            <a href="<?= APP_LINK ?>/admin/index.php?route=<?= $link['route'] ?>"
               class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3">
              <i class="bi bi-<?= $link['icon'] ?> fs-3 mb-1"></i>
              <small><?= htmlspecialchars($link['label']) ?></small>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__.'../../../includes/footer.php'; ?>