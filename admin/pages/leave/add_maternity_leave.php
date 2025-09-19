<?php
// --- Security Headers ---


// --- Application Security ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

// --- Authorization ---
redirectIfNotHR();

// --- Page Setup ---
$pageTitle = "Ajouter un Congé de Maternité";
$errors = [];
$employee_nin = '';
$start_date_str = '';
$end_date_str = '';
$notes = '';

// --- Database Queries ---
try {
    $company_settings_stmt = $db->query("SELECT maternite_leave_days FROM company_settings WHERE id = 1 LIMIT 1");
    $company_setting = $company_settings_stmt->fetch(PDO::FETCH_ASSOC);
    $default_maternity_leave_days = !empty($company_setting['maternite_leave_days']) ? (int)$company_setting['maternite_leave_days'] : 98;

    $employees_stmt = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status = 'active' AND gender = 'female' ORDER BY last_name, first_name");
    $female_employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $errors[] = "Une erreur de base de données est survenue.";
    $female_employees = [];
    $default_maternity_leave_days = 98;
}

// --- Form Submission Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = "Erreur de sécurité (jeton CSRF invalide).";
    } else {
        $employee_nin = filter_input(INPUT_POST, 'employee_nin', FILTER_SANITIZE_STRING);
        $start_date_str = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
        $end_date_str = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
        $justification_file = $_FILES['justification_document'] ?? null;

        // Validation...
        if (empty($employee_nin)) $errors[] = "Veuillez sélectionner une employée.";
        if (empty($start_date_str)) $errors[] = "La date de début est requise.";
        if (empty($end_date_str)) $errors[] = "La date de fin est requise.";

        $start_date = DateTime::createFromFormat('Y-m-d', $start_date_str);
        $end_date = DateTime::createFromFormat('Y-m-d', $end_date_str);

        if ($start_date === false) $errors[] = "Format de date de début invalide.";
        if ($end_date === false) $errors[] = "Format de date de fin invalide.";
        if ($start_date && $end_date && $start_date > $end_date) $errors[] = "La date de début ne peut pas être après la date de fin.";

        if ($start_date && $end_date && empty($errors)) {
            $overlap_stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_nin = :nin AND status IN ('approved', 'pending') AND start_date <= :end_date AND end_date >= :start_date");
            $overlap_stmt->execute([':nin' => $employee_nin, ':start_date' => $start_date_str, ':end_date' => $end_date_str]);
            if ($overlap_stmt->fetchColumn() > 0) {
                $errors[] = "Cette employée a déjà une demande de congé qui se chevauche avec ces dates.";
            }
        }

        // File Upload Handling
        $justification_path_db = null;
        if ($justification_file && $justification_file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = PROJECT_ROOT . '/assets/uploads/sick_justifications/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            // **MODIFICATION HERE: Check file size against the server's default value**
            if ($justification_file['size'] > get_max_upload_size()) {
                 $max_size_in_mb = round(get_max_upload_size() / 1024 / 1024, 2);
                 $errors[] = "Fichier trop volumineux. La taille maximale autorisée par le serveur est de {$max_size_in_mb}MB.";
            } else {
                $original_filename = basename($justification_file['name']);
                $safe_filename = preg_replace("/[^A-Za-z0-9\._-]/", '', $original_filename);
                $extension = pathinfo($safe_filename, PATHINFO_EXTENSION);
                $filename = "maternite_" . $employee_nin . "_" . date('YmdHis') . "." . $extension;
                $file_path = $upload_dir . $filename;
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];

                if (in_array(mime_content_type($justification_file['tmp_name']), $allowed_types)) {
                    if (move_uploaded_file($justification_file['tmp_name'], $file_path)) {
                        $justification_path_db = $upload_dir . $filename;
                    } else {
                        $errors[] = "Erreur lors du téléchargement du fichier justificatif.";
                    }
                } else {
                    $errors[] = "Type de fichier invalide pour le justificatif. Formats acceptés: PDF, JPG, PNG.";
                }
            }
        } elseif ($justification_file && $justification_file['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Erreur avec le fichier justificatif (code: " . $justification_file['error'] . ").";
        }

        // Database Transaction
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $days_requested = $end_date->diff($start_date)->days + 1;

                $sql_leave = "INSERT INTO leave_requests (employee_nin, leave_type, start_date, end_date, days_requested, reason, status, justification_path, comment, created_at, leave_year)
                              VALUES (:employee_nin, 'maternite', :start_date, :end_date, :days_requested, 'Congé de Maternité', 'approved', :justification_path, :comment, NOW(), :leave_year)";
                $stmt_leave = $db->prepare($sql_leave);
                $stmt_leave->execute([
                    ':employee_nin' => $employee_nin, ':start_date' => $start_date_str, ':end_date' => $end_date_str,
                    ':days_requested' => $days_requested, ':justification_path' => $justification_path_db,
                    ':comment' => $notes, ':leave_year' => $start_date->format('Y')
                ]);

                $current_loop_date = clone $start_date;
                $recorded_by = $_SESSION['user_id'] ?? null;
                $sql_att = "REPLACE INTO employee_attendance (employee_nin, attendance_date, status, leave_type_if_absent, recorded_by_user_id, created_at, updated_at) 
                            VALUES (:nin, :att_date, 'maternity_leave', 'Maternité (Système)', :user_id, NOW(), NOW())";
                $stmt_att = $db->prepare($sql_att);

                while ($current_loop_date <= $end_date) {
                    $stmt_att->execute([':nin' => $employee_nin, ':att_date' => $current_loop_date->format('Y-m-d'), ':user_id' => $recorded_by]);
                    $current_loop_date->modify('+1 day');
                }

                $db->commit();
                $_SESSION['success_message'] = "Congé de maternité ajouté avec succès pour l'employée NIN " . htmlspecialchars($employee_nin) . ".";
                header("Location: " . route('leaves_list_sick'));
                exit();
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $errors[] = "Erreur lors de l'enregistrement: " . $e->getMessage();
                error_log("Maternity Leave Add Error: " . $e->getMessage());
            }
        }
    }
}

// **MODIFICATION HERE: Get max size for display in the form**
$max_upload_mb = round(get_max_upload_size() / 1024 / 1024, 2);

include __DIR__ . '../../../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= route('leaves_requests') ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour aux Demandes</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Erreur !</strong>
        <?php foreach ($errors as $error): ?>
            <p class="mb-0"><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= route('leave_add_maternity_leave') ?>" enctype="multipart/form-data">
               <?php csrf_input(); // ✅ Correct: Just call the function here ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="employee_nin" class="form-label">Employée*</label>
                        <select class="form-select" id="employee_nin" name="employee_nin" required>
                            <option value="" disabled <?= empty($employee_nin) ? 'selected' : '' ?>>Sélectionner une employée</option>
                            <?php foreach ($female_employees as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= ($employee_nin === $emp['nin']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name'] . ' (NIN: ' . $emp['nin'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="start_date" class="form-label">Date de Début*</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date_str) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="end_date" class="form-label">Date de Fin*</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date_str) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="days_requested_display" class="form-label">Durée (jours calendaires)</label>
                        <input type="text" class="form-control" id="days_requested_display" readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="justification_document" class="form-label">Justificatif (Optionnel)</label>
                        <input type="file" class="form-control" id="justification_document" name="justification_document" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text text-muted">Formats: PDF, JPG, PNG. Taille max: <?= $max_upload_mb ?>MB.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="notes" class="form-label">Notes (Optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($notes) ?></textarea>
                    </div>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer le Congé
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const daysDisplay = document.getElementById('days_requested_display');
    const defaultLeaveDays = <?= (int)$default_maternity_leave_days ?>;

    function calculateDates(event) {
        const startDateStr = startDateInput.value;
        const endDateStr = endDateInput.value;
        if (!startDateStr) {
            endDateInput.value = '';
            daysDisplay.value = '';
            return;
        }
        const startDate = new Date(startDateStr);
        if (isNaN(startDate.getTime())) {
            daysDisplay.value = 'Date de début invalide';
            return;
        }
        let endDate;
        const isStartDateChange = event && event.target.id === 'start_date';
        if (isStartDateChange || !endDateStr) {
            endDate = new Date(startDate.getTime());
            endDate.setDate(endDate.getDate() + defaultLeaveDays - 1);
            endDateInput.value = endDate.toISOString().split('T')[0];
        } else {
            endDate = new Date(endDateStr);
        }
        if (!isNaN(endDate.getTime())) {
            const duration = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
            daysDisplay.value = duration > 0 ? `${duration} jours` : '';
        } else {
            daysDisplay.value = 'Date de fin invalide';
        }
    }
    startDateInput.addEventListener('change', calculateDates);
    endDateInput.addEventListener('change', calculateDates);
    calculateDates();
});
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>