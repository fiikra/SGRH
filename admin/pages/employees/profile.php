<?php

// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Security Headers: Set before any output ---

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}


redirectIfNotHR(); // Ensure only admin access


if (!isset($_GET['nin'])) {
    // Redirect if no NIN is provided
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);
$stmt_employee = $db->prepare("SELECT *, DATEDIFF(trial_end_date, CURDATE()) as trial_days_left FROM employees WHERE nin = ?");
$stmt_employee->execute([$nin]);
$employee = $stmt_employee->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé avec le NIN: " . htmlspecialchars($nin);
    // Redirect if employee not found
    header("Location: " . route('employees_list'));
    exit();
}

// --- [IMPROVEMENT] Efficient Previous/Next Employee Logic ---
$nav_sql = "
    WITH RankedEmployees AS (
        SELECT
            nin,
            first_name,
            last_name,
            LAG(nin) OVER (ORDER BY last_name, first_name) as prev_nin,
            LAG(CONCAT(first_name, ' ', last_name)) OVER (ORDER BY last_name, first_name) as prev_name,
            LEAD(nin) OVER (ORDER BY last_name, first_name) as next_nin,
            LEAD(CONCAT(first_name, ' ', last_name)) OVER (ORDER BY last_name, first_name) as next_name
        FROM
            employees
    )
    SELECT prev_nin, prev_name, next_nin, next_name
    FROM RankedEmployees
    WHERE nin = ?
";
$stmt_nav = $db->prepare($nav_sql);
$stmt_nav->execute([$nin]);
$nav_data = $stmt_nav->fetch(PDO::FETCH_ASSOC);

$prev_employee = $nav_data && $nav_data['prev_nin'] ? ['nin' => $nav_data['prev_nin'], 'name' => $nav_data['prev_name']] : null;
$next_employee = $nav_data && $nav_data['next_nin'] ? ['nin' => $nav_data['next_nin'], 'name' => $nav_data['next_name']] : null;
// --- End of new logic ---


// Calculate current available recuperation balance
$recup_balance_stmt = $db->prepare(
    "SELECT SUM(nb_jours) as current_recup_balance
     FROM employee_recup_days
     WHERE employee_nin = ? AND status = 'not_taked'"
);
$recup_balance_stmt->execute([$nin]);
$recup_data = $recup_balance_stmt->fetch(PDO::FETCH_ASSOC);
$current_recup_balance = $recup_data['current_recup_balance'] ?? 0;

// Récupérer les documents de l'employé
$documents_stmt = $db->prepare("SELECT * FROM employee_documents WHERE employee_nin = ? ORDER BY upload_date DESC");
$documents_stmt->execute([$nin]);

// --- Mettre à jour la liste des types de congés à récupérer ---
$leave_types_to_fetch = [
    'annuel', 'reliquat', 'recup', 'anticipe', 'unpaid',
    'special_mariage', 'special_naissance', 'special_deces',
    'special_mariage_enf', 'special_circoncision'
];
$placeholders = implode(',', array_fill(0, count($leave_types_to_fetch), '?'));
$leaves_sql = "SELECT * FROM leave_requests WHERE employee_nin = ? AND leave_type IN ($placeholders) ORDER BY start_date DESC";
$leaves_stmt = $db->prepare($leaves_sql);
$leaves_stmt->execute(array_merge([$nin], $leave_types_to_fetch));

// Récupérer les congés de maladie
$sick_leaves_stmt = $db->prepare("SELECT * FROM leave_requests WHERE employee_nin = ? AND leave_type IN ('sick', 'maladie') ORDER BY start_date DESC");
$sick_leaves_stmt->execute([$nin]);

// Si l'employée est une femme, récupérer les congés de maternité
if ($employee['gender'] === 'female') {
    $maternity_leaves_stmt = $db->prepare("SELECT * FROM leave_requests WHERE employee_nin = ? AND leave_type = 'maternity' ORDER BY start_date DESC");
    $maternity_leaves_stmt->execute([$nin]);
}

// Récupérer les sanctions de l'employé
$sanctions_stmt = $db->prepare("SELECT s.*, q.reference_number as questionnaire_ref FROM employee_sanctions s LEFT JOIN employee_questionnaires q ON s.questionnaire_id = q.id WHERE s.employee_nin = ? ORDER BY s.sanction_date DESC");
$sanctions_stmt->execute([$nin]);

// Récupérer les questionnaires de l'employé
$questionnaires_stmt = $db->prepare("SELECT * FROM employee_questionnaires WHERE employee_nin = ? ORDER BY issue_date DESC");
$questionnaires_stmt->execute([$nin]);

// Fetch only questionnaires available to be linked to a sanction
$available_questionnaires_stmt = $db->prepare("
    SELECT q.id, q.reference_number, q.issue_date, q.questionnaire_type
    FROM employee_questionnaires q
    LEFT JOIN employee_sanctions s ON q.id = s.questionnaire_id
    WHERE q.employee_nin = ? AND s.id IS NULL AND q.status = 'closed' AND q.questionnaire_type= 'Entretien préalable à une sanction'
    ORDER BY q.issue_date DESC
");
$available_questionnaires_stmt->execute([$nin]);


// START: NEW QUERY FOR FORMATIONS
// Fetch formations history for the employee
$formations_stmt = $db->prepare("
    SELECT f.id, f.title, f.trainer_name, f.start_date, f.end_date, f.status AS formation_status, fp.status AS participant_status
    FROM formation_participants fp
    JOIN formations f ON fp.formation_id = f.id
    WHERE fp.employee_nin = ?
    ORDER BY f.start_date DESC
");
$formations_stmt->execute([$nin]);
$formations_history = $formations_stmt->fetchAll(PDO::FETCH_ASSOC);
// END: NEW QUERY FOR FORMATIONS
// Récupérer les 5 derniers certificats de l'employé
$certificates_stmt = $db->prepare("SELECT c.*, u.username as prepared_by
                                     FROM certificates c
                                     LEFT JOIN users u ON c.prepared_by = u.id
                                     WHERE c.employee_nin = ? AND c.certificate_type IN ('Attestation', 'Attestation_sold','Certficate')
                                     ORDER BY c.issue_date DESC
                                     LIMIT 5");
$certificates_stmt->execute([$nin]);

// Récupérer les informations de l'entreprise
$company_stmt = $db->query("SELECT company_name, logo_path FROM company_settings LIMIT 1");
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

// --- ATTENDANCE DATA AND SUMMARY CALCULATION ---
$attendance_filter_month_str = sanitize($_GET['att_month'] ?? date('Y-m'));
$current_filter_year = (int)substr($attendance_filter_month_str, 0, 4);
$current_filter_month_num = (int)substr($attendance_filter_month_str, 5, 2);

$min_max_months_sql = "SELECT MIN(DATE_FORMAT(attendance_date, '%Y-%m')) AS earliest_month,
                              MAX(DATE_FORMAT(attendance_date, '%Y-%m')) AS latest_month
                         FROM employee_attendance
                         WHERE employee_nin = :nin";
$min_max_months_stmt = $db->prepare($min_max_months_sql);
$min_max_months_stmt->execute([':nin' => $nin]);
$min_max_months = $min_max_months_stmt->fetch(PDO::FETCH_ASSOC);

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

// Update year and month numbers if $attendance_filter_month_str was changed
$current_filter_year = (int)substr($attendance_filter_month_str, 0, 4);
$current_filter_month_num = (int)substr($attendance_filter_month_str, 5, 2);


$current_dt = DateTime::createFromFormat('Y-m', $attendance_filter_month_str);
if (!$current_dt) { 
    $current_dt = new DateTime(date('Y-m-01', strtotime($attendance_filter_month_str)));
}
$previous_month_dt = (clone $current_dt)->modify('-1 month');
$next_month_dt = (clone $current_dt)->modify('+1 month');

// Regenerated URLs using the route() function
$previous_month_link = route('employees_view', ['nin' => $nin, 'att_month' => $previous_month_dt->format('Y-m')]) . '#attendance';
$next_month_link = route('employees_view', ['nin' => $nin, 'att_month' => $next_month_dt->format('Y-m')]) . '#attendance';


$disable_prev = false;
if (empty($earliest_attendance_month) || $attendance_filter_month_str <= $earliest_attendance_month) {
    $disable_prev = true;
}

$disable_next = false;
$current_real_month_comparison = date('Y-m');
// Allow navigating to current real month even if no latest attendance data, but not beyond.
if ($attendance_filter_month_str >= $current_real_month_comparison) {
    if (empty($latest_attendance_month) || $attendance_filter_month_str >= $latest_attendance_month) {
        $disable_next = true;
    }
}


$attendance_sql = "SELECT attendance_date, status, notes, leave_type_if_absent, is_weekend_work, is_holiday_work,
                               check_in_time, check_out_time, effective_work_hours, overtime_hours_recorded
                         FROM employee_attendance
                         WHERE employee_nin = :nin AND DATE_FORMAT(attendance_date, '%Y-%m') = :month
                         ORDER BY attendance_date ASC";
$attendance_stmt = $db->prepare($attendance_sql);
$attendance_stmt->execute([':nin' => $nin, ':month' => $attendance_filter_month_str]);
$attendance_records_for_month = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- START: ATTENDANCE SUMMARY CALCULATION (Daily statuses) ---
$total_worked_days = 0;
$total_annual_leave = 0;
$total_sick_leave = 0;
$total_maternity_leave = 0;
$total_training_leave = 0;
$total_mission_leave = 0;
$total_other_leave = 0;
$total_absent_justified_paid = 0;
$total_absent_justified_unpaid = 0;
$total_absent_unjustified = 0;
$total_tf_days = 0; 
$total_tw_days = 0;

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

// MODIFICATION: Fetch Monthly HS and Retenu from employee_monthly_financial_summary
$monthly_financial_stmt = $db->prepare(
    "SELECT total_hs_hours, total_retenu_hours 
     FROM employee_monthly_financial_summary 
     WHERE employee_nin = :nin 
     AND period_year = :year 
     AND period_month = :month"
);
$monthly_financial_stmt->execute([
    ':nin' => $nin,
    ':year' => $current_filter_year,
    ':month' => $current_filter_month_num
]);
$monthly_financial_data = $monthly_financial_stmt->fetch(PDO::FETCH_ASSOC);

$monthly_hs_total_for_display = $monthly_financial_data['total_hs_hours'] ?? 0.00;
$monthly_retenue_total_for_display = $monthly_financial_data['total_retenu_hours'] ?? 0.00;
// END MODIFICATION

// --- END: ATTENDANCE SUMMARY CALCULATION ---

// Define detailed leave types for UI logic
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

// Fetch position and salary history
$stmt_pos_history = $db->prepare("SELECT * FROM employee_position_history WHERE employee_nin = ? ORDER BY start_date DESC");
$stmt_pos_history->execute([$nin]);
$position_history = $stmt_pos_history->fetchAll(PDO::FETCH_ASSOC);

$stmt_sal_history = $db->prepare("SELECT * FROM employee_salary_history WHERE employee_nin = ? ORDER BY effective_date DESC");
$stmt_sal_history->execute([$nin]);
$salary_history = $stmt_sal_history->fetchAll(PDO::FETCH_ASSOC);

// Fetch positions and departments for dropdowns
$positions_list = $db->query("SELECT nom FROM postes")->fetchAll(PDO::FETCH_ASSOC);
$departments_list = $db->query("SELECT name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

// --- Récupérer les décisions de carrière pour l'onglet fusionné ---
$decisions_stmt = $db->prepare("SELECT * FROM promotion_decisions WHERE employee_nin = ? ORDER BY effective_date DESC");
$decisions_stmt->execute([$nin]);
$decisions_history = $decisions_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Data for Dynamic Questionnaire Modal ---
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
    'Autre' => ["", "", ""] // Provides 3 empty fields for the "Other" case
];

$pageTitle = "Profil de " . htmlspecialchars($employee['first_name']) . " " . htmlspecialchars($employee['last_name']);
include __DIR__. '../../../../includes/header.php';

// Load all needed employee data here (as in your original view.php)
include_once __DIR__ . '/tabs/info.php';
include_once __DIR__ . '/tabs/documents.php';
include_once __DIR__ . '/tabs/formations.php';
include_once __DIR__ . '/tabs/sanctions.php';
include_once __DIR__ . '/tabs/questionnaires.php';
include_once __DIR__ . '/tabs/leaves.php';
include_once __DIR__ . '/tabs/sick_leaves.php';
include_once __DIR__ . '/tabs/maternity_leaves.php';
include_once __DIR__ . '/tabs/certificates.php';
include_once __DIR__ . '/tabs/attendance.php';
include_once __DIR__ . '/tabs/career_decisions.php';
include_once __DIR__ . '/tabs/notifications.php';

// Modals (at end of page)
include_once __DIR__ . '/modals/departure.php';
include_once __DIR__ . '/modals/sanction.php';
include_once __DIR__ . '/modals/questionnaire.php';
include_once __DIR__ . '/modals/promotion.php';
include_once __DIR__ . '/modals/voucher.php';
include_once __DIR__ . '/modals/pdf_preview.php';
include_once __DIR__ . '/modals/leave.php';

?>
<link rel="stylesheet" href="/assets/css/employees/profile/talenteo.css">
<script src="/assets/js/employees/profile/profile.js"></script>
<script src="/assets/js/employees/profile/tabs.js"></script>
<script src="/assets/js/employees/profile/modals.js"></script>
<?php require_once __DIR__ . '../../../../includes/footer.php'; ?>