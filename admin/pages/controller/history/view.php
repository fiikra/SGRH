<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

require_once __DIR__ . '../../../model/attendance/AttendanceHistoryModel.php';
$model = new AttendanceHistoryModel($db);

// --- Gestion des filtres ---
$filterYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('Y')]]);
$filterMonth = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('m')]]);
$filterEmployeeNin = sanitize($_GET['employee_nin'] ?? '');

// --- Récupération des données ---
$attendanceRecords = $model->getAttendanceHistory($filterYear, $filterMonth, $filterEmployeeNin);
$employeesList = $model->getActiveEmployees();

// --- Préparation des variables pour la vue ---
$pageTitle = "Historique des Présences";
$monthNameDisplay = "Mois";
try {
    $dateObj = DateTime::createFromFormat('!m', $filterMonth);
    if ($dateObj) {
        $monthNameDisplay = class_exists('IntlDateFormatter')
            ? ucfirst((new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::FULL, IntlDateFormatter::NONE))->format($dateObj))
            : $dateObj->format('F');
    }
} catch(Exception $e) {}

$selectedEmployeeLabel = '';
if ($filterEmployeeNin) {
    foreach ($employeesList as $emp) {
        if ($filterEmployeeNin === $emp['nin']) {
            $selectedEmployeeLabel = htmlspecialchars("{$emp['first_name']} {$emp['last_name']} ({$emp['nin']})");
            break;
        }
    }
}

// Map des codes pour la légende
$attendanceCodeMap = [
    'P'    => ['label' => 'Présent (jour ouvré normal)', 'badge' => 'bg-success'],
    'TF'   => ['label' => 'Présent sur jour férié', 'badge' => 'bg-danger'],
    'TW'   => ['label' => 'Présent sur weekend/vendredi', 'badge' => 'bg-danger'],
    'C'    => ['label' => 'Congé Annuel', 'badge' => 'bg-info text-dark'],
    'M'    => ['label' => 'Maladie', 'badge' => 'bg-purple'],
    'RC'   => ['label' => 'Repos (Weekend)', 'badge' => 'bg-primary text-dark border'],
    'JF'   => ['label' => 'Jour Férié', 'badge' => 'bg-primary text-dark border'],
    'ANJ'  => ['label' => 'Absent non justifié', 'badge' => 'bg-danger'],
    'AAP'  => ['label' => 'Absent autorisé payé', 'badge' => 'bg-warning text-dark'],
    'AANP' => ['label' => 'Absent autorisé non payé', 'badge' => 'bg-secondary'],
    'MT'   => ['label' => 'Maternité', 'badge' => 'bg-pink text-dark'],
    'F'    => ['label' => 'Formation', 'badge' => 'bg-teal'],
    'MS'   => ['label' => 'Mission', 'badge' => 'bg-orange'],
    'X'    => ['label' => 'Autre absence', 'badge' => 'bg-indigo'],
];


// --- Chargement de la vue ---
include __DIR__ . '/../../views/history/view.php';