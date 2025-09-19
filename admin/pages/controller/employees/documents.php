<?php
if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

if (!isset($_GET['nin'])) {
    $_SESSION['error'] = "Employee NIN not provided.";
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);
$employee = $employeeModel->getEmployeeByNin($nin);

if (!$employee) {
    $_SESSION['error'] = "Employee not found.";
    header("Location: " . route('employees_list'));
    exit();
}

// Handle the POST request for adding a new document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    try {
        if (empty($_POST['document_type']) || empty($_POST['title'])) {
            throw new Exception("Document type and title are required.");
        }
        
        // The uploadFile function should handle validation (size, type) and return the path
        $filePath = uploadFile($_FILES['document_file'], 'documents');
        
        $data = [
            $nin,
            sanitize($_POST['document_type']),
            sanitize($_POST['title']),
            $filePath,
            !empty($_POST['issue_date']) ? sanitize($_POST['issue_date']) : null,
            !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null,
            sanitize($_POST['notes'] ?? null)
        ];
        
        $employeeModel->addDocument($data);
        
        $_SESSION['success'] = "Document uploaded successfully!";
        header("Location: " . route('employees_documents', ['nin' => $nin]));
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Fetch existing documents for the view
$pageTitle = "Documents for " . htmlspecialchars($employee['first_name'] . " " . $employee['last_name']);
$documents = $employeeModel->getEmployeeDocuments($nin)->fetchAll(PDO::FETCH_ASSOC);

// Load the view
include __DIR__ . '/../../views/employees/documents.php';