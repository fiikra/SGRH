<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Request Method Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . route('employees_list'));
    exit();
}

// --- Input Sanitization & Validation ---
$nin = sanitize($_POST['employee_nin'] ?? '');
$questionnaire_id = sanitize($_POST['questionnaire_id'] ?? '');
$sanction_type = sanitize($_POST['sanction_type'] ?? '');
$sanction_date = sanitize($_POST['sanction_date'] ?? '');
$fault_date = sanitize($_POST['fault_date'] ?? '');
$reason = sanitize($_POST['reason'] ?? '');
$user_id = $_SESSION['user_id'];

$redirect_url = route('employees_view', ['nin' => $nin]) . "#sanctions";

if (empty($nin) || empty($questionnaire_id) || empty($sanction_type) || empty($sanction_date) || empty($reason)) {
    flash('error', 'Tous les champs sont requis, y compris la sélection d\'un questionnaire.');
    header("Location: " . $redirect_url);
    exit();
}

// --- Database Transaction ---
$db->beginTransaction();
try {
    // 1. Generate a robust reference number
    $reference_number = generate_reference_number('SC', 'employee_sanctions', 'reference_number', $db);

    // 2. Insert the new sanction record
    $sql_insert = "INSERT INTO employee_sanctions 
                      (reference_number, employee_nin, questionnaire_id, sanction_type, reason, sanction_date, created_by) 
                   VALUES 
                      (:ref, :nin, :q_id, :sanction_type, :reason, :sanction_date, :user_id)";
    $stmt_insert = $db->prepare($sql_insert);
    $stmt_insert->execute([
        ':ref' => $reference_number,
        ':nin' => $nin,
        ':q_id' => $questionnaire_id,
        ':sanction_type' => $sanction_type,
        ':reason' => $reason,
        ':sanction_date' => $sanction_date,
        ':user_id' => $user_id
    ]);
    
    // 3. If the sanction is a dismissal, update the employee's status
    if ($sanction_type === 'licenciement') {
        $sql_update = "UPDATE employees SET 
                           status = 'inactive', 
                           departure_date = :departure_date, 
                           departure_reason = 'Licenciement' 
                       WHERE nin = :nin";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->execute([
            ':departure_date' => $sanction_date,
            ':nin' => $nin
        ]);
    }
    
    // If all queries were successful, commit the transaction
    $db->commit();
    flash('success', 'La sanction a été enregistrée avec succès (Réf: ' . htmlspecialchars($reference_number) . ')');

} catch (PDOException $e) {
    // If anything fails, roll back the entire transaction
    $db->rollBack();
    error_log("Sanction Error: " . $e->getMessage());
    
    // Provide a specific error if the questionnaire was already used (unique constraint violation)
    if ($e->getCode() == 23000) {
        flash('error', "Erreur: Ce questionnaire a déjà été utilisé pour une autre sanction.");
    } else {
        flash('error', "Une erreur de base de données est survenue lors de l'enregistrement.");
    }
}

// --- Final Redirect ---
header("Location: " . $redirect_url);
exit();