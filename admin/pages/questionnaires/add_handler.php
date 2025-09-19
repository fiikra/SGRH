<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

// Redirect if the request method is not POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . route('employees_list'));
    exit();
}

// --- Input Validation & Sanitization ---
$nin = sanitize($_POST['employee_nin'] ?? '');
$questionnaire_type = sanitize($_POST['questionnaire_type'] ?? '');
$request_date = sanitize($_POST['request_date'] ?? '');
$user_id = $_SESSION['user_id'];

// Define the redirect target URL once
$redirect_url = route('employees_view', ['nin' => $nin]) . "#questionnaires";

if (empty($nin) || empty($questionnaire_type) || empty($request_date)) {
    // Use the flash message system
    flash('error', 'Tous les champs sont requis pour créer un questionnaire.');
    header("Location: " . $redirect_url);
    exit();
}

// --- Database Interaction ---
try {
    $sql = "INSERT INTO employee_questionnaires 
                (employee_nin, questionnaire_type, request_date, status, created_by)
            VALUES 
                (:nin, :type, :request_date, 'Généré', :user_id)";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':nin' => $nin,
        ':type' => $questionnaire_type,
        ':request_date' => $request_date,
        ':user_id' => $user_id
    ]);

    // Set success message
    flash('success', 'Le questionnaire a été créé avec succès.');

} catch (PDOException $e) {
    // Log the detailed error and show a generic message to the user
    error_log("Questionnaire creation error: " . $e->getMessage());
    flash('error', 'Erreur lors de la création du questionnaire.');
}

// --- Final Redirect ---
header("Location: " . $redirect_url);
exit();