<?php
if (!defined('APP_SECURE_INCLUDE')) { http_response_code(403); die('Direct access not allowed.'); }
redirectIfNotAdminOrHR();

$formation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$formation_id) { header('Location: index.php?route=formations_list'); exit; }

// Handle POST requests (add participants OR mark as completed)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token(); // Central CSRF check

    // Handle adding new participants
    if (isset($_POST['add_participants'])) {
        $employees_to_add = $_POST['employee_nins'] ?? [];
        if (!empty($employees_to_add)) {
            $stmt = $db->prepare("INSERT IGNORE INTO formation_participants (formation_id, employee_nin) VALUES (?, ?)");
            foreach ($employees_to_add as $nin) {
                $stmt->execute([$formation_id, $nin]);
            }
            flash('success', 'Les participants ont été ajoutés.');
        }
    }

    // Handle marking the formation as completed
    if (isset($_POST['mark_completed'])) {
        $stmt = $db->prepare("UPDATE formations SET status = 'Terminée' WHERE id = ?");
        $stmt->execute([$formation_id]);
        flash('success', 'La formation a été marquée comme terminée.');
    }

    header('Location: index.php?route=formations_view&id=' . $formation_id);
    exit;
}

// Fetch formation details
$stmt = $db->prepare("SELECT * FROM formations WHERE id = ?");
$stmt->execute([$formation_id]);
$formation = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$formation) { header('Location: index.php?route=formations_list'); exit; }

// Fetch current participants with their department and position
$p_stmt = $db->prepare("
    SELECT e.nin, e.first_name, e.last_name, e.department, e.position 
    FROM formation_participants fp 
    JOIN employees e ON fp.employee_nin = e.nin 
    WHERE fp.formation_id = ? 
    ORDER BY e.last_name
");
$p_stmt->execute([$formation_id]);
$participants = $p_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch employees available to be added (only if formation is not completed)
$available_employees = [];
if ($formation['status'] === 'Planifiée') {
    $a_stmt = $db->prepare("
        SELECT nin, first_name, last_name FROM employees 
        WHERE status = 'active' AND nin NOT IN (
            SELECT employee_nin FROM formation_participants WHERE formation_id = ?
        )
        ORDER BY last_name
    ");
    $a_stmt->execute([$formation_id]);
    $available_employees = $a_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "Détails de la Formation";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div>
            <span class="badge bg-dark fs-6 mb-1"><?= htmlspecialchars($formation['reference_number']) ?></span>
            <h1 class="mb-0"><?= htmlspecialchars($formation['title']) ?></h1>
            <p class="lead text-muted mb-0">Par <?= htmlspecialchars($formation['trainer_name']) ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($formation['status'] === 'Terminée'): ?>
                <a href="index.php?route=formations_print_catalog&id=<?= $formation['id'] ?>" target="_blank" class="btn btn-secondary">
                    <i class="bi bi-printer-fill me-2"></i>Imprimer le Catalogue
                </a>
                <div class="alert alert-success d-flex align-items-center mb-0 p-2">
                    <i class="bi bi-check-circle-fill me-2"></i> <strong>Terminée</strong>
                </div>
            <?php else: ?>
                <form method="POST" onsubmit="return confirm('Voulez-vous vraiment marquer cette formation comme terminée ? Cette action est irréversible.');">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <button type="submit" name="mark_completed" class="btn btn-success">
                        <i class="bi bi-check-circle-fill me-2"></i>Marquer comme Terminée
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php display_flash_messages(); ?>

    <div class="card my-4">
        <div class="card-header">Sujet de la Formation</div>
        <div class="card-body">
            <p style="white-space: pre-wrap;"><?= htmlspecialchars($formation['subject']) ?></p>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Participants Inscrits (<?= count($participants) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($participants)): ?>
                        <p class="text-muted p-3">Aucun participant n'est encore inscrit à cette formation.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Nom Complet</th>
                                        <th>Département</th>
                                        <th>Poste</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td>
                                            <a href="index.php?route=employees_view&nin=<?= $participant['nin'] ?>">
                                                <?= htmlspecialchars($participant['first_name'] . ' ' . $participant['last_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($participant['department']) ?></td>
                                        <td><?= htmlspecialchars($participant['position']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($formation['status'] === 'Planifiée'): ?>
            <div class="col-lg-5 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Ajouter des Participants</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                            <div class="mb-3">
                                <label for="employee_nins" class="form-label">Sélectionner des employés :</label>
                                <?php if (empty($available_employees)): ?>
                                    <p class="text-muted">Tous les employés actifs sont déjà inscrits.</p>
                                <?php else: ?>
                                    <select name="employee_nins[]" id="employee_nins" class="form-select" multiple size="8">
                                        <?php foreach ($available_employees as $employee): ?>
                                            <option value="<?= htmlspecialchars($employee['nin']) ?>">
                                                <?= htmlspecialchars($employee['last_name'] . ' ' . $employee['first_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Maintenez Ctrl (ou Cmd) pour en sélectionner plusieurs.</small>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($available_employees)): ?>
                                <button type="submit" name="add_participants" class="btn btn-primary">Ajouter les Employés</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <a href="index.php?route=formations_list" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Retour à la liste</a>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>