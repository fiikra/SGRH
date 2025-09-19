<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

$pageTitle = "Modifier l'Ordre de Mission";

// --- Fetching Data ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID d'ordre de mission invalide.";
    header("Location: " . route('missions_list_missions')); // MODIFIED
    exit();
}
$mission_id = (int)$_GET['id'];

// Fetch existing mission order data
$stmt_mission = $db->prepare("SELECT * FROM mission_orders WHERE id = ?");
$stmt_mission->execute([$mission_id]);
$mission = $stmt_mission->fetch(PDO::FETCH_ASSOC);

if (!$mission) {
    $_SESSION['error'] = "Ordre de mission non trouvé.";
    header("Location: " . route('missions_list_missions')); // MODIFIED
    exit();
}

// Fetch active employees for the dropdown
$employees_stmt = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status = 'active' ORDER BY last_name, first_name");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $employee_nin = sanitize($_POST['employee_nin'] ?? '');
    $destination = sanitize($_POST['destination'] ?? '');
    $departure_date_str = sanitize($_POST['departure_date'] ?? '');
    $return_date_str = sanitize($_POST['return_date'] ?? '');
    $objective = sanitize($_POST['objective'] ?? '');
    $vehicle_registration = sanitize($_POST['vehicle_registration'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $status = sanitize($_POST['status'] ?? '');

    // Validation logic...
    if (empty($employee_nin)) $errors[] = "Veuillez sélectionner un employé.";
    // ... other validation rules ...

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            $sql = "UPDATE mission_orders SET 
                        employee_nin = :employee_nin, destination = :destination, departure_date = :departure_date, 
                        return_date = :return_date, objective = :objective, vehicle_registration = :vehicle_registration, 
                        notes = :notes, status = :status, approved_by_user_id = :approved_by, approval_date = :approval_date, 
                        updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);

            $approved_by = $mission['approved_by_user_id'];
            $approval_date = $mission['approval_date'];

            // Set approval info only if status is changing to 'approved'
            if ($status === 'approved' && $mission['status'] !== 'approved') {
                $approved_by = $_SESSION['user_id'];
                $approval_date = date('Y-m-d H:i:s');
            } elseif ($status !== 'approved') {
                $approved_by = null;
                $approval_date = null;
            }
            
            $params = [
                ':employee_nin' => $employee_nin,
                ':destination' => $destination,
                ':departure_date' => (new DateTime($departure_date_str))->format('Y-m-d H:i:s'),
                ':return_date' => (new DateTime($return_date_str))->format('Y-m-d H:i:s'),
                ':objective' => $objective,
                ':vehicle_registration' => !empty($vehicle_registration) ? $vehicle_registration : null,
                ':notes' => $notes,
                ':status' => $status,
                ':approved_by' => $approved_by,
                ':approval_date' => $approval_date,
                ':id' => $mission_id
            ];
            
            $stmt->execute($params);
            $db->commit();
            
            $_SESSION['success'] = "Ordre de mission N°" . htmlspecialchars($mission['reference_number']) . " mis à jour.";
            header("Location: " . route('missions_list_missions')); // MODIFIED
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = "Erreur lors de la mise à jour.";
            error_log("Mission Update Error: " . $e->getMessage());
        }
    }
    
    // If errors, repopulate form with submitted values for correction
    $mission['employee_nin'] = $employee_nin;
    // ... repopulate other fields ...
}

// This now includes the sidebar and main layout
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-pencil-square me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
        <div>
            <a href="<?= route('missions_list_missions') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i> Liste des Ordres</a>
            <a href="<?= route('missions_generate_mission_order_pdf', ['id' => $mission_id]) ?>" class="btn btn-sm btn-outline-danger ms-2" target="_blank">
                <i class="bi bi-file-earmark-pdf me-1"></i> Visualiser PDF
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header">
            Détails de l'Ordre de Mission N°: <strong><?= htmlspecialchars($mission['reference_number']) ?></strong>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= route('missions_edit_mission', ['id' => $mission_id]) ?>">
                <?php csrf_input(); ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="employee_nin" class="form-label">Employé(e)*</label>
                        <select class="form-select" id="employee_nin" name="employee_nin" required>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= ($mission['employee_nin'] === $emp['nin']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="destination" class="form-label">Destination(s)*</label>
                        <input type="text" class="form-control" id="destination" name="destination" value="<?= htmlspecialchars($mission['destination']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="departure_date" class="form-label">Date et Heure de Départ*</label>
                        <input type="datetime-local" class="form-control" id="departure_date" name="departure_date" 
                               value="<?= htmlspecialchars((new DateTime($mission['departure_date']))->format('Y-m-d\TH:i')) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="return_date" class="form-label">Date et Heure de Retour*</label>
                        <input type="datetime-local" class="form-control" id="return_date" name="return_date" 
                               value="<?= htmlspecialchars((new DateTime($mission['return_date']))->format('Y-m-d\TH:i')) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="objective" class="form-label">Objectif de la Mission*</label>
                    <textarea class="form-control" id="objective" name="objective" rows="3" required><?= htmlspecialchars($mission['objective']) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vehicle_registration" class="form-label">Immatriculation Véhicule</label>
                        <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" value="<?= htmlspecialchars($mission['vehicle_registration'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="status" class="form-label">Statut*</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending" <?= ($mission['status'] === 'pending') ? 'selected' : '' ?>>En attente</option>
                            <option value="approved" <?= ($mission['status'] === 'approved') ? 'selected' : '' ?>>Approuvé</option>
                            <option value="rejected" <?= ($mission['status'] === 'rejected') ? 'selected' : '' ?>>Rejeté</option>
                            <option value="completed" <?= ($mission['status'] === 'completed') ? 'selected' : '' ?>>Terminé</option>
                            <option value="cancelled" <?= ($mission['status'] === 'cancelled') ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes (Internes)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($mission['notes'] ?? '') ?></textarea>
                </div>

                <hr>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-circle-fill me-2"></i>Mettre à Jour</button>
                <a href="<?= route('missions_list_missions') ?>" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>