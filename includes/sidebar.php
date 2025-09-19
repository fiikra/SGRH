<aside class="sidebar">
    <div class="sidebar-header">
        <a href="#" class="sidebar-logo">
            <i class="bi bi-buildings-fill"></i>
            <span class="sidebar-logo-text">SGRH</span>
        </a>
        <button class="sidebar-toggle" id="sidebar-toggle">
            <i class="bi bi-list"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= APP_LINK ?>/admin/index.php?route=dashboard" class="sidebar-link <?= ($pageTitle === 'Tableau de Bord') ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> <span class="sidebar-link-text">Dashboard</span></a>
        <a href="<?= APP_LINK ?>/admin/index.php?route=employees_list" class="sidebar-link <?= ($pageTitle === 'Liste des Employés') ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> <span class="sidebar-link-text">Employees</span></a>
        <a href="<?= APP_LINK ?>/admin/index.php?route=leave_requests" class="sidebar-link <?= ($pageTitle === 'Demandes de Congé') ? 'active' : '' ?>"><i class="bi bi-calendar-check-fill"></i> <span class="sidebar-link-text">Demandes Conges</span></a>
        <a href="<?= APP_LINK ?>/admin/index.php?route=missions_list_missions" class="sidebar-link <?= ($pageTitle === 'Ordres de Mission') ? 'active' : '' ?>"><i class="bi bi-briefcase-fill"></i> <span class="sidebar-link-text">Ordres de missions</span></a>
        <a href="<?= APP_LINK ?>/admin/index.php?route=reports_certificates" class="sidebar-link <?= ($pageTitle === 'Rapports') ? 'active' : '' ?>"><i class="bi bi-file-earmark-text-fill"></i> <span class="sidebar-link-text">Rapports</span></a>
        <a href="<?= APP_LINK ?>/admin/index.php?route=settings_company" class="sidebar-link <?= ($pageTitle === 'Paramètres') ? 'active' : '' ?>"><i class="bi bi-gear-fill"></i> <span class="sidebar-link-text">Parametres</span></a>
    </nav>
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-left"></i> <span class="sidebar-link-text">Logout</span></a>
    </div>
</aside>