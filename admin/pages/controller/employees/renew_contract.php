<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nin = sanitize($_POST['employee_nin'] ?? '');
    $duration = filter_var($_POST['renewal_duration_months'] ?? 0, FILTER_VALIDATE_INT);
    
    $redirect_url = route('employees_view', ['nin' => $nin]);

    if (empty($nin) || $duration === false || $duration < 1 || $duration > 12) {
        $_SESSION['error'] = 'Données de renouvellement invalides.';
        header("Location: " . $redirect_url);
        exit();
    }

    try {
        // Récupérer la date de fin actuelle pour calculer la nouvelle
        $employee = $employeeModel->getEmployeeByNin($nin);
        if (!$employee) {
            throw new Exception("Employé non trouvé.");
        }

        $current_end_date = new DateTime($employee['end_date'] ?? 'now');
        $current_end_date->add(new DateInterval("P{$duration}M"));
        $new_end_date = $current_end_date->format('Y-m-d');

        // Appeler la nouvelle méthode du modèle
        $employeeModel->renewContract($nin, $new_end_date, $employee['position'], $employee['department']);
        
        $_SESSION['success'] = "Le contrat a été renouvelé avec succès jusqu'au " . formatDate($new_end_date) . ".";

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors du renouvellement du contrat : " . $e->getMessage();
    }

    header("Location: " . $redirect_url);
    exit();
}

// Rediriger si la page est accédée directement
header("Location: " . route('employees_list'));
exit();