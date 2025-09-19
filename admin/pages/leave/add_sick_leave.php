<?php
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}
redirectIfNotHR();

// Fetch employees
$employees = $db->query("SELECT nin, first_name, last_name FROM employees ORDER BY last_name, first_name")->fetchAll();

// --- FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nin = sanitize($_POST['employee_nin']);
    $startDate = sanitize($_POST['start_date']);
    $endDate = sanitize($_POST['end_date']);
    $reason = sanitize($_POST['reason']);

    // Handle file upload
    $justification_path = null;
    if (isset($_FILES['justification']) && $_FILES['justification']['error'] == UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['justification']['tmp_name'];
        $fileName = basename($_FILES['justification']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
        if (in_array($fileExt, $allowedExt)) {
            $uploadDir = PROJECT_ROOT . '/assets/uploads/sick_justifications/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $uniqueName = 'maladie_' . $nin . '_' . date('YmdHis') . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $uniqueName;
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $justification_path = 'assets/uploads/sick_justifications/' . $uniqueName;
            } else {
                $_SESSION['error'] = "Erreur lors du téléchargement du fichier justificatif.";
            }
        } else {
            $_SESSION['error'] = "Format de fichier non autorisé (pdf, jpg, jpeg, png uniquement).";
        }
    }

    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    // 1. Check if there is an overlapping approved or pending leave (ANY type, except Maladie itself)
    $overlapStmt = $db->prepare("
        SELECT * FROM leave_requests 
        WHERE employee_nin = ?
          AND status IN ('pending', 'approved')
          AND leave_type != 'Maladie'
          AND (
            (start_date <= ? AND end_date >= ?) OR
            (start_date <= ? AND end_date >= ?) OR
            (start_date >= ? AND end_date <= ?)
          )
        ORDER BY start_date ASC
    ");
    $overlapStmt->execute([
        $nin,
        $startDate, $startDate,
        $endDate, $endDate,
        $startDate, $endDate
    ]);
    $overlappingLeave = $overlapStmt->fetch(PDO::FETCH_ASSOC);

    if ($overlappingLeave) {
        // Pause the overlapping leave for the period of the sickness
        try {
            $db->beginTransaction();

            // 1.1 Insert the new Maladie leave (approved)
            $stmt = $db->prepare("INSERT INTO leave_requests
                (employee_nin, leave_type, start_date, end_date, days_requested, reason, status, created_at, justification_path)
                VALUES (?, ?, ?, ?, ?, ?, 'Maladie', NOW(), ?)");
            $stmt->execute([
                $nin,
                'Maladie',
                $startDate,
                $endDate,
                $days,
                $reason,
                $justification_path
            ]);
            $maladie_leave_id = $db->lastInsertId();

            // 1.2 Pause the existing leave (update leave_requests, insert into leave_pauses)
            $leave_id_to_pause = $overlappingLeave['id'];

            // Add the pause
            $stmtPause = $db->prepare("INSERT INTO leave_pauses
                (leave_request_id, pause_start_date, pause_end_date, reason, attachment_filename, created_by)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmtPause->execute([
                $leave_id_to_pause,
                $startDate,
                $endDate,
                'Pause automatique (congé maladie du ' . $startDate . ' au ' . $endDate . ')',
                $justification_path,
                $_SESSION['user_id'] ?? null
            ]);

            // Update leave status to paused
            $stmtUpdate = $db->prepare("UPDATE leave_requests SET status = 'paused' WHERE id = ?");
            $stmtUpdate->execute([$leave_id_to_pause]);

            $db->commit();
            $_SESSION['leave_success'] = true;
            header("Location: " . route('leaves_list_sick'));
            exit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = "Erreur (pause automatique) : " . $e->getMessage();
        }
    } else {
        // No overlap, just insert the Maladie leave
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO leave_requests
                (employee_nin, leave_type, start_date, end_date, days_requested, reason, status, created_at, justification_path)
                VALUES (?, ?, ?, ?, ?, ?, 'Maladie', NOW(), ?)");
            $stmt->execute([
                $nin,
                'Maladie',
                $startDate,
                $endDate,
                $days,
                $reason,
                $justification_path
            ]);

            $db->commit();
            $_SESSION['leave_success'] = true;
            header("Location: " . route('leave_List_sick_leave'));
            exit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = "Erreur: " . $e->getMessage();
        }
    }
    // After inserting into leave_pauses ...
    // (the following block seems out of place in the original and is not needed unless you have an explicit pause restore flow)
}

$pageTitle = "Ajouter une Maladie";
include __DIR__. '../../../../includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Ajouter une Maladie (Arrêt maladie)</h1>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['leave_success'])): ?>
        <div class="alert alert-success">
            Arrêt maladie enregistré avec succès.
        </div>
        <?php unset($_SESSION['leave_success']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" action="<?= route('leave_add_sick_leave') ?>">
                <?php csrf_input(); // ✅ Correct: Just call the function here ?>
            <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Employé*</label>
                            <select name="employee_nin" class="form-select" required>
                                <option value="">Sélectionner un employé</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= htmlspecialchars($emp['nin']) ?>">
                                        <?= htmlspecialchars($emp['last_name']) ?> <?= htmlspecialchars($emp['first_name']) ?> (<?= htmlspecialchars($emp['nin']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Date Début*</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Date Fin*</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Justificatif (PDF, JPG, PNG) *</label>
                    <input type="file" name="justification" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motif / Détail*</label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Ex: Arrêt maladie prescrit, référence certificat médical..."></textarea>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Enregistrer la maladie
                    </button>
                    <a href="<?= route('leaves_list_sick') ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');

        // Définir la date de début par défaut à aujourd'hui
        startDateInput.valueAsDate = new Date();

        // Synchroniser la date de fin avec la date de début
        startDateInput.addEventListener('change', function() {
            if (!endDateInput.value || new Date(endDateInput.value) < new Date(this.value)) {
                endDateInput.value = this.value;
            }
        });
    });
</script>

<?php include __DIR__. '../../../../includes/footer.php'; ?>