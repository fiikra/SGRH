<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

// This controller only processes POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nin = sanitize($_POST['employee_nin'] ?? '');
    $departure_date = sanitize($_POST['departure_date'] ?? '');
    $departure_reason = sanitize($_POST['departure_reason'] ?? '');
    $custom_reason = sanitize($_POST['custom_reason'] ?? '');

    // Always define the redirect URL early for error handling
    $redirect_url = route('employees_view', ['nin' => $nin]);

    if (empty($nin) || empty($departure_date) || empty($departure_reason)) {
        $_SESSION['error'] = 'Tous les champs requis ne sont pas remplis.';
        header("Location: " . $redirect_url);
        exit();
    }

    // Determine the final reason to store in the database
    $reason_to_store = ($departure_reason === 'Autre' && !empty($custom_reason)) 
        ? $custom_reason 
        : $departure_reason;

    try {
        // Call the model to update the employee's status
        $employeeModel->processEmployeeDeparture($nin, $departure_date, $reason_to_store);
        $_SESSION['success'] = "Le départ de l'employé a été enregistré avec succès.";

    } catch (PDOException $e) {
        // Log the detailed error for the admin, but show a generic message to the user
        error_log("Departure processing error: " . $e->getMessage());
        $_SESSION['error'] = "Erreur de base de données lors de l'enregistrement du départ.";
    }

    header("Location: " . $redirect_url);
    exit();

} else {
    // Redirect any GET requests to the main employee list
    header("Location: " . route('employees_list'));
    exit();
}