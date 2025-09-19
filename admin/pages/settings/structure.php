<?php
/**
 * Page: Company Settings
 *
 * Manages all global settings for the company, including legal information,
 * HR policies, SMTP, and organizational structure.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdmin();

// Récupérer les données pour les formulaires et listes
$departments = $db->query("SELECT d.*, CONCAT(e.first_name, ' ', e.last_name) as manager_name FROM departments d LEFT JOIN employees e ON d.manager_nin = e.nin ORDER BY d.name ASC")->fetchAll(PDO::FETCH_ASSOC);
$positions = $db->query("SELECT p.*, d.name as department_name FROM positions p LEFT JOIN departments d ON p.department_id = d.id ORDER BY p.title ASC")->fetchAll(PDO::FETCH_ASSOC);
$employees = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status = 'active' ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Gestion de l'Organisation";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4"><?= $pageTitle ?></h1>
    <p class="text-muted">Gérez ici les départements et les postes de votre entreprise, y compris les fiches de poste et les salaires de base.</p>

    <div class="row">
        <!-- Section Départements -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-diagram-3-fill me-2"></i> Départements</h5>
                    <button class="btn btn-primary btn-sm" onclick="openDepartmentModal('add')"><i class="bi bi-plus-circle"></i> Ajouter</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Nom</th><th>Responsable</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach($departments as $dept): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($dept['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($dept['manager_name'] ?? 'N/A') ?></td>
                                        <td><button class="btn btn-sm btn-outline-primary" onclick='openDepartmentModal("edit", <?= json_encode($dept, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Postes -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-briefcase-fill me-2"></i> Postes</h5>
                    <button class="btn btn-primary btn-sm" onclick="openPositionModal('add')"><i class="bi bi-plus-circle"></i> Ajouter</button>
                </div>
                <div class="card-body">
                     <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Titre</th><th>Département</th><th>Salaire de Base</th><th>Actions</th></tr></thead>
                            <tbody>
                                 <?php foreach($positions as $pos): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($pos['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($pos['department_name'] ?? 'N/A') ?></td>
                                        <td><?= $pos['base_salary'] ? number_format($pos['base_salary'], 2, ',', ' ') . ' DZD' : 'N/A' ?></td>
                                        <td><button class="btn btn-sm btn-outline-primary" onclick='openPositionModal("edit", <?= json_encode($pos, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Départements -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="handle_structure.php" method="POST">
           <?php csrf_input(); // ✅ Correct: Just call the function here ?>
        <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="departmentModalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="department_action">
                    <input type="hidden" name="department_id" id="department_id">
                    <div class="mb-3"><label class="form-label">Nom du département</label><input type="text" name="name" id="department_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="department_description" class="form-control"></textarea></div>
                    <div class="mb-3"><label class="form-label">Responsable</label><select name="manager_nin" id="department_manager" class="form-select"><option value="">Aucun</option><?php foreach($employees as $emp) echo "<option value='{$emp['nin']}'>{$emp['first_name']} {$emp['last_name']}</option>"; ?></select></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Postes -->
<div class="modal fade" id="positionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
         <form action="handle_structure.php" method="POST">
           <?php csrf_input(); // ✅ Correct: Just call the function here ?>
         <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="positionModalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="position_action">
                    <input type="hidden" name="position_id" id="position_id">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>Titre du poste</label><input type="text" name="title" id="position_title" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label>Département</label><select name="department_id" id="position_department" class="form-select"><option value="">Aucun</option><?php foreach($departments as $dept) echo "<option value='{$dept['id']}'>".htmlspecialchars($dept['name'])."</option>"; ?></select></div>
                    </div>
                    <div class="mb-3"><label>Salaire de base (Optionnel)</label><input type="number" step="0.01" name="base_salary" id="position_salary" class="form-control"></div>
                    <div class="mb-3"><label>Fiche de poste / Description des tâches</label><textarea name="job_description" id="position_description" class="form-control" rows="5"></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            </div>
        </form>
    </div>
</div>

<script>
const departmentModal = new bootstrap.Modal(document.getElementById('departmentModal'));
function openDepartmentModal(action, data = {}) {
    const form = document.querySelector('#departmentModal form');
    form.reset();
    document.getElementById('department_action').value = action === 'add' ? 'add_department' : 'edit_department';
    document.getElementById('departmentModalTitle').innerText = action === 'add' ? 'Ajouter un Département' : 'Modifier le Département';
    if (action === 'edit') {
        document.getElementById('department_id').value = data.id;
        document.getElementById('department_name').value = data.name;
        document.getElementById('department_description').value = data.description;
        document.getElementById('department_manager').value = data.manager_nin || '';
    }
    departmentModal.show();
}

const positionModal = new bootstrap.Modal(document.getElementById('positionModal'));
function openPositionModal(action, data = {}) {
    const form = document.querySelector('#positionModal form');
    form.reset();
    document.getElementById('position_action').value = action === 'add' ? 'add_position' : 'edit_position';
    document.getElementById('positionModalTitle').innerText = action === 'add' ? 'Ajouter un Poste' : 'Modifier le Poste';
    if (action === 'edit') {
        document.getElementById('position_id').value = data.id;
        document.getElementById('position_title').value = data.title;
        document.getElementById('position_department').value = data.department_id || '';
        document.getElementById('position_salary').value = data.base_salary;
        document.getElementById('position_description').value = data.job_description;
    }
    positionModal.show();
}
</script>

<?php include '../../includes/footer.php'; ?>
