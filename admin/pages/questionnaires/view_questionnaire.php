<?php
// =========================================================================
// == SECURE PDF VIEWER LOGIC
// =========================================================================

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
if (isset($_GET['action']) && $_GET['action'] === 'view_response_pdf') {
    // This block handles serving the protected PDF file.
    // It runs before any other logic on this page.

    // --- Security & Initialization ---
    
    redirectIfNotHR();

    $questionnaire_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$questionnaire_id) {
        http_response_code(400);
        die('Invalid or missing questionnaire ID.');
    }

    try {
        $stmt = $db->prepare("SELECT response_pdf_path FROM employee_questionnaires WHERE id = :id");
        $stmt->execute([':id' => $questionnaire_id]);
        $pdf_filename = $stmt->fetchColumn();

        if (!$pdf_filename) {
            http_response_code(404);
            die('No response PDF found for this questionnaire.');
        }

        $file_path = __DIR__ . '/../../../../assets/uploads/questionnaire_responses/' . $pdf_filename;

        if (!file_exists($file_path) || !is_readable($file_path)) {
            http_response_code(404);
            error_log("File not found or not readable: " . $file_path);
            die('The requested file could not be found on the server.');
        }

        // --- Serve the File ---
        header("Content-Security-Policy: frame-ancestors 'self'");
        header_remove('X-Frame-Options');
        
        header('Content-Type: application/pdf');
        // Use 'attachment' to force download, or 'inline' to have the browser try to display it.
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        ob_clean();
        flush();
        readfile($file_path);
        exit();

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("PDF viewer database error: " . $e->getMessage());
        die('A database error occurred while trying to retrieve the file.');
    }
}

// =========================================================================
// == QUESTIONNAIRE UPDATE HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_questionnaire') {
    redirectIfNotHR();

    // --- Sanitize and validate inputs ---
    $questionnaire_id = filter_input(INPUT_POST, 'questionnaire_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $response_summary = filter_input(INPUT_POST, 'response_summary', FILTER_SANITIZE_STRING);
    $decision = filter_input(INPUT_POST, 'decision', FILTER_SANITIZE_STRING);
    $updated_by = $_SESSION['user_id'] ?? null;
    
    $response_date = (in_array($status, ['responded', 'decision_made', 'closed'])) ? date('Y-m-d') : null;

    if (!$questionnaire_id) {
        flash('error', 'ID de questionnaire invalide pour la mise à jour.');
        header("Location: " . route('questionnaires_index'));
        exit();
    }
    
    try {
        $stmt_current = $db->prepare("SELECT response_pdf_path FROM employee_questionnaires WHERE id = :id");
        $stmt_current->execute([':id' => $questionnaire_id]);
        $current_pdf_path = $stmt_current->fetchColumn();
        $new_pdf_path = $current_pdf_path;

        if (isset($_FILES['response_pdf']) && $_FILES['response_pdf']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '../../../../assets/uploads/questionnaire_responses/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileType = mime_content_type($_FILES['response_pdf']['tmp_name']);
            if ($fileType !== 'application/pdf') {
                 flash('error', 'Le fichier de réponse doit être un PDF.');
                 header("Location: " . route('questionnaires_view_questionnaire', ['id' => $questionnaire_id]));
                 exit();
            }

            $fileName = 'response_' . $questionnaire_id . '_' . time() . '.pdf';
            $uploadFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['response_pdf']['tmp_name'], $uploadFile)) {
                $new_pdf_path = $fileName;
            } else {
                throw new Exception('Erreur lors du téléchargement du fichier PDF de réponse.');
            }
        }

        $sql = "UPDATE employee_questionnaires SET
                    status = ?,
                    response_summary = ?,
                    decision = ?,
                    response_pdf_path = ?,
                    response_date = IF(response_date IS NULL AND ? IS NOT NULL, ?, response_date),
                    updated_at = ?,
                    updated_by = ?
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        
        $params = [
            $status,
            $response_summary,
            $decision,
            $new_pdf_path,
            $response_date,
            $response_date,
            date('Y-m-d H:i:s'),
            $updated_by,
            $questionnaire_id
        ];
        
        $stmt->execute($params);

        flash('success', 'Le questionnaire a été mis à jour avec succès.');

    } catch (Exception $e) {
        error_log("Questionnaire update error: " . $e->getMessage());
        flash('error', 'Une erreur est survenue lors de la mise à jour: ' . $e->getMessage());
    }

    header("Location: " . route('questionnaires_view_questionnaire', ['id' => $questionnaire_id]));
    exit();
}

// =========================================================================
// == SANCTION CREATION HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_sanction') {
    redirectIfNotHR();

    $employee_nin = filter_input(INPUT_POST, 'employee_nin', FILTER_SANITIZE_STRING);
    $questionnaire_id = filter_input(INPUT_POST, 'questionnaire_id', FILTER_VALIDATE_INT);
    $fault_date = filter_input(INPUT_POST, 'fault_date', FILTER_SANITIZE_STRING);
    $sanction_type = filter_input(INPUT_POST, 'sanction_type', FILTER_SANITIZE_STRING);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $sanction_date = filter_input(INPUT_POST, 'sanction_date', FILTER_SANITIZE_STRING);
    $decision_ref = filter_input(INPUT_POST, 'decision_ref', FILTER_SANITIZE_STRING);
    $created_by = $_SESSION['user_id'] ?? null;

    if (empty($employee_nin) || empty($fault_date) || empty($sanction_type) || empty($reason) || empty($sanction_date)) {
        flash('error', 'Veuillez remplir tous les champs obligatoires pour créer la sanction.');
    } else {
        $db->beginTransaction();
        try {
            $sql_insert = "INSERT INTO employee_sanctions (employee_nin, questionnaire_id, fault_date, sanction_type, reason, sanction_date, decision_ref, created_by)
                           VALUES (:employee_nin, :questionnaire_id, :fault_date, :sanction_type, :reason, :sanction_date, :decision_ref, :created_by)";
            $stmt_insert = $db->prepare($sql_insert);
            $stmt_insert->execute([
                ':employee_nin' => $employee_nin,
                ':questionnaire_id' => $questionnaire_id,
                ':fault_date' => $fault_date,
                ':sanction_type' => $sanction_type,
                ':reason' => $reason,
                ':sanction_date' => $sanction_date,
                ':decision_ref' => $decision_ref,
                ':created_by' => $created_by
            ]);
            $new_sanction_id = $db->lastInsertId();

            $sql_update = "UPDATE employee_questionnaires SET sanction_id = :sanction_id WHERE id = :questionnaire_id";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->execute([
                ':sanction_id' => $new_sanction_id,
                ':questionnaire_id' => $questionnaire_id
            ]);

            $db->commit();
            flash('success', 'La sanction a été créée et liée à ce questionnaire avec succès.');

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Sanction creation/linking error: " . $e->getMessage());
            flash('error', 'Une erreur de base de données est survenue lors de la création de la sanction.');
        }
    }
    header("Location: " . route('questionnaires_view_questionnaire', ['id' => $questionnaire_id]));
    exit();
}


// =========================================================================
// == PAGE DISPLAY LOGIC
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

$q_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$linked_sanction = null;

if (!$q_id) {
    flash('error', "ID de questionnaire invalide ou manquant.");
    header("Location: " . route('employees_list'));
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT q.*, e.first_name, e.last_name, e.position, e.nin
        FROM employee_questionnaires q
        JOIN employees e ON q.employee_nin = e.nin
        WHERE q.id = :q_id
    ");
    $stmt->execute([':q_id' => $q_id]);
    $q_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$q_data) {
        flash('error', "Questionnaire non trouvé.");
        header("Location: " . route('questionnaires_index'));
        exit();
    }

    if (!empty($q_data['sanction_id'])) {
        $sanction_stmt = $db->prepare("SELECT * FROM employee_sanctions WHERE id = :sanction_id");
        $sanction_stmt->execute([':sanction_id' => $q_data['sanction_id']]);
        $linked_sanction = $sanction_stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Questionnaire view error: " . $e->getMessage());
    flash('error', "Une erreur de base de données est survenue.".$e->getMessage());
    header("Location: " . route('questionnaires_index'));
    exit();
}

$pageTitle = "Détails du Questionnaire";
include __DIR__. '../../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h1 class="h3 mb-2 mb-sm-0">Détails du Questionnaire</h1>
        <a href="<?= route('employees_view', ['nin' => $q_data['nin']]) ?>#questionnaires" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour au Profil
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Informations Générales</h5>
                </div>
                <div class="card-body">
                    <p><strong>Employé:</strong> <?= htmlspecialchars($q_data['first_name'] . ' ' . $q_data['last_name']) ?></p>
                    <p><strong>Type:</strong> <?= htmlspecialchars($q_data['questionnaire_type']) ?></p>
                    <p><strong>Reférence:</strong> <?= htmlspecialchars($q_data['reference_number']) ?></p>
                    <p><strong>Date d'émission:</strong> <?= formatDate($q_data['issue_date']) ?></p>
                    <p><strong>Date limite de réponse:</strong> <?= $q_data['response_due_date'] ? formatDate($q_data['response_due_date']) : 'N/A' ?></p>
                    <hr>
                    <p class="mb-0"><strong>Statut Actuel:</strong>
                        <?php 
                            $status_labels = ['pending_response' => 'En attente', 'responded' => 'Répondu', 'decision_made' => 'Décision prise', 'closed' => 'Clôturé'];
                            $status_badges = ['pending_response' => 'warning', 'responded' => 'info', 'decision_made' => 'primary', 'closed' => 'success'];
                        ?>
                        <span class="badge bg-<?= $status_badges[$q_data['status']] ?? 'secondary' ?>"><?= $status_labels[$q_data['status']] ?? 'Inconnu' ?></span>
                    </p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-folder2-open"></i> Documents</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="<?= route('questionnaires_generate_questionnaire_pdf', ['id' => $q_id]) ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            Voir le Questionnaire Original
                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                        </a>
                        <?php if (!empty($q_data['response_pdf_path'])): ?>
                            <a href="<?= APP_LINK . '/assets/uploads/questionnaire_responses/' . htmlspecialchars($q_data['response_pdf_path']) ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                Voir la Réponse Signée
                                <i class="bi bi-file-earmark-check-fill text-success"></i>
                            </a>
                        <?php else: ?>
                            <div class="list-group-item disabled text-muted">
                                Aucune réponse signée n'a été uploadée.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
           
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Suivi et Décision</h5>
                </div>
                <div class="card-body">
                    <form action="<?= route('questionnaires_view_questionnaire', ['id' => $q_id]) ?>" method="post" enctype="multipart/form-data">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="update_questionnaire">
                        <input type="hidden" name="questionnaire_id" value="<?= $q_id ?>">
                        <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($q_data['nin']) ?>">
                         <?php if ($q_data['status'] != 'closed' ): ?>
                        <div class="mb-3">
                            <label for="status" class="form-label">Changer le statut</label>
                            <select name="status" class="form-select">
                                <option value="pending_response" <?= $q_data['status'] == 'pending_response' ? 'selected' : '' ?>>En attente de réponse</option>
                                <option value="responded" <?= $q_data['status'] == 'responded' ? 'selected' : '' ?>>Répondu</option>
                                <option value="decision_made" <?= $q_data['status'] == 'decision_made' ? 'selected' : '' ?>>Décision prise</option>
                                <option value="closed" <?= $q_data['status'] == 'closed' ? 'selected' : '' ?>>Clôturé</option>
                            </select>
                        </div>
                         <?php endif; ?>
                        <div class="mb-3">
                            <label for="response_summary" class="form-label">Résumé / Compte-rendu</label>
                            <textarea name="response_summary" class="form-control" rows="4"><?= htmlspecialchars($q_data['response_summary'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="decision" class="form-label">Décision finale</label>
                            <textarea name="decision" class="form-control" rows="3"><?= htmlspecialchars($q_data['decision'] ?? '') ?></textarea>
                        </div>
                        <?php if ( $q_data['status'] != 'closed'  ): ?>
                             <?php if ( $q_data['status'] != 'responded'  ): ?>
                        <div class="mb-3">
                            <label for="response_pdf" class="form-label">Uploader les réponses signées (PDF)</label>
                            <input type="file" name="response_pdf" class="form-control" accept=".pdf">
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                         <?php if ($q_data['status'] != 'closed' ): ?>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg"></i> Enregistrer les modifications</button>
                         <?php endif; ?>
                    </form>
                </div>
            </div>
             
            <?php if ($q_data['status'] === 'closed'  && $q_data['questionnaire_type'] === 'Entretien préalable à une sanction' && !$linked_sanction): ?>
            <!-- New Sanction Card -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Créer une Sanction Suite à ce Questionnaire</h5>
                </div>
                <div class="card-body">
                    <form action="<?= route('sanctions_add_handler', ['id' => $q_id]) ?>" method="post">
                        <?php csrf_input(); ?>
                        
                        <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($q_data['nin']) ?>">
                        <input type="hidden" name="questionnaire_id" value="<?= $q_id ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sanction_type" class="form-label">Type de Sanction</label>
                                <select name="sanction_type" id="sanction_type" class="form-select" required>
                                    <option value="" disabled selected>-- Choisir un type --</option>
                                    <option value="avertissement_verbal">Avertissement Verbal</option>
                                    <option value="avertissement_ecrit">Avertissement Écrit</option>
                                    <option value="mise_a_pied_1">Mise à pied (1er degré)</option>
                                    <option value="mise_a_pied_2">Mise à pied (2ème degré)</option>
                                    <option value="mise_a_pied_3">Mise à pied (3ème degré)</option>
                                    <option value="licenciement">Licenciement</option>
                                </select>
                            </div>
                           
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fault_date" class="form-label">Date de la Faute</label>
                                <input type="date" name="fault_date" id="fault_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="sanction_date" class="form-label">Date de la Sanction</label>
                                <input type="date" name="sanction_date" id="sanction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Motif Détaillé</label>
                            <textarea name="reason" id="reason" class="form-control" rows="4" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-save-fill"></i> Enregistrer la Sanction
                        </button>
                    </form>
                </div>
            </div>
            <?php elseif ($linked_sanction): ?>
            <!-- Linked Sanction Info Card -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Sanction Liée</h5>
                </div>
                <div class="card-body">
                    <p>Ce questionnaire a déjà abouti à la sanction suivante :</p>
                    <p><strong>Type :</strong> <?= htmlspecialchars($linked_sanction['sanction_type']) ?></p>
                    <p><strong>Date :</strong> <?= formatDate($linked_sanction['sanction_date']) ?></p>
                    <p><strong>Référence :</strong> <?= htmlspecialchars($linked_sanction['reference_number'] ?? 'N/A') ?></p>
                    <a href="<?= route('sanctions_view_sanction', ['id' => $linked_sanction['id']]) ?>" class="btn btn-outline-danger w-100">
                        Voir les détails de la sanction
                    </a>
                </div>
            </div>
            <?php endif; ?>
             
           
        </div>
       
    </div>
</div>

<?php include __DIR__. '../../../../includes/footer.php'; ?>
