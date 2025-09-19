<?php
if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

// --- Get filters and pagination ---
$filters = [
    'search'        => sanitize($_GET['search'] ?? ''),
    'status'        => sanitize($_GET['status'] ?? ''),
    'department'    => sanitize($_GET['department'] ?? ''),
    'position'      => sanitize($_GET['position'] ?? ''),
    'hire_date_start' => sanitize($_GET['hire_date_start'] ?? ''),
    'hire_date_end'   => sanitize($_GET['hire_date_end'] ?? ''),
    'salary_min'    => sanitize($_GET['salary_min'] ?? ''),
    'salary_max'    => sanitize($_GET['salary_max'] ?? ''),
];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15; // Increased for better viewing
$offset = ($page - 1) * $perPage;

// --- Call Model to get filtered data ---
// NOTE: The getEmployeesFiltered method in the model needs to be updated to handle all these filters.
$data = $employeeModel->getEmployeesFiltered($filters, $perPage, $offset);
$employees = $data['employees'];
$total = $data['total'];
$totalPages = ceil($total / $perPage);

// --- Data for filter dropdowns ---
$departments = $employeeModel->getDistinct('department');
$positions = $employeeModel->getDistinct('position');

// --- Handle CSV Export (can be a separate controller or handled here) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // This logic should be moved to its own controller (e.g., export_csv.php) for purer MVC,
    // but for now, it's kept as per the original file's structure.
    $exportData = $employeeModel->getEmployeesFiltered($filters, 10000, 0)['employees']; // Get all filtered results

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['NIN', 'Nom Complet', 'Poste', 'Département', 'Date Embauche', 'Statut', 'Salaire']);
    foreach ($exportData as $employee) {
        fputcsv($output, [
            $employee['nin'],
            $employee['first_name'] . ' ' . $employee['last_name'],
            $employee['position'],
            $employee['department'],
            formatDate($employee['hire_date']),
            ucfirst($employee['status']),
            $employee['salary'],
        ]);
    }
    fclose($output);
    exit();
}

$pageTitle = "Liste des Employés";
// --- Load View ---
include __DIR__ . '/../../views/employees/list.php';