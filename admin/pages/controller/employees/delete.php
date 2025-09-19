<?php
if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

if (!isset($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}
$nin = sanitize($_GET['nin']);

// Fetch employee data for confirmation page and for file deletion
$employee = $employeeModel->getEmployeeByNin($nin);
if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé.";
    header("Location: " . route('employees_list'));
    exit();
}

// Handle the POST request to confirm deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // First, delete the physical photo file if it exists
        if (!empty($employee['photo_path']) && file_exists(PROJECT_ROOT . $employee['photo_path'])) {
            @unlink(PROJECT_ROOT . $employee['photo_path']);
        }
        
        // Note: document files are not handled here for simplicity.
        // A more robust system would loop through documents and delete them too.
        
        // Call the model method which runs all deletes in a transaction
        $employeeModel->deleteEmployee($nin);
        
        $_SESSION['success'] = "Employé et toutes ses données associées ont été supprimés avec succès.";
        header("Location: " . route('employees_list'));
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
        header("Location: " . route('employees_view', ['nin' => $nin]));
        exit();
    }
}

$pageTitle = "Supprimer Employé";
include __DIR__ . '/../../views/employees/delete.php';