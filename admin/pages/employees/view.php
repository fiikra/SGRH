<?php
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
    'annuel',
    'reliquat',
    'recup',
    'anticipe',
    'unpaid',
    'special_mariage',
    'special_naissance',
    'special_deces',
    'special_mariage_enf',
    'special_circoncision'
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
        case 'present':
            $total_worked_days++;
            break;
        case 'present_offday':
            $total_tf_days++;
            break;
        case 'present_weekend':
            $total_tw_days++;
            break;
        case 'annual_leave':
        case 'on_leave_from_excel_c':
            $total_annual_leave++;
            break;
        case 'sick_leave':
        case 'maladie':
        case 'on_leave_from_excel_m':
            $total_sick_leave++;
            break;
        case 'maternity_leave':
        case 'on_leave_from_excel_mt':
            $total_maternity_leave++;
            break;
        case 'training':
        case 'on_leave_from_excel_f':
            $total_training_leave++;
            break;
        case 'mission':
        case 'on_leave_from_excel_ms':
            $total_mission_leave++;
            break;
        case 'other_leave':
        case 'on_leave_from_excel_x':
            $total_other_leave++;
            break;
        case 'absent_authorized_paid':
        case 'absent_from_excel_aap':
            $total_absent_justified_paid++;
            break;
        case 'absent_authorized_unpaid':
        case 'absent_from_excel_aanp':
            $total_absent_justified_unpaid++;
            break;
        case 'absent_unjustified':
        case 'absent_from_excel_anj':
        case 'absent_from_excel':
            $total_absent_unjustified++;
            break;
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
include __DIR__ . '../../../../includes/header.php';
?>


<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <?php if ($prev_employee): ?>
                <a href="<?= route('employees_view', ['nin' => $prev_employee['nin']]) ?>" class="btn btn-outline-secondary" title="Précédent: <?= htmlspecialchars($prev_employee['name']) ?>">
                    <i class="bi bi-arrow-left-circle"></i>
                    <span class="d-none d-md-inline">Précédent</span>
                </a>
            <?php endif; ?>
        </div>

        <h1 class="text-center mx-3"><?= $pageTitle ?></h1>

        <div>
            <?php if ($next_employee): ?>
                <a href="<?= route('employees_view', ['nin' => $next_employee['nin']]) ?>" class="btn btn-outline-secondary" title="Suivant: <?= htmlspecialchars($next_employee['name']) ?>">
                    <span class="d-none d-md-inline">Suivant</span>
                    <i class="bi bi-arrow-right-circle"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex justify-content-end align-items-center mb-4">
        <a href="<?= route('employees_list') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour à la liste
        </a>
        <a href="<?= route('employees_edit', ['nin' => $nin]) ?>" class="btn btn-primary ms-2">
            <i class="bi bi-pencil"></i> Modifier le profil
        </a>
    </div>


    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>
                        <?php if (!empty($employee['photo_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $employee['photo_path'])): ?>
                            <img src="/<?= htmlspecialchars($employee['photo_path']) ?>" class="rounded-circle mb-3" width="150" height="150" alt="Photo de l'employé" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 150px; height: 150px;">
                                <i class="bi bi-person" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="text-center">
                            <h3><?= htmlspecialchars($employee['first_name']) ?> <?= htmlspecialchars($employee['last_name']) ?></h3>
                            <h5 class="text-muted"><?= htmlspecialchars($employee['position']) ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($employee['department']) ?></p>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <button class="btn btn-sm btn-info" onclick="window.open('<?= route('employees_generate_pdf', ['nin' => $nin]) ?>', '_blank')"><i class="bi bi-file-earmark-pdf"></i> Générer Fiche Employé</button>
                            <button class="btn btn-sm btn-info" onclick="window.open('<?= route('employees_badge', ['nin' => $nin]) ?>', '_blank')"><i class="bi bi-credit-card-2-front"></i> Générer Badge</button>
                        </div>
                        <div class="d-grid gap-2">
                            <?php if (empty($employee['user_id'])): ?>
                                <button class="btn btn-sm btn-success" onclick="generateQuickAccess('<?= route('users_quick_create', ['nin' => $nin]) ?>', '<?= route('users_view') ?>', event)"><i class="bi bi-person-plus"></i> Créer Accès Rapide</button>
                            <?php else: ?>
                                <a href="<?= route('users_view', ['id' => $employee['user_id']]) ?>" class="btn btn-sm btn-secondary"><i class="bi bi-person-check"></i> Voir Compte Utilisateur</a>
                            <?php endif; ?>
                        </div>
                    </h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($employee['on_trial'] == 1): ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-stopwatch"></i>
                            La période d'essai se termine le <strong><?= formatDate($employee['trial_end_date']) ?></strong>
                            (dans <?= $employee['trial_days_left'] ?> jours).
                        </div>
                        <div class="mb-3">
                            <?php if ($employee['on_trial'] == 1): ?>
                                <span class="badge bg-warning text-dark">Période d'essai</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#trialDecisionModal">
                                <i class="bi bi-gavel"></i> Décision Période d'Essai
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="bi bi-briefcase me-2"></i>Statut Professionnel</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span><i class="bi bi-calendar-event me-2"></i>Date d'embauche:</span> <strong><?= formatDate($employee['hire_date']) ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="bi bi-file-earmark-text me-2"></i>Type de contrat:</span>
                            <strong>
                                <?php
                                $typesContratSettingsRaw = $db->query("SELECT types_contrat FROM personnel_settings LIMIT 1")->fetchColumn();
                                $typesContratSettings = parse_json_field($typesContratSettingsRaw);
                                $displayContract = htmlspecialchars($employee['contract_type'] ?? 'Non spécifié');
                                if (!empty($typesContratSettings) && is_array($typesContratSettings)) {
                                    if (in_array($employee['contract_type'], $typesContratSettings)) {
                                        $displayContract = htmlspecialchars($employee['contract_type']);
                                    } else {
                                        $legacyContractTypes = ['cdi' => 'CDI', 'cdd' => 'CDD', 'stage' => 'Stage', 'interim' => 'Intérim', 'essai' => 'Essai'];
                                        $displayContract = htmlspecialchars($legacyContractTypes[$employee['contract_type']] ?? $employee['contract_type'] ?? 'Non spécifié');
                                    }
                                } else {
                                    $legacyContractTypes = ['cdi' => 'CDI', 'cdd' => 'CDD', 'stage' => 'Stage', 'interim' => 'Intérim', 'essai' => 'Essai'];
                                    $displayContract = htmlspecialchars($legacyContractTypes[$employee['contract_type']] ?? $employee['contract_type'] ?? 'Non spécifié');
                                }
                                echo $displayContract;
                                ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="bi bi-calendar-x me-2"></i>Date de fin de contrat:</span>
                            <strong>
                                <?php if (!empty($employee['end_date'])): ?>
                                    <?= formatDate($employee['end_date']) ?>
                                    <?php
                                    try {
                                        $endDate = new DateTime($employee['end_date']);
                                        $today = new DateTime();
                                        if ($endDate > $today) {
                                            $interval = $today->diff($endDate);
                                            echo ' <span class="badge bg-success ms-1"><i class="bi bi-clock"></i> J-' . $interval->days . '</span>';
                                        } else {
                                            echo ' <span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i> Expiré</span>';
                                        }
                                    } catch (Exception $e) { /* Ignore date error */
                                    }
                                    ?>
                                <?php else: ?> N/A <?php endif; ?>
                            </strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi bi-activity me-2"></i>Statut Employé:</span>
                            <span class="badge rounded-pill bg-<?= $employee['status'] === 'active' ? 'success' : ($employee['status'] === 'inactive' ? 'secondary' : ($employee['status'] === 'suspended' ? 'warning text-dark' : ($employee['status'] === 'cancelled' ? 'danger' : 'dark'))) ?>">
                                <i class="bi bi-<?= $employee['status'] === 'active' ? 'check-circle' : ($employee['status'] === 'inactive' ? 'pause-circle' : ($employee['status'] === 'suspended' ? 'exclamation-circle' : ($employee['status'] === 'cancelled' ? 'x-octagon' : 'question-circle'))) ?> me-1"></i>
                                <?= ucfirst(htmlspecialchars($employee['status'])) ?>
                            </span>
                        </li>
                        <?php if ($employee['status'] === 'inactive' || $employee['status'] === 'cancelled'): ?>
                            <li class="list-group-item list-group-item-danger d-flex justify-content-between"><span><i class="bi bi-box-arrow-left me-2"></i>Date de sortie:</span> <strong><?= formatDate($employee['departure_date']) ?></strong></li>
                            <li class="list-group-item list-group-item-danger d-flex justify-content-between"><span><i class="bi bi-info-circle me-2"></i>Motif de sortie:</span> <strong><?= htmlspecialchars($employee['departure_reason']) ?></strong></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-person-rolodex me-2"></i>Coordonnées</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item"><i class="bi bi-envelope me-2"></i> <?= htmlspecialchars($employee['email']) ?></li>
                        <li class="list-group-item"><i class="bi bi-telephone me-2"></i> <?= htmlspecialchars($employee['phone']) ?></li>
                        <li class="list-group-item"><i class="bi bi-house-door me-2"></i> <?= htmlspecialchars($employee['address'] . ($employee['postal_code'] ? ', ' . $employee['postal_code'] : '') . ($employee['city'] ? ' ' . $employee['city'] : '')) ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" data-bs-target="#info" href="#info">Informations</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" href="#documents">Documents</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#formations" href="#formations">Formations</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#sanctions" href="#sanctions">Sanctions</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#questionnaires" href="#questionnaires">Questionnaires</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#leaves" href="#leaves">Congés</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#sick-leaves" href="#sick-leaves">Maladies</a></li>
                        <?php if ($employee['gender'] === 'female'): ?>
                            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#maternity-leaves" href="#maternity-leaves">Maternité</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#certificates" href="#certificates">Certificats</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#attendance" href="#attendance">Présence</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#departure" href="#departure"><i class="bi bi-door-closed-fill"></i> Départ</a></li>
                        <li class="nav-item"><a class="nav-link" id="career-tab-link" data-bs-toggle="tab" href="#career-and-decisions">Carrière & Décisions</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" data-bs-target="#notifications" href="#notifications"><i class="bi bi-bell"></i> Notifications</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="info">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="bi bi-person-vcard me-2"></i>Informations Personnelles</h5>
                                    <dl class="row small">
                                        <dt class="col-sm-5">NIN:</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars($employee['nin']) ?></dd>
                                        <dt class="col-sm-5">N° Sécurité Sociale (NSS):</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars($employee['nss'] ?? 'N/A') ?></dd>
                                        <dt class="col-sm-5">Date de Naissance:</dt>
                                        <dd class="col-sm-7"><?= formatDate($employee['birth_date']) ?> (Lieu: <?= htmlspecialchars($employee['birth_place'] ?? 'N/A') ?>)</dd>
                                        <dt class="col-sm-5">Genre:</dt>
                                        <dd class="col-sm-7"><?= $employee['gender'] === 'male' ? 'Masculin' : ($employee['gender'] === 'female' ? 'Féminin' : 'Autre') ?></dd>
                                        <dt class="col-sm-5">Sit. Familiale:</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars(ucfirst($employee['marital_status'] ?? 'N/A')) ?></dd>
                                        <dt class="col-sm-5">Pers. à charge:</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars($employee['dependents'] ?? '0') ?></dd>
                                    </dl>
                                    <?php if (!empty($employee['emergency_contact'])): ?>
                                        <h5 class="mt-3"><i class="bi bi-telephone-outbound me-2"></i>Contact d'Urgence</h5>
                                        <p class="small">Nom: <?= htmlspecialchars($employee['emergency_contact']) ?> <br>Téléphone: <?= htmlspecialchars($employee['emergency_phone']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="bi bi-building me-2"></i>Informations Professionnelles</h5>
                                    <dl class="row small">
                                        <dt class="col-sm-5">Poste Actuel:</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars($employee['position']) ?></dd>
                                        <dt class="col-sm-5">Département:</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars($employee['department']) ?></dd>
                                        <dt class="col-sm-5">Salaire Brut Mensuel:</dt>
                                        <dd class="col-sm-7"><?= number_format($employee['salary'], 2, ',', ' ') ?> DZD</dd>
                                        <dt class="col-sm-5">Solde Congé Annuel (N):</dt>
                                        <dd class="col-sm-7"><?= number_format($employee['annual_leave_balance'], 1) ?> jour(s)</dd>
                                        <?php if (isset($employee['remaining_leave_balance']) && $employee['remaining_leave_balance'] > 0): ?>
                                            <dt class="col-sm-5 text-muted">Reliquat Congé (N-1):</dt>
                                            <dd class="col-sm-7 text-muted"><?= number_format($employee['remaining_leave_balance'], 1) ?> jour(s)</dd>
                                        <?php endif; ?>
                                        <?php if (isset($current_recup_balance) && $current_recup_balance > 0): ?>
                                            <dt class="col-sm-5">Solde Récupération Actuel:</dt>
                                            <dd class="col-sm-7"><?= number_format($current_recup_balance, 0) ?> jour(s)</dd>
                                        <?php endif; ?>
                                    </dl>
                                    <?php if (!empty($employee['bank_name'])): ?>
                                        <h5 class="mt-3"><i class="bi bi-bank me-2"></i>Coordonnées Bancaires</h5>
                                        <dl class="row small">
                                            <dt class="col-sm-5">Nom de la Banque:</dt>
                                            <dd class="col-sm-7"><?= htmlspecialchars($employee['bank_name']) ?></dd>
                                            <dt class="col-sm-5">RIB (20 chiffres):</dt>
                                            <dd class="col-sm-7"><?= htmlspecialchars($employee['rib']) ?></dd>
                                            <?php if (!empty($employee['bank_account'])): ?>
                                                <dt class="col-sm-5">Numéro de Compte:</dt>
                                                <dd class="col-sm-7"><?= htmlspecialchars($employee['bank_account']) ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="documents">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Documents de l'Employé</h5>
                                <a href="<?= route('employees_documents', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-folder-plus"></i> Gérer les Documents</a>
                            </div>
                            <?php if ($documents_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Titre du Document</th>
                                                <th>Date d'Upload</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($doc = $documents_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td><?= ucfirst(htmlspecialchars($doc['document_type'])) ?></td>
                                                    <td><?= htmlspecialchars($doc['title']) ?></td>
                                                    <td><?= formatDate($doc['upload_date'], 'd/m/Y H:i') ?></td>
                                                    <td class="text-end">
                                                        <?php
                                                        $actual_doc_filename = basename($doc['file_path']);
                                                        $baseUrlForLink = '';
                                                        if (defined('APP_LINK')) {
                                                            $baseUrlForLink = rtrim(APP_LINK, '/');
                                                        } elseif (defined('BASE_URL')) {
                                                            $baseUrlForLink = rtrim(BASE_URL, '/');
                                                        }
                                                        $documentsWebPath = '/assets/uploads/documents/';
                                                        $doc_filepath_for_link = $baseUrlForLink . $documentsWebPath . $actual_doc_filename;
                                                        ?>
                                                        <a href="#" class="btn btn-sm btn-outline-primary" title="Voir le document" onclick="showPdfPreview('<?= htmlspecialchars($doc_filepath_for_link) ?>'); return false;"><i class="bi bi-eye"></i></a>
                                                        <a href="<?= htmlspecialchars($doc_filepath_for_link) ?>" download="<?= htmlspecialchars($actual_doc_filename) ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="bi bi-download"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucun document n'a été trouvé pour cet employé.</div>
                            <?php endif; ?>
                        </div>


                        <div class="tab-pane fade" id="sanctions">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Historique des Sanctions Disciplinaires</h5>
                                <?php if ($employee['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#addSanctionModal"><i class="bi bi-exclamation-triangle-fill"></i> Nouvelle Sanction</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($sanctions_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type de Sanction</th>
                                                <th>ID</th>
                                                <th>Date de la Sanction</th>
                                                <th>Motif</th>
                                                <th>Document</th>
                                                <th>Questionnaire Référence</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($sanction = $sanctions_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        $sanction_labels = [
                                                            'avertissement_verbal' => 'Avertissement Verbal',
                                                            'avertissement_ecrit' => 'Avertissement Écrit (1er degré)',
                                                            'mise_a_pied_1' => 'Mise à pied 1 jour (2e degré)',
                                                            'mise_a_pied_2' => 'Mise à pied 2 jours (2e degré)',
                                                            'mise_a_pied_3' => 'Mise à pied 3 jours (2e degré)',
                                                            'licenciement' => 'Licenciement (3e degré)'
                                                        ];
                                                        echo htmlspecialchars($sanction_labels[$sanction['sanction_type']] ?? $sanction['sanction_type']);
                                                        ?>
                                                    </td>
                                                    <td><?= formatDate($sanction['reference_number']) ?></td>
                                                    <td><?= formatDate($sanction['sanction_date']) ?></td>
                                                    <td title="<?= htmlspecialchars($sanction['reason']) ?>"><?= htmlspecialchars(substr($sanction['reason'], 0, 50)) ?>...</td>
                                                    <td>
                                                        <?php if (!empty($sanction['notification_path'])):
                                                            $notification_url = '/assets/uploads/sanctions/' . basename($sanction['notification_path']);
                                                        ?>
                                                            <a href="#" onclick="showPdfPreview('<?= htmlspecialchars($notification_url) ?>'); return false;" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Voir</a>
                                                        <?php else: ?>
                                                            <a href="<?= route('sanctions_generate_notification_pdf', ['ref' => $sanction['reference_number']]) ?>" class="btn btn-sm btn-outline-warning" target="_blank" title="Générer PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($sanction['questionnaire_ref'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <a href="<?= route('sanctions_view_sanction', ['id' => $sanction['reference_number']]) ?>" class="btn btn-sm btn-info" title="Détails"><i class="bi bi-search"></i> Détails</a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucune sanction disciplinaire trouvée pour cet employé.</div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="formations">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Historique des Formations Suivies</h5>
                            </div>
                            <?php if (empty($formations_history)): ?>
                                <div class="alert alert-info">Cet employé n'est inscrit à aucune formation pour le moment.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Titre de la Formation</th>
                                                <th>Formateur / École</th>
                                                <th>Période</th>
                                                <th>Statut Formation</th>
                                                <th>Statut Participant</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($formations_history as $formation): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($formation['title']) ?></strong></td>
                                                    <td><?= htmlspecialchars($formation['trainer_name']) ?></td>
                                                    <td>Du <?= formatDate($formation['start_date']) ?> au <?= formatDate($formation['end_date']) ?></td>
                                                    <td>
                                                        <?php if ($formation['formation_status'] === 'Terminée'): ?>
                                                            <span class="badge bg-success">Terminée</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-primary">Planifiée</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $p_status = $formation['participant_status'];
                                                        $p_badge = 'secondary'; // Default for 'Inscrit'
                                                        if ($p_status === 'Complété') $p_badge = 'success';
                                                        if ($p_status === 'Annulé') $p_badge = 'danger';
                                                        ?>
                                                        <span class="badge bg-<?= $p_badge ?>"><?= htmlspecialchars($p_status) ?></span>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="<?= route('formations_view', ['id' => $formation['id']]) ?>" class="btn btn-sm btn-info" title="Voir les détails de la formation">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="questionnaires">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Historique des Questionnaires</h5>
                                <?php if ($employee['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionnaireModal"><i class="bi bi-patch-question-fill"></i> Générer un questionnaire</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($questionnaires_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Référence</th>
                                                <th>Date d'émission</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($q = $questionnaires_stmt->fetch(PDO::FETCH_ASSOC)):
                                                $status_labels = ['pending_response' => 'En attente', 'responded' => 'Répondu', 'decision_made' => 'Décision prise', 'closed' => 'Clôturé'];
                                                $status_badges = ['pending_response' => 'warning', 'responded' => 'info', 'decision_made' => 'primary', 'closed' => 'success'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($q['questionnaire_type']) ?></td>
                                                    <td><?= htmlspecialchars($q['reference_number']) ?></td>
                                                    <td><?= formatDate($q['issue_date']) ?></td>
                                                    <td><span class="badge bg-<?= $status_badges[$q['status']] ?? 'secondary' ?>"><?= $status_labels[$q['status']] ?? 'Inconnu' ?></span></td>
                                                    <td>
                                                        <a href="<?= route('questionnaires_generate_questionnaire_pdf', ['id' => $q['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Imprimer le questionnaire"><i class="bi bi-file-earmark-pdf"></i></a>
                                                        <a href="<?= route('questionnaires_view_questionnaire', ['id' => $q['id']]) ?>" class="btn btn-sm btn-info" title="Voir le questionnaire"><i class="bi bi-eye"></i></a>
                                                    </td>

                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucun questionnaire trouvé pour cet employé.</div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="leaves">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Historique des Congés</h5>
                                <?php if ($employee['status'] === 'active'): ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newAddLeaveModal">
                                            <i class="bi bi-plus-circle"></i> Nouvelle Demande
                                        </button>
                                        <a href="<?= route('leave_adjust_leave', ['nin' => $nin]) ?>" class="btn btn-sm btn-warning ms-1"><i class="bi bi-pencil-square"></i> Ajuster Solde</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($leaves_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type de Congé</th>
                                                <th>Période</th>
                                                <th>Durée (jours)</th>
                                                <th>Statut</th>
                                                <th>Date de Demande</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($leave = $leaves_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td>
                                                        <strong>
                                                            <?php
                                                            // Utilise le tableau de configuration pour afficher le bon libellé
                                                            echo htmlspecialchars($leave_types_config[$leave['leave_type']] ?? ucfirst($leave['leave_type']));
                                                            ?>
                                                        </strong>
                                                    </td>
                                                    <td>Du <?= formatDate($leave['start_date']) ?> au <?= formatDate($leave['end_date']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($leave['days_requested']) ?></td>
                                                    <td><span class="badge bg-<?= getStatusBadgeClass($leave['status']) ?>"><?= ucfirst(htmlspecialchars($leave['status'])) ?></span></td>
                                                    <td><?= formatDate($leave['created_at'], 'd/m/Y H:i') ?></td>
                                                    <td><a href="<?= route('leave_view', ['id' => $leave['id']]) ?>" class="btn btn-sm btn-info" title="Voir la demande"><i class="bi bi-eye"></i></a>
                                                        <?php if ($leave['status'] === 'approved' || $leave['status'] === 'prise'): ?>
                                                            <a href="<?= route('leave_Leave_certificate', ['leave_id' => $leave['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimer l'attestation de congé"><i class="bi bi-file-earmark-pdf"></i></a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucune demande de congé correspondante n'a été trouvée pour cet employé.</div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="sick-leaves">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Historique des Congés Maladie</h5>
                                <?php if ($employee['status'] === 'active'): ?>
                                    <a href="<?= route('leave_add_sick_leave', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Ajouter un congé maladie</a>
                                <?php endif; ?>
                            </div>
                            <?php if ($sick_leaves_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Période</th>
                                                <th>Durée (jours)</th>
                                                <th>Statut</th>
                                                <th>Justificatif</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($sick_leave = $sick_leaves_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td>Du <?= formatDate($sick_leave['start_date']) ?> au <?= formatDate($sick_leave['end_date']) ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($sick_leave['days_requested']) ?></td>
                                                    <td><span class="badge bg-<?= $sick_leave['status'] === 'approved' ? 'success' : ($sick_leave['status'] === 'rejected' ? 'danger' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($sick_leave['status'])) ?></span></td>
                                                    <td>
                                                        <?php if (!empty($sick_leave['justification_path'])):
                                                            $justification_url = '/assets/uploads/sick_justifications/' . basename($sick_leave['justification_path']);
                                                        ?>
                                                            <a href="#" onclick="showPdfPreview('<?= htmlspecialchars($justification_url) ?>'); return false;" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Voir</a>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucun congé maladie trouvé pour cet employé.</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($employee['gender'] === 'female'): ?>
                            <div class="tab-pane fade" id="maternity-leaves">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5>Historique des Congés Maternité</h5>
                                    <?php if ($employee['status'] === 'active'): ?>
                                        <a href="<?= route('leave_add_maternity_leave', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Ajouter un congé maternité</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($maternity_leaves_stmt) && $maternity_leaves_stmt->rowCount() > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Période</th>
                                                    <th>Durée (jours)</th>
                                                    <th>Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($maternity_leave = $maternity_leaves_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <tr>
                                                        <td>Du <?= formatDate($maternity_leave['start_date']) ?> au <?= formatDate($maternity_leave['end_date']) ?></td>
                                                        <td class="text-center"><?= htmlspecialchars($maternity_leave['days_requested']) ?></td>
                                                        <td><span class="badge bg-<?= $maternity_leave['status'] === 'approved' ? 'success' : ($maternity_leave['status'] === 'rejected' ? 'danger' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($maternity_leave['status'])) ?></span></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">Aucun congé de maternité trouvé pour cette employée.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="tab-pane fade" id="departure">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Gestion du Départ de l'Employé</h5>
                            </div>
                            <?php if ($employee['status'] === 'active'): ?>
                                <div class="alert alert-warning">
                                    <strong>Attention :</strong> L'enregistrement du départ rendra le profil de l'employé **inactif**. Cette action est difficilement réversible.
                                </div>
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#departureModal"><i class="bi bi-box-arrow-right"></i> Enregistrer un Départ</button>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <h5 class="alert-heading">Départ déjà enregistré</h5>
                                    <p>Cet employé est déjà marqué comme inactif.</p>
                                    <hr>
                                    <p class="mb-0"><strong>Date de sortie :</strong> <?= formatDate($employee['departure_date']) ?><br>
                                        <strong>Motif :</strong> <?= htmlspecialchars($employee['departure_reason']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>


                        <div class="tab-pane fade" id="certificates">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Historique des Certificats (5 derniers)</h5>
                                <div>
                                    <a href="<?= route('reports_generate', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-plus"></i> Nouveau Certificat</a>
                                    <a href="<?= route('reports_history', ['nin' => $nin]) ?>" class="btn btn-sm btn-info ms-1"><i class="bi bi-list-ul"></i> Voir Tout l'Historique</a>
                                </div>
                            </div>
                            <?php if ($certificates_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Type de Certificat</th>
                                                <th>Numéro de Référence</th>
                                                <th>Date d'Émission</th>
                                                <th>Émis par</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($cert = $certificates_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        $typeBadgeClass = ['attestation' => 'bg-primary', 'certficate' => 'bg-success', 'attestation_sold' => 'bg-info'];
                                                        $typeLabels = ['attestation' => 'Attestation', 'certficate' => 'Certificat', 'attestation_sold' => 'Attestation de Solde'];
                                                        $certTypeKey = strtolower($cert['certificate_type']);
                                                        $badgeClass = $typeBadgeClass[$certTypeKey] ?? 'bg-secondary';
                                                        $label = $typeLabels[$certTypeKey] ?? ucfirst(htmlspecialchars($cert['certificate_type']));
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($cert['reference_number']) ?></td>
                                                    <td><?= formatDate($cert['issue_date']) ?></td>
                                                    <td><?= htmlspecialchars($cert['prepared_by'] ?? 'N/A') ?></td>
                                                    <td class="text-end">
                                                        <?php
                                                        $actual_cert_filename = basename($cert['generated_filename']);
                                                        $baseUrlForLink = '';
                                                        if (defined('APP_LINK')) {
                                                            $baseUrlForLink = rtrim(APP_LINK, '/');
                                                        } elseif (defined('BASE_URL')) {
                                                            $baseUrlForLink = rtrim(BASE_URL, '/');
                                                        }
                                                        $certificatesWebPath = '/assets/uploads/certificates/';
                                                        $cert_filepath_for_link = $baseUrlForLink . $certificatesWebPath . $actual_cert_filename;
                                                        ?>
                                                        <a href="<?= route('reports_view_certificate', ['ref' => $cert['reference_number']]) ?>" class="btn btn-outline-primary" target="_blank" data-bs-toggle="tooltip" title="Voir le PDF">
                                                            <i class="bi bi-eye"></i>
                                                        </a><a href="<?= htmlspecialchars($cert_filepath_for_link) ?>" download="<?= htmlspecialchars($actual_cert_filename) ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="bi bi-download"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 text-end">
                                    <a href="<?= route('reports_history', ['nin' => $nin]) ?>" class="btn btn-sm btn-link">
                                        <i class="bi bi-chevron-double-right"></i> Afficher plus de certificats
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucun certificat n'a été généré récemment pour cet employé.</div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade" id="attendance">
                            <div class="tab-pane fade show active" id="attendance-tab-pane" role="tabpanel" aria-labelledby="attendance-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <?php
                                    $month_year_title_att = "Mois Inconnu";
                                    if ($current_dt) {
                                        $month_year_title_att = formatMonthYear($current_dt);
                                    }
                                    ?>
                                    <h5>Registre de Présence (<?= htmlspecialchars($month_year_title_att) ?>)</h5>
                                    <div>
                                        <a href="<?= route('reports_generate_attendance_report_pdf', ['nin' => $nin, 'month' => $attendance_filter_month_str]) ?>" class="btn btn-sm btn-danger" target="_blank">
                                            <i class="bi bi-file-earmark-pdf"></i> Imprimer Relevé du Mois
                                        </a>

                                        <?php
                                        $currentMonthNav = date('n');
                                        $currentYearNav  = date('Y');
                                        ?>
                                        <a href="<?= route('attendance_history', ['employee_nin' => $employee['nin'], 'year' => $currentYearNav, 'month' => $currentMonthNav]) ?>"
                                            class="btn btn-sm btn-info">
                                            <i class="bi bi-calendar-range"></i> Historique
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <a href="<?= $previous_month_link ?>" class="btn btn-sm btn-outline-secondary <?= $disable_prev ? 'disabled' : '' ?>" aria-disabled="<?= $disable_prev ? 'true' : 'false' ?>">
                                        <i class="bi bi-arrow-left"></i> Mois Précédent
                                    </a>
                                    <h6 class="mb-0"><?= htmlspecialchars($month_year_title_att) ?></h6>
                                    <a href="<?= $next_month_link ?>" class="btn btn-sm btn-outline-secondary <?= $disable_next ? 'disabled' : '' ?>" aria-disabled="<?= $disable_next ? 'true' : 'false' ?>">
                                        Mois Suivant <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header bg-light py-2">
                                        <h6 class="card-title mb-0 fw-bold">Résumé Mensuel (<?= htmlspecialchars($month_year_title_att) ?>)</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <dl class="row mb-0 small">
                                            <dt class="col-sm-5">Jours Travaillés (P) :</dt>
                                            <dd class="col-sm-7"><?= $total_worked_days ?> jour(s)</dd>
                                            <dt class="col-sm-5">Jours Travaillés Jour Férié (TF):</dt>
                                            <dd class="col-sm-7"><?= $total_tf_days ?> jour(s)</dd>
                                            <dt class="col-sm-5">Jours Travaillés Weekend (TW):</dt>
                                            <dd class="col-sm-7"><?= $total_tw_days ?> jour(s)</dd>
                                            <dt class="col-sm-5">Congés annuels (C) :</dt>
                                            <dd class="col-sm-7"><?= $total_annual_leave ?> jour(s)</dd>
                                            <dt class="col-sm-5">Maladie (M) :</dt>
                                            <dd class="col-sm-7"><span class="badge bg-purple"><?= $total_sick_leave ?> jour(s)</span></dd>
                                            <dt class="col-sm-5">Maternité (MT) :</dt>
                                            <dd class="col-sm-7"><?= $total_maternity_leave ?> jour(s)</dd>
                                            <dt class="col-sm-5">Formation (F) :</dt>
                                            <dd class="col-sm-7"><?= $total_training_leave ?> jour(s)</dd>
                                            <dt class="col-sm-5">Mission (MS) :</dt>
                                            <dd class="col-sm-7"><?= $total_mission_leave ?> jour(s)</dd>
                                            <dt class="col-sm-5">Autre absence (X) :</dt>
                                            <dd class="col-sm-7"><?= $total_other_leave ?> jour(s)</dd>
                                            <dt class="col-sm-5">Absent autorisé payé (AAP) :</dt>
                                            <dd class="col-sm-7"><?= $total_absent_justified_paid ?> jour(s)</dd>
                                            <dt class="col-sm-5">Absent autorisé non payé (AANP) :</dt>
                                            <dd class="col-sm-7"><?= $total_absent_justified_unpaid ?> jour(s)</dd>
                                            <dt class="col-sm-5 text-danger">Absent non justifié (ANJ) :</dt>
                                            <dd class="col-sm-7 text-danger"><strong><?= $total_absent_unjustified ?> jour(s)</strong></dd>

                                            <dt class="col-sm-12">
                                                <hr class="my-1">
                                            </dt>

                                            <dt class="col-sm-5">Heures Supplémentaires (HS) Mois :</dt>
                                            <dd class="col-sm-7"><?= number_format($monthly_hs_total_for_display, 2, ',', ' ') ?> h</dd>
                                            <dt class="col-sm-5">Heures de Retenue Mois :</dt>
                                            <dd class="col-sm-7"><?= number_format($monthly_retenue_total_for_display, 2, ',', ' ') ?> h</dd>
                                        </dl>
                                    </div>
                                </div>
                                <?php if (empty($attendance_records_for_month)): ?>
                                    <div class="alert alert-info">Aucun pointage pour le mois sélectionné.</div>
                                <?php else: ?>
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-sm table-hover table-bordered table-sticky-header">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Statut</th>
                                                    <th class="text-center">Travail WE/JF</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $attendance_code_map_display = [
                                                    'present' => ['label' => 'Présent (P)', 'badge' => 'bg-success text-white'],
                                                    'present_offday' => ['label' => 'Présent Jour Férié (TF)', 'badge' => 'bg-danger text-white'],
                                                    'present_weekend' => ['label' => 'Présent Weekend (TW)', 'badge' => 'bg-orange text-white'],
                                                    'annual_leave' => ['label' => 'Congé Annuel (C)', 'badge' => 'bg-info text-dark'],
                                                    'sick_leave' => ['label' => 'Maladie (M)', 'badge' => 'bg-purple text-white'],
                                                    'maladie' => ['label' => 'Maladie (M)', 'badge' => 'bg-purple text-white'],
                                                    'weekend' => ['label' => 'Repos (RC)', 'badge' => 'bg-light text-dark border'],
                                                    'holiday' => ['label' => 'Jour Férié (JF)', 'badge' => 'bg-light text-dark border'],
                                                    'absent_unjustified' => ['label' => 'Absent NJ (ANJ)', 'badge' => 'bg-danger text-white'],
                                                    'absent_authorized_paid' => ['label' => 'Absent AP (AAP)', 'badge' => 'bg-warning text-dark'],
                                                    'absent_authorized_unpaid' => ['label' => 'Absent ANP (AANP)', 'badge' => 'bg-secondary text-white'],
                                                    'maternity_leave' => ['label' => 'Maternité (MT)', 'badge' => 'bg-pink text-dark'],
                                                    'training' => ['label' => 'Formation (F)', 'badge' => 'bg-teal text-white'],
                                                    'mission' => ['label' => 'Mission (MS)', 'badge' => 'bg-primary text-white'],
                                                    'other_leave' => ['label' => 'Autre Congé (X)', 'badge' => 'bg-indigo text-white'],
                                                    'on_leave_from_excel_c' => ['label' => 'Congé (C) (Excel)', 'badge' => 'bg-info text-dark'],
                                                    'on_leave_from_excel_m' => ['label' => 'Maladie (M) (Excel)', 'badge' => 'bg-purple text-white'],
                                                    'on_leave_from_excel_mt' => ['label' => 'Maternité (MT) (Excel)', 'badge' => 'bg-pink text-dark'],
                                                    'on_leave_from_excel_f' => ['label' => 'Formation (F) (Excel)', 'badge' => 'bg-teal text-white'],
                                                    'on_leave_from_excel_ms' => ['label' => 'Mission (MS) (Excel)', 'badge' => 'bg-primary text-white'],
                                                    'on_leave_from_excel_x' => ['label' => 'Autre Congé (X) (Excel)', 'badge' => 'bg-indigo text-white'],
                                                    'absent_from_excel_anj' => ['label' => 'Absent NJ (ANJ) (Excel)', 'badge' => 'bg-danger text-white'],
                                                    'absent_from_excel_aap' => ['label' => 'Absent AP (AAP) (Excel)', 'badge' => 'bg-warning text-dark'],
                                                    'absent_from_excel_aanp' => ['label' => 'Absent ANP (AANP) (Excel)', 'badge' => 'bg-secondary text-white'],
                                                ];
                                                foreach ($attendance_records_for_month as $record):
                                                    $db_status_lc = strtolower($record['status'] ?? '');
                                                    $status_info = $attendance_code_map_display[$db_status_lc] ?? ['label' => htmlspecialchars(ucfirst(str_replace('_', ' ', $record['status'] ?? 'N/A'))), 'badge' => 'bg-light text-dark'];
                                                    $status_badge_class = $status_info['badge'];
                                                    $status_display_text = $status_info['label'];
                                                ?>
                                                    <tr>
                                                        <td><?= formatDate($record['attendance_date'] ?? null, 'd/m/Y (D)') ?></td>
                                                        <td><span class="badge <?= $status_badge_class ?>"><?= $status_display_text ?></span></td>
                                                        <td class="text-center">
                                                            <?php if (!empty($record['is_weekend_work'])): ?><span class="badge bg-warning text-dark" title="Travail Weekend">WE</span>
                                                            <?php elseif (!empty($record['is_holiday_work'])): ?><span class="badge bg-info text-white" title="Travail Jour Férié">JF</span>
                                                            <?php else: ?><small class="text-muted">Normal</small><?php endif; ?>
                                                        </td>
                                                        <td><small><?= nl2br(htmlspecialchars($record['notes'] ?? ($record['leave_type_if_absent'] ?? ''))) ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <style>
                                .bg-purple {
                                    background-color: #6f42c1 !important;
                                    color: #fff !important;
                                }

                                .bg-pink {
                                    background-color: #e83e8c !important;
                                    color: #fff !important;
                                }

                                .bg-orange {
                                    background-color: #fd7e14 !important;
                                    color: #fff !important;
                                }

                                .bg-teal {
                                    background-color: #20c997 !important;
                                    color: #fff !important;
                                }

                                .bg-indigo {
                                    background-color: #6610f2 !important;
                                    color: #fff !important;
                                }

                                .badge.bg-info.text-dark {
                                    color: #000 !important;
                                }

                                .badge.bg-warning.text-dark {
                                    color: #000 !important;
                                }

                                .table-sticky-header thead th {
                                    position: sticky;
                                    top: 0;
                                    background-color: #f8f9fa;
                                    z-index: 10;
                                }
                            </style>
                        </div>

                        <div class="tab-pane fade" id="career-and-decisions" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Évolution de Carrière & Décisions</h4>
                                <?php if ($employee['status'] === 'active'): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promotionModal">
                                        <i class="bi bi-graph-up-arrow"></i> Enregistrer une Décision
                                    </button>
                                <?php endif; ?>
                            </div>

                            <h5>Historique des Décisions de Carrière (Notifications)</h5>
                            <?php if (count($decisions_history) > 0): ?>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Référence</th>
                                                <th>Type Décision</th>
                                                <th>Date d'Effet</th>
                                                <th class="text-end">Notification PDF</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($decisions_history as $decision): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($decision['reference_number']) ?></td>
                                                    <td>
                                                        <?php
                                                        $decision_labels = [
                                                            'promotion_only' => 'Promotion sans augmentation',
                                                            'promotion_salary' => 'Promotion avec augmentation',
                                                            'salary_only' => 'Augmentation seule'
                                                        ];
                                                        echo $decision_labels[$decision['decision_type']] ?? 'N/A';
                                                        ?>
                                                    </td>
                                                    <td><?= formatDate($decision['effective_date']) ?></td>
                                                    <td class="text-end">
                                                        <a href="<?= route('promotions_generate_decision_pdf', ['id' => $decision['id']]) ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Générer la Notification PDF">
                                                            <i class="bi bi-file-earmark-pdf"></i> PDF
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucune décision de carrière formalisée n'a été trouvée pour cet employé.</div>
                            <?php endif; ?>

                            <hr class="my-4">

                            <h5>Historique des Postes</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Poste</th>
                                        <th>Département</th>
                                        <th>Date de Début</th>
                                        <th>Date de Fin</th>
                                        <th>Motif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($position_history as $pos): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($pos['position_title']) ?></td>
                                            <td><?= htmlspecialchars($pos['department']) ?></td>
                                            <td><?= formatDate($pos['start_date']) ?></td>
                                            <td><?= $pos['end_date'] ? formatDate($pos['end_date']) : '<span class="badge bg-success">Actuel</span>' ?></td>
                                            <td><?= htmlspecialchars($pos['change_reason']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <hr class="my-4">

                            <h5>Historique des Salaires</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Salaire Net</th>
                                        <th>Date d'effet</th>
                                        <th>Type de changement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salary_history as $sal): ?>
                                        <tr>
                                            <td><?= number_format($sal['gross_salary'], 2, ',', ' ') ?> DZD</td>
                                            <td><?= formatDate($sal['effective_date']) ?></td>
                                            <td><?= htmlspecialchars($sal['change_type']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="tab-pane fade" id="notifications">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Notifications (Période d'essai)</h5>
                            </div>
                            <?php
                            $notifications_stmt = $db->prepare("SELECT * FROM trial_notifications WHERE employee_nin = ? ORDER BY issue_date DESC");
                            $notifications_stmt->execute([$employee['nin']]);
                            ?>
                            <?php if ($notifications_stmt->rowCount() > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Référence</th>
                                                <th>Date</th>
                                                <th>Décision</th>
                                                <th class="text-end">PDF</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($noti = $notifications_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($noti['reference_number']) ?></td>
                                                    <td><?= formatDate($noti['issue_date'], 'd/m/Y') ?></td>
                                                    <td>
                                                        <?php
                                                        if ($noti['decision'] === 'confirm') echo "<span class='badge bg-success'>Confirmation</span>";
                                                        elseif ($noti['decision'] === 'renew') echo "<span class='badge bg-warning text-dark'>Renouvellement</span>";
                                                        else echo "<span class='badge bg-danger'>Fin de contrat</span>";
                                                        ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="<?= route('trial_notifications_trial_notification_view', ['ref' => $noti['reference_number']]) ?>" class="btn btn-info" data-bs-toggle="tooltip" title="Voir les détails">
                                                            <i class="bi bi-search"></i> Détails
                                                        </a>
                                                        <a href="<?= route('trial_notifications_generate_notification_pdf', ['ref' => $noti['reference_number']]) ?>" class="btn btn-sm btn-outline-danger" title="Générer le PDF" target="_blank">
                                                            <i class="bi bi-file-earmark-pdf"></i> PDF
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">Aucune notification de période d'essai trouvée pour cet employé.</div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="departureModal" tabindex="-1" aria-labelledby="departureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <form action="<?= route('employees_process_departure') ?>" method="post">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="departureModalLabel">Enregistrer le Départ de l'Employé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                    <div class="mb-3">
                        <label for="departure_date" class="form-label">Date de Sortie</label>
                        <input type="date" class="form-control" id="departure_date" name="departure_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="departure_reason" class="form-label">Motif du Départ</label>
                        <select class="form-select" id="departure_reason" name="departure_reason" required onchange="toggleCustomReason(this.value)">
                            <option value="" selected disabled>-- Choisir un motif --</option>
                            <option value="Fin de contrat CDD">Fin de contrat (CDD)</option>
                            <option value="Période d'essai non concluante">Période d'essai non concluante</option>
                            <option value="Rupture de contrat à l'amiable">Rupture de contrat à l'amiable</option>
                            <option value="Démission">Démission</option>
                            <option value="Retraite">Retraite</option>
                            <option value="Licenciement">Licenciement (suite à une sanction)</option>
                            <option value="Décès">Décès</option>
                            <option value="Autre">Autre (préciser)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="custom_reason_wrapper" style="display: none;">
                        <label for="custom_reason" class="form-label">Préciser le motif</label>
                        <input type="text" class="form-control" id="custom_reason" name="custom_reason">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Confirmer le Départ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addSanctionModal" tabindex="-1" aria-labelledby="addSanctionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form action="<?= route('sanctions_add_handler') ?>" method="post" enctype="multipart/form-data">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addSanctionModalLabel">Nouvelle Sanction Disciplinaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">
                    <div class="alert alert-info">Pour appliquer une sanction, veuillez d'abord sélectionner un questionnaire clôturé qui servira de base à votre décision.</div>

                    <div class="mb-3">
                        <label for="questionnaire_id" class="form-label">Questionnaire Lié</label>
                        <select class="form-select" id="questionnaire_id" name="questionnaire_id" required>
                            <option value="" selected disabled>-- Sélectionner un questionnaire clôturé --</option>
                            <?php while ($q_avail = $available_questionnaires_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?= $q_avail['id'] ?>"><?= htmlspecialchars($q_avail['reference_number'] . ' - ' . $q_avail['questionnaire_type'] . ' (' . formatDate($q_avail['issue_date']) . ')') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label for="sanction_type" class="form-label">Type de Sanction</label>
                        <select class="form-select" name="sanction_type" required>
                            <option value="" selected disabled>-- Choisir le type --</option>
                            <optgroup label="1er Degré">
                                <option value="avertissement_ecrit">Avertissement Écrit</option>
                            </optgroup>
                            <optgroup label="2e Degré">
                                <option value="mise_a_pied_1">Mise à pied (1 jour)</option>
                                <option value="mise_a_pied_2">Mise à pied (2 jours)</option>
                                <option value="mise_a_pied_3">Mise à pied (3 jours)</option>
                            </optgroup>
                            <optgroup label="3e Degré">
                                <option value="licenciement">Licenciement</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="sanction_date" class="form-label">Date de la Sanction</label>
                        <input type="date" class="form-control" name="sanction_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motif / Décision finale</label>
                        <textarea class="form-control" name="reason" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer la Sanction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="updateQuestionnaireModal" tabindex="-1" aria-labelledby="updateQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form action="<?= route('questionnaires_update_handler') ?>" method="post">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="updateQuestionnaireModalLabel">Mettre à jour le Questionnaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="reference_number" id="update_q_ref">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">

                    <div class="mb-3">
                        <label for="update_status" class="form-label">Statut du Questionnaire</label>
                        <select class="form-select" id="update_status" name="status" required>
                            <option value="pending_response">En attente de réponse</option>
                            <option value="responded">Répondu</option>
                            <option value="decision_made">Décision prise</option>
                            <option value="closed">Clôturé</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="update_response_summary" class="form-label">Résumé de la Réponse de l'Employé (optionnel)</label>
                        <textarea name="response_summary" id="update_response_summary" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="update_decision" class="form-label">Décision Finale (optionnel)</label>
                        <textarea name="decision" id="update_decision" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les Modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addQuestionnaireModal" tabindex="-1" aria-labelledby="addQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="<?= route('questionnaires_questionnaire_handler') ?>" method="post">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addQuestionnaireModalLabel">Nouveau Questionnaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">


                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="questionnaire_type" class="form-label">Type de Questionnaire *</label>
                            <select class="form-select" id="questionnaire_type" name="questionnaire_type" required>
                                <option value="Entretien préalable à une sanction">Disciplinaire (Entretien préalable)</option>
                                <option value="Evaluation de performance">Evaluation de performance</option>
                                <option value="Entretien Annuel" selected>Entretien Annuel</option>
                                <option value="Autre">Autre (Personnalisé)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="issue_date" class="form-label">Date d'Émission *</label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label">Sujet / Contexte *</label>
                        <textarea name="subject" id="subject" class="form-control" rows="2" required></textarea>
                    </div>

                    <hr>

                    <h6 class="mb-3">Questions du questionnaire</h6>
                    <div id="questions_container"></div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Générer le Questionnaire</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="voucherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Identifiants d'Accès Générés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="voucherContent" class="p-4 border rounded bg-light">
                    <div class="text-center mb-4">
                        <h3>Vos Identifiants d'Accès</h3>
                        <p class="text-muted">Veuillez conserver ce document en lieu sûr.</p>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Employé</h5>
                                    <p class="card-text" id="voucherEmployeeName"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Date de Création</h5>
                                    <p class="card-text" id="voucherCreationDate"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <h5 class="alert-heading">Instructions</h5>
                        <p>Ces identifiants sont à transmettre à l'employé. Il devra changer son mot de passe lors de sa première connexion.</p>
                    </div>
                    <div class="credentials-box p-3 bg-white border rounded text-center">
                        <h4 class="mb-3">Identifiants de Connexion</h4>
                        <div class="d-flex justify-content-around my-3">
                            <div>
                                <h6>Nom d'utilisateur</h6>
                                <div class="p-2 bg-light rounded"><code id="voucherUsername" class="fs-4"></code></div>
                            </div>
                            <div>
                                <h6>Mot de passe</h6>
                                <div class="p-2 bg-light rounded"><code id="voucherPassword" class="fs-4"></code></div>
                            </div>
                        </div>
                        <p class="text-muted mt-2">URL d'accès: <?= defined('APP_LINK') ? htmlspecialchars(APP_LINK) : '#' ?>/login</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="printVoucher()"><i class="bi bi-printer"></i> Imprimer</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width:90vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfPreviewLabel">Aperçu du Document PDF</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" style="height:80vh;"><iframe id="pdfPreviewFrame" src="about:blank" style="width:100%;height:100%;" frameborder="0"></iframe></div>
        </div>
    </div>
</div>

<div class="modal fade" id="newAddLeaveModal" tabindex="-1" aria-labelledby="newAddLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form method="post" action="<?= route('leave_add') ?>" id="newLeaveForm">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="newAddLeaveModalLabel">Nouvelle Demande de Congé pour <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                    <input type="hidden" name="source_page" value="employee_view">
                    <input type="hidden" name="redirect_nin" value="<?= htmlspecialchars($employee['nin']) ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Type de Congé*</label>
                                <select name="leave_type" class="form-select" required id="modal_leave_type" onchange="updateModalLeaveTypeUI()">
                                    <?php foreach ($detailed_leave_types_view as $key => $type): ?>
                                        <option value="<?= $key ?>"><?= $type['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3" id="modal_soldes_display">
                                <label class="form-label">Soldes disponibles</label>
                                <ul class="list-group">
                                    <li class="list-group-item">Annuel: <span id="modal_annual_leave_balance"></span> jours</li>
                                    <li class="list-group-item">Reliquat: <span id="modal_remaining_leave_balance"></span> jours (max 30)</li>
                                    <li class="list-group-item">Récup: <span id="modal_recup_balance"></span> jours</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date Début*</label>
                                <input type="date" name="start_date" class="form-control" required id="modal_start_date">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date Fin*</label>
                                <input type="date" name="end_date" class="form-control" required id="modal_end_date">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motif*</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form action="<?= route('promotions_handle_decision') ?>" method="POST" id="promotionForm">
                <?php csrf_input(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="promotionModalLabel">Enregistrer une Décision de Carrière</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">

                    <div class="mb-3">
                        <label for="decision_type" class="form-label">Type de Décision *</label>
                        <select name="decision_type" id="decision_type" class="form-select" required onchange="togglePromotionFields()">
                            <option value="" selected disabled>-- Choisir le type d'action --</option>
                            <option value="promotion_only">Promotion sans augmentation</option>
                            <option value="promotion_salary">Promotion avec augmentation</option>
                            <option value="salary_only">Augmentation de salaire seule</option>
                        </select>
                    </div>

                    <div class="mb-3" id="position_fields" style="display:none;">
                        <label class="form-label">Nouveau Poste *</label>
                        <select name="new_position" id="new_position" class="form-select">
                            <option value="" disabled selected>-- Choisir un poste --</option>
                            <?php foreach ($positions_list as $pos): ?>
                                <option value="<?= htmlspecialchars($pos['nom']) ?>"><?= htmlspecialchars($pos['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="salary_field" style="display:none;">
                        <label class="form-label">Nouveau Salaire Brut Mensuel (DZD) *</label>
                        <input type="number" step="0.01" name="new_salary" id="new_salary" class="form-control" placeholder="ex: 65000.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Date d'effet de la décision *</label>
                        <input type="date" name="effective_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motif / Justification</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer la Décision</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="trialDecisionModal" tabindex="-1" aria-labelledby="trialDecisionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="trialDecisionModalLabel">Décision sur la Période d'Essai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= route('trial_notifications_process_trial_decision') ?>" method="POST">
                <?php csrf_input(); ?>
                <div class="modal-body">
                    <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($employee['nin']) ?>">
                    <p>Que souhaitez-vous faire concernant la période d'essai de cet employé ?</p>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="decision" id="decision_confirm" value="confirm" checked>
                        <label class="form-check-label" for="decision_confirm">
                            <strong>Confirmer l'employé</strong><br>
                            <small>Le statut "Période d'essai" sera retiré.</small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="decision" id="decision_renew" value="renew">
                        <label class="form-check-label" for="decision_renew">
                            <strong>Renouveler la période d'essai</strong>
                        </label>
                        <div id="renew_options" class="mt-2" style="display:none;">
                            <label for="renewal_duration_months">Durée du renouvellement (mois):</label>
                            <select name="renewal_duration_months" class="form-select form-select-sm">
                                <option value="3">3 mois</option>
                                <option value="6">6 mois</option>
                            </select>
                            <input type="hidden" name="renew_period" id="renew_period_label" value="">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="decision" id="decision_terminate" value="terminate">
                        <label class="form-check-label" for="decision_terminate">
                            <strong>Terminer le contrat</strong><br>
                            <small>Motif: Période d'essai non concluante.</small>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Valider la décision</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pdf_to_show = $_GET['showpdf'] ?? 0;
$pdf_url = $_GET['pdf'] ?? '';
?>

<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">Notification PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" style="height:80vh;">
                <?php if ($pdf_url): ?>
                    <iframe src="<?= htmlspecialchars($pdf_url) ?>" width="100%" height="100%" style="border:0; min-height:70vh;"></iframe>
                <?php else: ?>
                    <div class="alert alert-warning">Aucun PDF à afficher.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Corrected openUpdateQuestionnaireModal function
    function openUpdateQuestionnaireModal(data) {
        // Étape 1 : Vérifier si la fonction est bien appelée

        // Étape 2 : Vérifier si l'on trouve l'élément du modal
        var modalElement = document.getElementById('updateQuestionnaireModal');

        try {
            document.getElementById('update_q_ref').value = data.reference_number;
            document.getElementById('update_status').value = data.status;
            document.getElementById('update_response_summary').value = data.response_summary || '';
            document.getElementById('update_decision').value = data.decision || '';

        } catch (e) {
            alert("ERREUR à l'étape 3 : Impossible de remplir les champs. Erreur : " + e.message);
            return;
        }

        // Étape 4 : Essayer d'afficher le modal
        try {
            var myModal = new bootstrap.Modal(modalElement);

            myModal.show();
        } catch (e) {
            alert("ERREUR à l'étape 4 : Impossible d'afficher le modal. Le JavaScript de Bootstrap est-il bien chargé ? Erreur : " + e.message);
            return;
        }
    }

    function addQuestionField() {
        const container = document.getElementById('questions_container');
        const newIndex = container.getElementsByClassName('input-group').length + 1;
        const newField = document.createElement('div');
        newField.className = 'input-group mb-2';
        newField.innerHTML = `
        <span class="input-group-text">${newIndex}.</span>
        <input type="text" name="questions[]" class="form-control" placeholder="Question ${newIndex}">
    `;
        container.appendChild(newField);
    }


    function toggleCustomReason(value) {
        const wrapper = document.getElementById('custom_reason_wrapper');
        if (value === 'Autre') {
            wrapper.style.display = 'block';
            document.getElementById('custom_reason').required = true;
        } else {
            wrapper.style.display = 'none';
            document.getElementById('custom_reason').required = false;
        }
    }

    async function generateQuickAccess(createUrl, viewUrlBase, event) {
        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Génération...';
        btn.disabled = true;
        try {
            const response = await fetch(createUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error("Erreur de parsing JSON:", responseText);
                throw new Error('Réponse du serveur invalide.');
            }
            if (!response.ok) {
                throw new Error(data.message || `Erreur du serveur (${response.status}).`);
            }
            if (!data.success) {
                throw new Error(data.message || 'Erreur inconnue.');
            }

            document.getElementById('voucherEmployeeName').textContent = `${data.employee_first_name} ${data.employee_last_name}`;
            document.getElementById('voucherCreationDate').textContent = new Date().toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            document.getElementById('voucherUsername').textContent = data.username;
            document.getElementById('voucherPassword').textContent = data.password;

            var voucherModalEl = document.getElementById('voucherModal');
            var voucherModal = bootstrap.Modal.getInstance(voucherModalEl) || new bootstrap.Modal(voucherModalEl);
            voucherModal.show();

            // Assuming the backend returns the user_id, construct the view URL
            const viewUrl = viewUrlBase + '&id=' + data.user_id;
            const newButtonHtml = `<a href="${viewUrl}" class="btn btn-sm btn-secondary"><i class="bi bi-person-check"></i> Voir Compte Utilisateur</a>`;
            if (btn.parentElement) {
                btn.outerHTML = newButtonHtml;
            }

        } catch (error) {
            console.error('Erreur generateQuickAccess:', error);
            alert('Erreur: ' + error.message);
            if (btn && document.body.contains(btn)) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }
    }

    function printVoucher() {
        const printContent = document.getElementById('voucherContent').innerHTML;
        const styles = Array.from(document.head.querySelectorAll('link[rel="stylesheet"], style'));
        let printWindow = window.open('', '_blank', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Imprimer Voucher</title>');
        styles.forEach(style => {
            if (style.tagName === 'LINK') {
                printWindow.document.write(`<link rel="stylesheet" href="${style.href}">`);
            } else {
                printWindow.document.write(style.outerHTML);
            }
        });
        printWindow.document.write('</head><body>');
        printWindow.document.write(`<div class="container p-3">${printContent}</div>`);
        printWindow.document.close();

        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    function showPdfPreview(pdfUrl) {
        var modalEl = document.getElementById('pdfPreviewModal');
        if (modalEl) {
            var pdfPreviewModalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            document.getElementById('pdfPreviewFrame').src = pdfUrl;
            pdfPreviewModalInstance.show();
        } else {
            console.error("Modal 'pdfPreviewModal' non trouvée.");
        }
    }

    const modalDetailedLeaveTypes = <?= json_encode($detailed_leave_types_view) ?>;
    const modalLeaveTypeSelect = document.getElementById('modal_leave_type');
    const modalAnnualBalanceSpan = document.getElementById('modal_annual_leave_balance');
    const modalRemainingBalanceSpan = document.getElementById('modal_remaining_leave_balance');
    const modalRecupBalanceSpan = document.getElementById('modal_recup_balance');
    const modalStartDateInput = document.getElementById('modal_start_date');
    const modalEndDateInput = document.getElementById('modal_end_date');

    const employeeAnnualBalance = <?= json_encode($employee['annual_leave_balance'] ?? 0) ?>;
    const employeeRemainingBalance = <?= json_encode($employee['remaining_leave_balance'] ?? 0) ?>;
    const employeeRecupBalance = <?= json_encode($current_recup_balance ?? 0) ?>;

    function updateModalLeaveTypeUI() {
        updateModalPermittedDays();
        updateModalLeaveTypesAvailability();
    }

    function updateModalPermittedDays() {
        const leaveType = modalLeaveTypeSelect.value;
        const annual = parseFloat(modalAnnualBalanceSpan.textContent) || 0;
        const reliquat = parseFloat(modalRemainingBalanceSpan.textContent) || 0;
        const recup = parseFloat(modalRecupBalanceSpan.textContent) || 0;

        let typeConfig = modalDetailedLeaveTypes[leaveType];
        let calculatedMaxDays = 365;

        if (typeConfig) {
            if (leaveType === 'annuel') {
                calculatedMaxDays = Math.floor(reliquat > 30 ? 30 : reliquat) + recup + annual;
            } else if (leaveType === 'reliquat') {
                calculatedMaxDays = Math.floor(reliquat > 30 ? 30 : reliquat);
            } else if (leaveType === 'recup') {
                calculatedMaxDays = recup;
            } else if (leaveType === 'anticipe') {
                calculatedMaxDays = 30;
            } else if (typeConfig.max_days !== null) {
                calculatedMaxDays = typeConfig.max_days;
            }
        }

        modalEndDateInput.min = modalStartDateInput.value;
        if (modalStartDateInput.value && calculatedMaxDays > 0) {
            const start = new Date(modalStartDateInput.value);
            const maxEnd = new Date(start);
            maxEnd.setDate(start.getDate() + calculatedMaxDays - 1);
            modalEndDateInput.max = maxEnd.toISOString().split('T')[0];
        } else {
            modalEndDateInput.max = "";
        }
    }

    function updateModalLeaveTypesAvailability() {
        const annual = parseFloat(modalAnnualBalanceSpan.textContent) || 0;
        const reliquat = parseFloat(modalRemainingBalanceSpan.textContent) || 0;
        const recup = parseFloat(modalRecupBalanceSpan.textContent) || 0;
        const hasSold = (annual > 0) || (reliquat > 0) || (recup > 0);

        for (const option of modalLeaveTypeSelect.options) {
            const typeInfo = modalDetailedLeaveTypes[option.value];
            if (typeInfo) {
                if (typeInfo.has_sold === false) {
                    option.disabled = false;
                } else if ((option.value === 'unpaid' || option.value === 'anticipe') && hasSold) {
                    option.disabled = true;
                    if (modalLeaveTypeSelect.value === option.value) {
                        modalLeaveTypeSelect.value = 'annuel';
                        updateModalPermittedDays();
                    }
                } else {
                    option.disabled = false;
                }
            }
        }
    }

    if (modalLeaveTypeSelect) {
        modalLeaveTypeSelect.addEventListener('change', updateModalLeaveTypeUI);
    }

    function toggleSalaryField() {
        document.getElementById("salaryField").style.display = document.getElementById("update_salary").checked ? "block" : "none";
    }

    // New function for Promotion Modal dynamic fields
    function togglePromotionFields() {
        const decisionTypeSelect = document.getElementById('decision_type');
        const positionFields = document.getElementById('position_fields');
        const newPositionSelect = document.getElementById('new_position');
        const salaryField = document.getElementById('salary_field');
        const newSalaryInput = document.getElementById('new_salary');

        const selectedType = decisionTypeSelect.value;

        // Reset required attributes and display
        newPositionSelect.removeAttribute('required');
        newSalaryInput.removeAttribute('required');
        positionFields.style.display = 'none';
        salaryField.style.display = 'none';

        if (selectedType === 'promotion_only') {
            positionFields.style.display = 'block';
            newPositionSelect.setAttribute('required', 'required');
        } else if (selectedType === 'promotion_salary') {
            positionFields.style.display = 'block';
            newPositionSelect.setAttribute('required', 'required');
            salaryField.style.display = 'block';
            newSalaryInput.setAttribute('required', 'required');
        } else if (selectedType === 'salary_only') {
            salaryField.style.display = 'block';
            newSalaryInput.setAttribute('required', 'required');
        }
    }

    // [IMPROVEMENT] Combined DOMContentLoaded listeners
    document.addEventListener('DOMContentLoaded', function() {
        // --- Activate tab based on URL hash ---
        const hash = window.location.hash;
        if (hash) {
            const tabTriggerEl = document.querySelector(`.nav-tabs a[href="${hash}"]`);
            if (tabTriggerEl) {
                var tab = bootstrap.Tab.getInstance(tabTriggerEl) || new bootstrap.Tab(tabTriggerEl);
                tab.show();
            }
        }

        // --- Logic for New Leave Modal ---
        var newAddLeaveModalEl = document.getElementById('newAddLeaveModal');
        if (newAddLeaveModalEl) {
            newAddLeaveModalEl.addEventListener('show.bs.modal', function() {
                modalAnnualBalanceSpan.textContent = parseFloat(employeeAnnualBalance).toFixed(1);
                modalRemainingBalanceSpan.textContent = parseFloat(employeeRemainingBalance).toFixed(1);
                modalRecupBalanceSpan.textContent = parseFloat(employeeRecupBalance).toFixed(1);

                modalStartDateInput.valueAsDate = new Date();
                modalEndDateInput.value = modalStartDateInput.value;

                updateModalLeaveTypeUI();
            });
        }

        // --- Logic for Trial Period Decision Modal ---
        const renewRadio = document.getElementById('decision_renew');
        const renewOptions = document.getElementById('renew_options');
        document.querySelectorAll('input[name="decision"]').forEach((elem) => {
            elem.addEventListener("change", function(event) {
                renewOptions.style.display = renewRadio.checked ? 'block' : 'none';
            });
        });
        var select = document.querySelector('select[name="renewal_duration_months"]');
        var hiddenLabel = document.getElementById('renew_period_label');

        function updateLabel() {
            if (select) {
                hiddenLabel.value = select.value + " mois";
            }
        }
        if (select) {
            select.addEventListener('change', updateLabel);
            updateLabel(); // Initialise on load
        }
        if (renewRadio && renewRadio.checked) {
            renewOptions.style.display = 'block';
        }

        // --- Logic for Promotion Modal ---
        const decisionTypeSelect = document.getElementById('decision_type');
        if (decisionTypeSelect) {
            decisionTypeSelect.addEventListener('change', togglePromotionFields);
            togglePromotionFields(); // Initial call
        }

        // --- Logic for Dynamic Questionnaire Modal ---
        const defaultQuestions = <?= json_encode($default_questions) ?>;
        const typeSelect = document.getElementById('questionnaire_type');
        const questionsContainer = document.getElementById('questions_container');

        function updateQuestionFields() {
            if (!typeSelect || !questionsContainer) return;
            const selectedType = typeSelect.value;
            const questions = defaultQuestions[selectedType] || ["", "", ""]; // Fallback

            let questionsHtml = '';
            questions.forEach((questionText, index) => {
                questionsHtml += `
                <div class="input-group mb-2">
                    <span class="input-group-text">${index + 1}.</span>
                    <input 
                        type="text" 
                        name="questions[]" 
                        class="form-control" 
                        placeholder="Question ${index + 1}" 
                        value="${questionText.replace(/"/g, '&quot;')}" 
                        required>
                </div>
            `;
            });
            questionsContainer.innerHTML = questionsHtml;
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', updateQuestionFields);
            updateQuestionFields(); // Initial population
        }
    });

    <?php if ($pdf_to_show && $pdf_url): ?>
        window.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('pdfModal'));
            myModal.show();
        });
    <?php endif; ?>
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>