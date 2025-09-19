<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

$docId = sanitize($_GET['id'] ?? null);
$nin = sanitize($_GET['nin'] ?? null);

// Ensure both required parameters are present
if (!$docId || !$nin) {
    $_SESSION['error'] = "Requête invalide. Informations manquantes pour la suppression.";
    // Redirect to the main list if we don't know which employee to go back to
    $redirect_url = $nin ? route('employees_documents', ['nin' => $nin]) : route('employees_list');
    header("Location: " . $redirect_url);
    exit();
}

try {
    // Step 1: Get the document's file path from the database BEFORE deleting the record
    $filePath = $employeeModel->getDocumentFilePath($docId);
    
    // Step 2: Delete the database record
    $isDeleted = $employeeModel->deleteDocument($docId, $nin);

    if ($isDeleted) {
        // Step 3: If the database record was successfully deleted, delete the physical file
        if ($filePath && file_exists(PROJECT_ROOT . $filePath)) {
            // Use @unlink to suppress warnings if the file is somehow already gone
            @unlink(PROJECT_ROOT . $filePath);
        }
        $_SESSION['success'] = "Document supprimé avec succès.";
    } else {
        // This case might happen if the document ID doesn't exist or doesn't belong to the employee
        throw new Exception("Le document n'a pas pu être trouvé ou a déjà été supprimé.");
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de la suppression du document : " . $e->getMessage();
}

// Redirect back to the employee's document management page
header("Location: " . route('employees_documents', ['nin' => $nin]));
exit();