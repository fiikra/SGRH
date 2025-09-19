<?php

// --- Helper Functions ---
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() { return isset($_SESSION['user_id']); }
}

if (!function_exists('isAdmin')) {
    function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
}

if (!function_exists('isHR')) {
    function isHR() { return isset($_SESSION['role']) && $_SESSION['role'] === 'hr'; }
}

function isActiveRoute(string $route): string {
    return (isset($_GET['route']) && $_GET['route'] === $route) ? 'active' : '';
}

function isRouteGroupActive(array $routes): string {
    return (isset($_GET['route']) && in_array($_GET['route'], $routes)) ? 'active' : '';
}

$logo = APP_LINK . '/assets/img/brand.png';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="<?= (isAdmin() || isHR()) ? route('dashboard') : APP_LINK ?>">
      <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" height="35" class="me-2">
      <span>SGRH</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (isLoggedIn()): ?>
          <?php if (isAdmin() || isHR()): ?>

            <!-- Dashboard -->
            <li class="nav-item">
              <a class="nav-link <?= isActiveRoute('dashboard') ?>" href="<?= route('dashboard') ?>">
                <i class="bi bi-speedometer2"></i>
              </a>
            </li>

            <!-- Employés Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['employees_add','employees_list','attendance_manage']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-people-fill me-2"></i>Employés
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('employees_add') ?>" href="<?= route('employees_add') ?>"><i class="bi bi-person-plus-fill me-2"></i>Ajouter Employé</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('employees_list') ?>" href="<?= route('employees_list') ?>"><i class="bi bi-list-ul me-2"></i>Liste des Employés</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('attendance_manage') ?>" href="<?= route('attendance_manage') ?>"><i class="bi bi-calendar-check me-2"></i>Pointage</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= isActiveRoute('employees_view') ?>" href="<?= route('formations_list') ?>"><i class="bi bi-mortarboard-fill me-2"></i>Formations</a></li>
              </ul>
            </li>

            <!-- Missions Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['missions_add_mission','missions_list_missions']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-briefcase-fill"></i>Missions
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('missions_add_mission') ?>" href="<?= route('missions_add_mission') ?>"><i class="bi bi-plus-circle me-2"></i>Nouvel Ordre</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('missions_list_missions') ?>" href="<?= route('missions_list_missions') ?>"><i class="bi bi-list-stars me-2"></i>Liste des Ordres</a></li>
              </ul>
            </li>

            <!-- Contrats Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['contracts_add','contracts_index']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-file-earmark-text"></i>Contrats
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('contracts_add') ?>" href="<?= route('contracts_add') ?>"><i class="bi bi-plus-circle me-2"></i>Nouveau Contrat</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('contracts_index') ?>" href="<?= route('contracts_index') ?>"><i class="bi bi-list-ul me-2"></i>Liste des Contrats</a></li>
              </ul>
            </li>

            <!-- Congés Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['leave_add','maternity_leave','leave_add_maternity_leave','leave_requests','leave_emp_on_leave','leave_emp_reliquat','leave_leave_historique']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-calendar-event"></i>Congés
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('leave_add') ?>" href="<?= route('leave_add') ?>"><i class="bi bi-calendar-plus text-success"></i>Générer Congé</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('maternity_leave') ?>" href="<?= route('maternity_leave') ?>"><i class="bi bi-gender-female text-info"></i>Congé Maternité</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_add_maternity_leave') ?>" href="<?= route('leave_add_maternity_leave') ?>"><i class="bi bi-gender-female text-info"></i>Ajouter Maternité</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_requests') ?>" href="<?= route('leave_requests') ?>"><i class="bi bi-card-list text-primary"></i>Toutes Demandes</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_emp_on_leave') ?>" href="<?= route('leave_emp_on_leave') ?>"><i class="bi bi-person-walking text-info"></i>Employés en Congé</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_emp_reliquat') ?>" href="<?= route('leave_emp_reliquat') ?>"><i class="bi bi-hourglass-split text-warning"></i>Reliquat</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_leave_historique') ?>" href="<?= route('leave_leave_historique') ?>"><i class="bi bi-clock-history text-secondary"></i>Historique</a></li>
              </ul>
            </li>

            <!-- Maladies Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['leave_add_sick_leave','leave_List_sick_leave','leave_seek_leave_analytics']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-thermometer-half"></i>Maladies
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('leave_add_sick_leave') ?>" href="<?= route('leave_add_sick_leave') ?>"><i class="bi bi-journal-plus text-danger"></i>Ajouter Maladie</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_List_sick_leave') ?>" href="<?= route('leave_List_sick_leave') ?>"><i class="bi bi-list-task text-danger"></i>Liste Maladies</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_seek_leave_analytics') ?>" href="<?= route('leave_seek_leave_analytics') ?>"><i class="bi bi-clipboard-data text-danger"></i>Rapports Maladie</a></li>
              </ul>
            </li>

            <!-- Rapports Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['reports_certificates','trial_notifications_index','reports_decesion','promotions_index','settings_leave_history']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-file-earmark-bar-graph"></i>Rapports
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('reports_certificates') ?>" href="<?= route('reports_certificates') ?>"><i class="bi bi-file-earmark-pdf"></i>Certificats</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= isActiveRoute('trial_notifications_index') ?>" href="<?= route('trial_notifications_index') ?>"><i class="bi bi-collection"></i>Notifications</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= isActiveRoute('reports_decesion') ?>" href="<?= route('reports_decesion') ?>"><i class="bi bi-collection"></i>Décisions</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= isActiveRoute('questionnaires_index') ?>" href="<?= route('questionnaires_index') ?>"><i class="bi bi-archive"></i>Questionaires</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= isActiveRoute('sanctions_index') ?>" href="<?= route('sanctions_index') ?>"><i class="bi bi-archive"></i>Sanctions</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item <?= isActiveRoute('leave_report') ?>" href="<?= route('leave_report') ?>"><i class="bi bi-tools text-danger"></i>Rapport Congés</a></li>
              </ul>
            </li>

            <!-- Paramètres Dropdown -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= isRouteGroupActive(['settings_company','settings_personnel_settings','settings_personnel_organisation','settings_settings_smtp','users_register','users_users','update_system']) ?>"
                 href="#" data-bs-toggle="dropdown">
                <i class="bi bi-gear"></i>Paramètres
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= isActiveRoute('settings_company') ?>" href="<?= route('settings_company') ?>"><i class="bi bi-building"></i>Société</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('settings_personnel_settings') ?>" href="<?= route('settings_personnel_settings') ?>"><i class="bi bi-person-lines-fill"></i>Personnel & Paie</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('settings_personnel_organisation') ?>" href="<?= route('settings_personnel_organisation') ?>"><i class="bi bi-diagram-3"></i>Organisation</a></li>
                <li><a class="dropdown-item <?= isActiveRoute('settings_settings_smtp') ?>" href="<?= route('settings_settings_smtp') ?>"><i class="bi bi-envelope"></i>Email SMTP</a></li>
                <?php if (isAdmin()): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item <?= isActiveRoute('users_register') ?>" href="<?= route('users_register') ?>"><i class="bi bi-person-plus-fill me-2"></i>Ajouter Utilisateur</a></li>
                  <li><a class="dropdown-item <?= isActiveRoute('users_users') ?>" href="<?= route('users_users') ?>"><i class="bi bi-list-ul me-2"></i>Liste Utilisateurs</a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item <?= isActiveRoute('update_system') ?>" href="<?= route('update_system') ?>"><i class="bi bi-cloud-arrow-up"></i>Update Système</a></li>
                <?php endif;?>
              </ul>
            </li>

          <?php else: // Employee view ?>
            <li class="nav-item"><a class="nav-link <?= (isset($_GET['page']) && $_GET['page']==='dashboard')?'active':'' ?>" href="<?= APP_LINK ?>/employee/index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Tableau de bord</a></li>
            <li class="nav-item"><a class="nav-link <?= (isset($_GET['page']) && $_GET['page']==='leave')?'active':'' ?>" href="<?= APP_LINK ?>/employee/index.php?page=leave"><i class="bi bi-person-walking"></i> Mes Congés</a></li>
            <li class="nav-item"><a class="nav-link <?= (isset($_GET['page']) && $_GET['page']==='documents')?'active':'' ?>" href="<?= APP_LINK ?>/employee/index.php?page=documents"><i class="bi bi-file-earmark-person"></i> Mes Documents</a></li>
            <li class="nav-item"><a class="nav-link <?= (isset($_GET['page']) && $_GET['page']==='profile')?'active':'' ?>" href="<?= APP_LINK ?>/employee/index.php?page=profile"><i class="bi bi-person"></i> Mon Profil</a></li>
          <?php endif; ?>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav">
        <?php if (isLoggedIn()): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Utilisateur') ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= Proute('profile',['id'=>$_SESSION['user_id']]) ?>"><i class="bi bi-person"></i> Profil</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= Proute('logout') ?>"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= Proute('login') ?>"><i class="bi bi-box-arrow-in-right"></i> Connexion</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.innerWidth > 992) {
    document.querySelectorAll('.navbar .dropdown').forEach(el => {
      el.addEventListener('mouseenter', () => {
        let link = el.querySelector('[data-bs-toggle="dropdown"]');
        link && bootstrap.Dropdown.getOrCreateInstance(link).show();
      });
      el.addEventListener('mouseleave', () => {
        let link = el.querySelector('[data-bs-toggle="dropdown"]');
        link && bootstrap.Dropdown.getInstance(link)?.hide();
      });
    });
  }
});
</script>
