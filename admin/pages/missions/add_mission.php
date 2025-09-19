<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

$pageTitle = "Nouvel Ordre de Mission";

$employees_stmt = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status = 'active' ORDER BY last_name, first_name");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- POST Data Processing & Validation ---
    $employee_nin = sanitize($_POST['employee_nin'] ?? '');
    $destination = sanitize($_POST['destination'] ?? '');
    $departure_date_str = sanitize($_POST['departure_date'] ?? '');
    $return_date_str = sanitize($_POST['return_date'] ?? '');
    $objective = sanitize($_POST['objective'] ?? '');
    $vehicle_registration = sanitize($_POST['vehicle_registration'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($employee_nin)) $errors[] = "Veuillez sélectionner un employé.";
    if (empty($destination)) $errors[] = "La destination est requise.";
    if (empty($departure_date_str)) $errors[] = "La date de départ est requise.";
    if (empty($return_date_str)) $errors[] = "La date de retour est requise.";
    if (empty($objective)) $errors[] = "L'objectif de la mission est requis.";

    $departure_date = null;
    $return_date = null;

    try {
        if (!empty($departure_date_str)) $departure_date = new DateTime($departure_date_str);
        if (!empty($return_date_str)) $return_date = new DateTime($return_date_str);
    } catch (Exception $e) {
        $errors[] = "Format de date invalide.";
    }

    if ($departure_date && $return_date && $departure_date >= $return_date) {
        $errors[] = "La date de départ doit être antérieure à la date de retour.";
    }
    
    // --- Database Insertion ---
    if (empty($errors)) {
        try {
            // Generate New Reference Number: OM-XXXX-YYYY-RAND
            $current_year_for_ref = date('Y');
            $stmt_max_seq = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(reference_number, '-', 2), '-', -1) AS UNSIGNED)) FROM mission_orders WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(reference_number, '-', 3), '-', -1) = :year");
            $stmt_max_seq->execute([':year' => $current_year_for_ref]);
            $next_seq_num = ((int)$stmt_max_seq->fetchColumn() ?: 0) + 1;
            
            $reference_number = sprintf("OM-%04d-%s-%s", $next_seq_num, $current_year_for_ref, generateRandomAlphanumericString(4));

            $db->beginTransaction();

            $sql = "INSERT INTO mission_orders (employee_nin, reference_number, destination, departure_date, return_date, objective, vehicle_registration, notes, status, created_by_user_id)
                    VALUES (:employee_nin, :reference_number, :destination, :departure_date, :return_date, :objective, :vehicle_registration, :notes, :status, :created_by_user_id)";
            
            $params = [
                ':employee_nin' => $employee_nin,
                ':reference_number' => $reference_number,
                ':destination' => $destination,
                ':departure_date' => $departure_date->format('Y-m-d H:i:s'),
                ':return_date' => $return_date->format('Y-m-d H:i:s'),
                ':objective' => $objective,
                ':vehicle_registration' => !empty($vehicle_registration) ? $vehicle_registration : null,
                ':notes' => $notes,
                ':status' => 'pending', 
                ':created_by_user_id' => $_SESSION['user_id'] ?? null
            ];
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $db->commit();
            
            $_SESSION['success'] = "Ordre de mission N°{$reference_number} créé avec succès.";
            header("Location: " . route('missions_list_missions')); // MODIFIED
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            
            if ($e instanceof PDOException && $e->getCode() == '23000') { 
                $errors[] = "Erreur de référence dupliquée. Veuillez réessayer.";
            } else {
                $errors[] = "Erreur lors de la création de l'ordre de mission.";
            }
            error_log("Mission Order Error: " . $e->getMessage());
        }
    }
}

// This now includes the sidebar and main layout
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-briefcase-fill me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= route('missions_list_missions') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i> Liste des Ordres de Mission</a>
    </div>

    <?php display_flash_messages(); // Use the flash message system for errors on the next page ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p class="mb-0"><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="<?= route('missions_add_mission') ?>">
                <?php csrf_input(); ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="employee_nin" class="form-label">Employé(e)*</label>
                        <select class="form-select" id="employee_nin" name="employee_nin" required>
                            <option value="">Sélectionner un(e) employé(e)</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= (isset($_POST['employee_nin']) && $_POST['employee_nin'] === $emp['nin']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name'] . ' (NIN: ' . $emp['nin'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="destination" class="form-label">Destination(s)*</label>
                        <input type="text" class="form-control" id="destination" name="destination" value="<?= htmlspecialchars($_POST['destination'] ?? '') ?>" placeholder="Ex: Alger - Sétif - Alger" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="departure_date" class="form-label">Date et Heure de Départ*</label>
                        <input type="datetime-local" class="form-control" id="departure_date" name="departure_date" value="<?= htmlspecialchars($_POST['departure_date'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="return_date" class="form-label">Date et Heure de Retour*</label>
                        <input type="datetime-local" class="form-control" id="return_date" name="return_date" value="<?= htmlspecialchars($_POST['return_date'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="objective" class="form-label">Objectif de la Mission*</label>
                    <textarea class="form-control" id="objective" name="objective" rows="3" required><?= htmlspecialchars($_POST['objective'] ?? '') ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vehicle_registration" class="form-label">Immatriculation Véhicule (si applicable)</label>
                        <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" value="<?= htmlspecialchars($_POST['vehicle_registration'] ?? '') ?>" placeholder="Ex: 12345-116-01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="notes" class="form-label">Notes (Optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Enregistrer l'Ordre</button>
                <a href="<?= route('missions_list_missions') ?>" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>