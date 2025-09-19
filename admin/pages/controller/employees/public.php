<?php
if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
// NO redirectIfNotHR() for a public page

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

if (empty($_GET['nin'])) { exit('NIN required.'); }
$nin = sanitize($_GET['nin']);
$employee = $employeeModel->getEmployeeByNin($nin);

// Optional: Add a check here if you only want 'active' employees to be public
if (!$employee || $employee['status'] !== 'active') {
    // You can show a generic "not found" page or redirect
    $_SESSION['error'] = "Profile not found or is not active.";
    header("Location: " . route('home')); // Redirect to a generic page
    exit();
}

$pageTitle = "Profil de " . htmlspecialchars($employee['first_name']);
include __DIR__ . '/../../views/employees/public.php';