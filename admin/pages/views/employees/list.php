<?php 
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

include __DIR__ . '../../../../../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-people-fill me-2"></i>Liste des Employés</h4>
            <div class="d-flex gap-2">
                <a href="<?= route('employees_add') ?>" class="btn btn-success">
                    <i class="bi bi-person-plus-fill"></i> Ajouter un Employé
                </a>
                <a href="<?= route('employees_list', array_merge($filters, ['export' => 'csv'])) ?>" class="btn btn-secondary">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Exporter CSV
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="accordion" id="filterAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                            <i class="bi bi-funnel-fill me-2"></i> Options de Filtrage
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#filterAccordion">
                        <div class="accordion-body bg-light">
                            <form method="get" action="<?= route('employees_list') ?>">
                                <input type="hidden" name="route" value="employees_list">
                                <div class="row g-3">
                                    <div class="col-md-6 col-lg-4">
                                        <input type="text" name="search" class="form-control" placeholder="Rechercher par Nom, Prénom, NIN..." value="<?= htmlspecialchars($filters['search']) ?>">
                                    </div>
                                    <div class="col-md-6 col-lg-2">
                                        <select name="status" class="form-select">
                                            <option value="">Tous les statuts</option>
                                            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                                            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                                            <option value="suspended" <?= $filters['status'] === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <select name="department" class="form-select">
                                            <option value="">Tous les départements</option>
                                            <?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept) ?>" <?= $filters['department'] === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 col-lg-3">
                                        <select name="position" class="form-select">
                                            <option value="">Tous les postes</option>
                                            <?php foreach ($positions as $pos): ?><option value="<?= htmlspecialchars($pos) ?>" <?= $filters['position'] === $pos ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrer</button>
                                        <a href="<?= route('employees_list') ?>" class="btn btn-outline-secondary">Réinitialiser</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-hover table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom Complet</th>
                            <th>Poste</th>
                            <th>Département</th>
                            <th>Date Embauche</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($employee['photo_path'])): ?>
                                                <img src="/<?= htmlspecialchars($employee['photo_path']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Photo" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle me-2 d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></div>
                                                <div class="text-muted small">NIN: <?= htmlspecialchars($employee['nin']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($employee['position']) ?></td>
                                    <td><?= htmlspecialchars($employee['department']) ?></td>
                                    <td><?= formatDate($employee['hire_date']) ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-<?= $employee['status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($employee['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= route('employees_view', ['nin' => $employee['nin']]) ?>" class="btn btn-sm btn-outline-info" title="Voir le Profil"><i class="bi bi-eye-fill"></i></a>
                                        <a href="<?= route('employees_edit', ['nin' => $employee['nin']]) ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil-fill"></i></a>
                                        <a href="<?= route('employees_documents', ['nin' => $employee['nin']]) ?>" class="btn btn-sm btn-outline-secondary" title="Documents"><i class="bi bi-folder-fill"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center p-4">
                                    <p class="mb-0">Aucun employé trouvé correspondant à vos critères.</p>
                                    <a href="<?= route('employees_list') ?>" class="btn btn-link">Réinitialiser les filtres</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= route('employees_list', array_merge($filters, ['page' => $page - 1])) ?>">Précédent</a></li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('employees_list', array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= route('employees_list', array_merge($filters, ['page' => $page + 1])) ?>">Suivant</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>