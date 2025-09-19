<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

require_once __DIR__ . '../../../model/attendance/ManageAttendanceModel.php';
$model = new ManageAttendanceModel($db);

// --- Préparation des filtres ---
$filterYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('Y'), 'min_range' => 2000, 'max_range' => 2050]]);
$filterMonthNum = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['default' => (int)date('m'), 'min_range' => 1, 'max_range' => 12]]);
$filterEmployeeNin = sanitize($_GET['employee_nin'] ?? '');

// --- Récupération des données via le modèle ---
$attendanceRecords = $model->getFilteredAttendanceRecords($filterYear, $filterMonthNum, $filterEmployeeNin);
$monthlySummaries = $model->getMonthlyFinancialSummaries($filterYear, $filterMonthNum);
$employeesList = $model->getActiveEmployees();

// --- Logique de présentation (préparation des variables pour la vue) ---
$pageTitle = "Gestion de la Présence";

$monthNameDisplay = "Mois";
try {
    $dateObj = DateTime::createFromFormat('!m', $filterMonthNum);
    if ($dateObj) {
        $monthNameDisplay = class_exists('IntlDateFormatter')
            ? ucfirst((new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM'))->format($dateObj))
            : $dateObj->format('F');
    }
} catch(Exception $e) {}

// Définition de la map des codes de présence pour la légende dans la vue
$attendanceCodeMap = [
    'P'    => ['label' => 'Présent', 'badge' => 'bg-success'],
    'Travaille feries'   => ['label' => 'Présent Jour Férié (TF)', 'badge' => 'bg-danger'],
    'travaille weekend'   => ['label' => 'Présent Weekend (TW)', 'badge' => 'bg-orange'],
    'Conges'    => ['label' => 'Congé Annuel', 'badge' => 'bg-info text-dark'],
    'Maladie'    => ['label' => 'Maladie', 'badge' => 'bg-purple'],
    'weekend'   => ['label' => 'Repos (Weekend)', 'badge' => 'bg-primary text-dark border'],
    'Jour Feries'   => ['label' => 'Jour Férié', 'badge' => 'bg-primary text-dark border'],
    'Absence non justifier'  => ['label' => 'Absent non justifié', 'badge' => 'bg-danger'],
    'Absence autorise paye'  => ['label' => 'Absent autorisé payé', 'badge' => 'bg-warning text-dark'],
    'Absence autorise non payé' => ['label' => 'Absent autorisé non payé', 'badge' => 'bg-secondary'],
    'Maternite'   => ['label' => 'Maternité', 'badge' => 'bg-pink text-dark'],
    'Formation'    => ['label' => 'Formation', 'badge' => 'bg-teal'],
    'Mission'   => ['label' => 'Mission', 'badge' => 'bg-orange'],
    'autre'    => ['label' => 'Autre absence', 'badge' => 'bg-indigo'],
];


// --- Chargement de la vue ---
include __DIR__ . '/../../views/manage_attendance/view.php';