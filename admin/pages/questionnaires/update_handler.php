<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();
$user_id = $_SESSION['user_id'];

// --- Request Method Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . route('employees_list'));
    exit();
}

// --- Input Sanitization ---
$reference_number = sanitize($_POST['reference_number'] ?? '');
$nin = sanitize($_POST['employee_nin'] ?? '');
$status = sanitize($_POST['status'] ?? '');
$response_summary = sanitize($_POST['response_summary'] ?? null);
$decision = sanitize($_POST['decision'] ?? null);

// Define the redirect URL once to avoid repetition
$redirect_url = route('employees_view', ['nin' => $nin]) . "#questionnaires";

// --- Form Validation ---
if (empty($reference_number) || empty($nin) || empty($status) || empty($user_id)) {
    flash('error', 'Informations manquantes pour la mise à jour (Référence, NIN ou Statut).');
    header("Location: " . $redirect_url);
    exit();
}

$allowed_statuses = ['pending_response', 'responded', 'decision_made', 'closed'];
if (!in_array($status, $allowed_statuses)) {
    flash('error', 'Le statut fourni est invalide.');
    header("Location: " . $redirect_url);
    exit();
}

// --- Business Logic ---
$response_date = null;
if ($status === 'responded' || $status === 'closed') {
    $response_date = date('Y-m-d');
}

// --- Database Interaction ---
try {
    // [CORRECTION] Remplacement de IF() par IFNULL() pour une meilleure compatibilité
    $sql = "UPDATE employee_questionnaires SET
                status = :status,
                response_summary = :summary,
                decision = :decision,
                response_date = IFNULL(response_date, :response_date),
                updated_at = NOW(),
                updated_by = :user_id
            WHERE 
                reference_number = :reference_number AND employee_nin = :nin";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':status' => $status,
        ':summary' => !empty($response_summary) ? $response_summary : null,
        ':decision' => !empty($decision) ? $decision : null,
        ':response_date' => $response_date,
        ':user_id' => $user_id,
        ':reference_number' => $reference_number,
        ':nin' => $nin
    ]);

    if ($stmt->rowCount() > 0) {
        flash('success', 'Le statut du questionnaire a été mis à jour avec succès.');
    } else {
        flash('info', 'Aucune modification n\'a été détectée ou le questionnaire n\'a pas été trouvé.');
    }

} catch (PDOException $e) {
    error_log("Questionnaire update error for REF {$reference_number}: " . $e->getMessage());
    flash('error', 'Une erreur technique est survenue lors de la mise à jour du questionnaire.');
}

// --- Final Redirect ---
header("Location: " . $redirect_url);
exit();