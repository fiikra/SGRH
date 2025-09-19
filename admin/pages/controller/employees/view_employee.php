<?php
// /admin/pages/controller/employees/view_employee.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Security and Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}
redirectIfNotHR(); // Ensure only admin access

// --- Include the Model ---
require_once __DIR__ . '../../../model/employees/EmployeeModel.php';

// --- Input Validation ---
if (!isset($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}
$nin = sanitize($_GET['nin']);

// --- Instantiate Model and Fetch Core Data ---
$employeeModel = new EmployeeModel($db);
$employee = $employeeModel->getEmployeeByNin($nin);

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé avec le NIN: " . htmlspecialchars($nin);
    header("Location: " . route('employees_list'));
    exit();
}

// --- Prepare Data for the View ---
$pageTitle = "Profil de " . htmlspecialchars($employee['first_name']) . " " . htmlspecialchars($employee['last_name']);

// Navigation
$nav_data = $employeeModel->getNavigationData($nin);
$prev_employee = $nav_data && $nav_data['prev_nin'] ? ['nin' => $nav_data['prev_nin'], 'name' => $nav_data['prev_name']] : null;
$next_employee = $nav_data && $nav_data['next_nin'] ? ['nin' => $nav_data['next_nin'], 'name' => $nav_data['next_name']] : null;


// Balances
$current_recup_balance = $employeeModel->getRecuperationBalance($nin);

// Fetch data for tabs
$documents_stmt = $employeeModel->getEmployeeDocuments($nin);
$sanctions_stmt = $employeeModel->getSanctions($nin);
$questionnaires_stmt = $employeeModel->getQuestionnaires($nin);
$available_questionnaires_stmt = $employeeModel->getAvailableQuestionnairesForSanction($nin);
$formations_history = $employeeModel->getFormationsHistory($nin);
$certificates_stmt = $employeeModel->getRecentCertificates($nin);
$decisions_history = $employeeModel->getCareerDecisions($nin);
$position_history = $employeeModel->getPositionHistory($nin);
$salary_history = $employeeModel->getSalaryHistory($nin);
$notifications_stmt = $employeeModel->getTrialNotifications($nin);

// Leave data
$leave_types_to_fetch = ['annuel', 'reliquat', 'recup', 'anticipe', 'unpaid', 'special_mariage', 'special_naissance', 'special_deces', 'special_mariage_enf', 'special_circoncision'];
$leaves_stmt = $employeeModel->getLeaveRequests($nin, $leave_types_to_fetch);
$sick_leaves_stmt = $employeeModel->getSickLeaveRequests($nin);
if ($employee['gender'] === 'female') {
    $maternity_leaves_stmt = $employeeModel->getMaternityLeaveRequests($nin);
}

// --- Attendance Logic (Business Logic) ---
$attendance_filter_month_str = sanitize($_GET['att_month'] ?? date('Y-m'));
$min_max_months = $employeeModel->getAttendanceMonthRange($nin);
$earliest_attendance_month = $min_max_months['earliest_month'];
$latest_attendance_month = $min_max_months['latest_month'];

if (!empty($earliest_attendance_month) && !empty($latest_attendance_month)) {
    if ($attendance_filter_month_str < $earliest_attendance_month) {
        $attendance_filter_month_str = $earliest_attendance_month;
    }
    $current_real_month_str = date('Y-m');
    if ($attendance_filter_month_str > $latest_attendance_month && $attendance_filter_month_str > $current_real_month_str) {
           $attendance_filter_month_str = $latest_attendance_month;
    }
} else {
    $attendance_filter_month_str = date('Y-m');
}

$current_filter_year = (int)substr($attendance_filter_month_str, 0, 4);
$current_filter_month_num = (int)substr($attendance_filter_month_str, 5, 2);

$current_dt = DateTime::createFromFormat('Y-m', $attendance_filter_month_str);
if (!$current_dt) { $current_dt = new DateTime(date('Y-m-01', strtotime($attendance_filter_month_str))); }
$previous_month_dt = (clone $current_dt)->modify('-1 month');
$next_month_dt = (clone $current_dt)->modify('+1 month');

$previous_month_link = route('employees_view', ['nin' => $nin, 'att_month' => $previous_month_dt->format('Y-m')]) . '#attendance';
$next_month_link = route('employees_view', ['nin' => $nin, 'att_month' => $next_month_dt->format('Y-m')]) . '#attendance';

$disable_prev = false;
if (empty($earliest_attendance_month) || $attendance_filter_month_str <= $earliest_attendance_month) {
    $disable_prev = true;
}
$disable_next = false;
$current_real_month_comparison = date('Y-m');
if ($attendance_filter_month_str >= $current_real_month_comparison && (empty($latest_attendance_month) || $attendance_filter_month_str >= $latest_attendance_month)) {
    $disable_next = true;
}

$attendance_records_for_month = $employeeModel->getAttendanceRecords($nin, $attendance_filter_month_str);

// Attendance Summary Calculation
$total_worked_days = 0; $total_annual_leave = 0; $total_sick_leave = 0; $total_maternity_leave = 0;
$total_training_leave = 0; $total_mission_leave = 0; $total_other_leave = 0; $total_absent_justified_paid = 0;
$total_absent_justified_unpaid = 0; $total_absent_unjustified = 0; $total_tf_days = 0; $total_tw_days = 0;

foreach ($attendance_records_for_month as $att_rec_summary) {
    $status_lc_summary = strtolower($att_rec_summary['status'] ?? '');
    switch ($status_lc_summary) {
        case 'present': $total_worked_days++; break;
        case 'present_offday': $total_tf_days++; break; 
        case 'present_weekend': $total_tw_days++; break;
        case 'annual_leave': case 'on_leave_from_excel_c': $total_annual_leave++; break;
        case 'sick_leave': case 'maladie': case 'on_leave_from_excel_m': $total_sick_leave++; break;
        case 'maternity_leave': case 'on_leave_from_excel_mt': $total_maternity_leave++; break;
        case 'training': case 'on_leave_from_excel_f': $total_training_leave++; break;
        case 'mission': case 'on_leave_from_excel_ms': $total_mission_leave++; break;
        case 'other_leave': case 'on_leave_from_excel_x': $total_other_leave++; break;
        case 'absent_authorized_paid': case 'absent_from_excel_aap': $total_absent_justified_paid++; break;
        case 'absent_authorized_unpaid': case 'absent_from_excel_aanp': $total_absent_justified_unpaid++; break;
        case 'absent_unjustified': case 'absent_from_excel_anj': case 'absent_from_excel': $total_absent_unjustified++; break;
    }
}
$monthly_financial_data = $employeeModel->getMonthlyFinancialSummary($nin, $current_filter_year, $current_filter_month_num);
$monthly_hs_total_for_display = $monthly_financial_data['total_hs_hours'] ?? 0.00;
$monthly_retenue_total_for_display = $monthly_financial_data['total_retenu_hours'] ?? 0.00;

// Data for modals and dropdowns
$company = $employeeModel->getCompanySettings();
$positions_list = $employeeModel->getPositionsList();
$departments_list = $employeeModel->getDepartmentsList();
$typesContratSettingsRaw = $employeeModel->getContractTypes();


// --- Configuration data for the view ---
$detailed_leave_types_view = [
    'annuel'               => ['label' => 'Annuel', 'max_days' => null, 'has_sold' => true],
    'reliquat'             => ['label' => 'Reliquat', 'max_days' => 30, 'has_sold' => true],
    'recup'                => ['label' => 'Récupération', 'max_days' => null, 'has_sold' => true],
    'special_mariage'      => ['label' => 'Spécial - Mariage (3 jours)', 'max_days' => 3, 'has_sold' => false],
    'special_naissance'    => ['label' => 'Spécial - Naissance (3 jours)', 'max_days' => 3, 'has_sold' => false],
    'special_deces'        => ['label' => 'Spécial - Décès (3 jours)', 'max_days' => 3, 'has_sold' => false],
    'special_mariage_enf'  => ['label' => 'Spécial - Mariage Enfant (1 jour)', 'max_days' => 1, 'has_sold' => false],
    'special_circoncision' => ['label' => 'Spécial - Circoncision (1 jour)', 'max_days' => 1, 'has_sold' => false],
    'anticipe'             => ['label' => 'Anticipé', 'max_days' => 30, 'has_sold' => true],
    'unpaid'               => ['label' => 'Sans Solde', 'max_days' => 365, 'has_sold' => false],
];
$default_questions = [
    'Entretien préalable à une sanction' => [
        "Pouvez-vous décrire les faits qui vous sont reprochés ?",
        "Avez-vous des éléments ou une explication à fournir concernant ces faits ?",
        "Y a-t-il des circonstances particulières que nous devrions prendre en compte avant toute décision ?"
    ],
    'Evaluation de performance' => [
        "Quelles ont été vos plus grandes réussites au cours de la dernière période d'évaluation ?",
        "Quels sont les défis que vous avez rencontrés et comment les avez-vous surmontés ?",
        "Quels sont vos objectifs de développement professionnel pour la période à venir ?"
    ],
    'Entretien Annuel' => [
        "Comment évaluez-vous votre satisfaction générale concernant votre poste et vos missions ?",
        "Quels sont vos souhaits en matière de formation ou d'évolution de carrière au sein de l'entreprise ?",
        "Avez-vous des suggestions pour améliorer le fonctionnement de l'équipe ou du département ?"
    ],
    'Autre' => ["", "", ""]
];
$pdf_to_show = $_GET['showpdf'] ?? 0;
$pdf_url = $_GET['pdf'] ?? '';

// --- Load the View ---
include __DIR__ . '/../../views/employees/view.php';