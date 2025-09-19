<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}



redirectIfNotHR();
$pageTitle = "Gestion de la Présence";

// --- Filter initialization ---
$current_year = (int)date('Y');
$current_month = (int)date('m');
$filter_year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, [
    'options' => ['default' => $current_year, 'min_range' => 2000, 'max_range' => 2050]
]);
$filter_month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, [
    'options' => ['default' => $current_month, 'min_range' => 1, 'max_range' => 12]
]);
$filter_employee_nin = sanitize($_GET['employee_nin'] ?? '');

// --- Month name ---
$month_name_display = "Mois";
try {
    $dateObjDisp = DateTime::createFromFormat('!m', $filter_month);
    if ($dateObjDisp) {
        if (class_exists('IntlDateFormatter')) {
            $formatter = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM');
            $month_name_display = ucfirst($formatter->format($dateObjDisp));
        } else {
            $month_name_display = $dateObjDisp->format('F');
        }
    }
} catch(Exception $e) {}

// --- Attendance code map ---
$attendance_code_map = [
    'P'    => ['label' => 'Présent (jour ouvré normal)', 'badge' => 'bg-success'],
    'TF'   => ['label' => 'Présent sur jour férié (travail payé 175% + recup)', 'badge' => 'bg-danger'],
    'TW'   => ['label' => 'Présent sur weekend/vendredi (travail payé 175% + recup)', 'badge' => 'bg-danger'],
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

// --- Attendance query ---
$params = [];
$conditions = ["DATE_FORMAT(a.attendance_date, '%Y-%m') = :month"];
$params[':month'] = sprintf('%04d-%02d', $filter_year, $filter_month);

if ($filter_employee_nin) {
    $conditions[] = "a.employee_nin = :nin";
    $params[':nin'] = $filter_employee_nin;
}

$sql_attendance = "SELECT a.*, e.first_name, e.last_name FROM employee_attendance a JOIN employees e ON a.employee_nin = e.nin"
    . (count($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '')
    . " ORDER BY a.attendance_date ASC, e.last_name ASC, e.first_name ASC LIMIT 500";

$stmt_attendance = $db->prepare($sql_attendance);
$stmt_attendance->execute($params);
$attendance_records = $stmt_attendance->fetchAll(PDO::FETCH_ASSOC);

$monthly_summaries = []; // (placeholder for future total calculations)

// --- Employee list and selected label ---
$employees_stmt = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status='active' ORDER BY last_name, first_name");
$employees_list = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);
$selected_employee_label = '';
foreach ($employees_list as $emp) {
    if ($filter_employee_nin === $emp['nin']) {
        $selected_employee_label = htmlspecialchars("{$emp['first_name']} {$emp['last_name']} ({$emp['nin']})");
        break;
    }
}

include __DIR__ . '../../../../includes/header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion de la Présence (<?= htmlspecialchars($month_name_display . ' ' . $filter_year) ?>)</h1>
        <div>
            <a href="<?= route('attendance_export_pdf', [
                        'nin' => $filter_employee_nin,
                        'year' => $filter_year,
                        'month' => $filter_month
                    ]) ?>"
               class="btn btn-danger mb-3" target="_blank">
                <i class="bi bi-printer"></i> Imprimer la présence (<?= $month_name_display ?> <?= $filter_year ?>)
            </a>
        </div>
    </div>

    <?php foreach (['success', 'error', 'info'] as $alert_type):
        if (isset($_SESSION[$alert_type])): ?>
            <div class="alert alert-<?= $alert_type === 'success' ? 'success' : ($alert_type === 'error' ? 'danger' : 'info') ?>">
                <?= $_SESSION[$alert_type]; unset($_SESSION[$alert_type]); ?>
            </div>
    <?php endif; endforeach; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-list-check me-1"></i> Registre pour <?= sprintf('%02d/%04d', $filter_month, $filter_year) ?> (max 500 lignes)</div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-3 align-items-end">
                <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($filter_employee_nin) ?>">
                <div class="col-md-3">
                    <label class="form-label">Employé</label>
                    <input type="text" class="form-control form-control-sm" value="<?= $selected_employee_label ?>" disabled>
                </div>
                <div class="col-md-2">
                    <label for="filter_year_input" class="form-label">Année</label>
                    <input type="number" name="year" id="filter_year_input" class="form-control form-control-sm" value="<?= $filter_year ?>" min="2000" max="2050" required>
                </div>
                <div class="col-md-3">
                    <label for="filter_month_input" class="form-label">Mois</label>
                    <select name="month" id="filter_month_input" class="form-control form-control-sm" required>
                        <?php for ($m = 1; $m <= 12; $m++) {
                            $month_loop_name_display = "Mois $m";
                            try {
                                $dateObjLoop = DateTime::createFromFormat('!m', $m);
                                if ($dateObjLoop && class_exists('IntlDateFormatter')) {
                                    $formatterLoop = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM');
                                    $month_loop_name_display = ucfirst($formatterLoop->format($dateObjLoop));
                                } else if ($dateObjLoop) {
                                    $month_loop_name_display = $dateObjLoop->format('F');
                                }
                            } catch(Exception $e){}
                            echo "<option value='$m' ".($filter_month == $m ? 'selected' : '').">".htmlspecialchars($month_loop_name_display)."</option>";
                        } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-info btn-sm w-100"><i class="bi bi-filter"></i> Filtrer</button>
                </div>
                <div class="col-md-2">
                    <a href="<?= route('attendance_history', ['employee_nin' => $filter_employee_nin]) ?>" class="btn btn-secondary btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-striped table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Employé</th>
                            <th>Statut Pointage</th>
                            <th>Travail WE/JF</th>
                            <th>Notes/Type Excel</th>
                            <th>HS Mens.</th>
                            <th>Retenue Mens.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_records)): ?>
                            <tr><td colspan="7" class="text-center">Aucun enregistrement.</td></tr>
                        <?php else: foreach ($attendance_records as $record):
                            $db_status_lc = strtolower($record['status'] ?? '');
                            $status_badge_class = 'bg-secondary text-white'; 
                            $status_display_text = htmlspecialchars(ucfirst(str_replace('_', ' ', $record['status'] ?? 'N/A')));
                            switch ($db_status_lc) {
                                case 'present': $status_badge_class = 'bg-success text-white'; $status_display_text = 'Présent (P)'; break;
                                case 'present_offday': $status_badge_class = 'bg-danger text-white'; $status_display_text = 'Présent jour off (TF/TW)'; break;
                                case 'absent_unjustified':
                                case 'absent_from_excel_anj':
                                    $status_badge_class = 'bg-danger text-white'; $status_display_text = 'Absent NJ (ANJ)'; break;
                                case 'absent_authorized_paid':
                                case 'absent_from_excel_aap':
                                    $status_badge_class = 'bg-warning text-dark'; $status_display_text = 'Absent AP (AAP)'; break;
                                case 'absent_authorized_unpaid':
                                case 'absent_from_excel_aanp':
                                    $status_badge_class = 'bg-secondary text-white'; $status_display_text = 'Absent ANP (AANP)'; break;
                                case 'sick_leave':
                                case 'maladie':
                                    $status_badge_class = 'bg-purple text-white'; $status_display_text = 'Maladie (M)'; break; 
                                case 'annual_leave':
                                case 'on_leave_from_excel_c':
                                    $status_badge_class = 'bg-info text-dark'; $status_display_text = 'Congé (C)'; break;
                                case 'maternity_leave':
                                case 'on_leave_from_excel_mt':
                                    $status_badge_class = 'bg-pink text-dark'; $status_display_text = 'Maternité (MT)'; break;
                                case 'training':
                                case 'on_leave_from_excel_f':
                                    $status_badge_class = 'bg-teal text-white'; $status_display_text = 'Formation (F)'; break;
                                case 'mission':
                                case 'on_leave_from_excel_ms':
                                    $status_badge_class = 'bg-orange text-white'; $status_display_text = 'Mission (MS)'; break;
                                case 'other_leave':
                                case 'on_leave_from_excel_x':
                                    $status_badge_class = 'bg-indigo text-white'; $status_display_text = 'Autre Congé (X)'; break;
                                case 'weekend': 
                                    $status_badge_class = 'bg-primary text-dark border'; $status_display_text = 'Repos (RC)'; break;
                                case 'holiday': 
                                    $status_badge_class = 'bg-primary text-dark border'; $status_display_text = 'Férié (JF)'; break;
                            }
                            $emp_nin_for_summary = $record['employee_nin'];
                            $monthly_hs_display = $monthly_summaries[$emp_nin_for_summary]['total_hs'] ?? '-';
                            $monthly_retenue_display = $monthly_summaries[$emp_nin_for_summary]['total_retenue'] ?? '-';
                            if (is_numeric($monthly_hs_display)) $monthly_hs_display = number_format((float)$monthly_hs_display, 2);
                            if (is_numeric($monthly_retenue_display)) $monthly_retenue_display = number_format((float)$monthly_retenue_display, 2);
                        ?>
                        <tr>
                            <td><?= formatDate($record['attendance_date'] ?? null, 'd/m/Y') ?></td>
                            <td>
                                <a href="<?= route('employees_view', ['nin' => $record['employee_nin']]) ?>">
                                    <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                </a>
                                <small class="text-muted d-block">(<?= htmlspecialchars($record['employee_nin']) ?>)</small>
                            </td>
                            <td><span class="badge <?= $status_badge_class ?>"><?= $status_display_text ?></span></td>
                            <td class="text-center">
                                <?php
                                if($db_status_lc === 'present_offday') echo '<span class="badge bg-danger text-white" title="Travail WE ou JF">TF/TW</span>';
                                if(!empty($record['is_weekend_work'])) echo '<span class="badge bg-warning text-dark" title="Travail Weekend">WE</span> ';
                                if(!empty($record['is_holiday_work'])) echo '<span class="badge bg-info text-white" title="Travail Jour Férié">JF</span>';
                                if(empty($record['is_weekend_work']) && empty($record['is_holiday_work']) && $record['status'] !== 'present' && $db_status_lc !== 'present_offday') echo '-';
                                if(empty($record['is_weekend_work']) && empty($record['is_holiday_work']) && ($record['status'] === 'present' || $db_status_lc === 'present_offday')) echo '<small class="text-muted">Normal</small>';
                                ?>
                            </td>
                            <td>
                                <small>
                                    <?php
                                    $type_note = trim($record['leave_type_if_absent'] ?? '');
                                    $note = trim($record['notes'] ?? '');
                                    $excel_code = null;
                                    if (preg_match('/\b([A-Z]{2,4})\b/', $type_note, $matches)) {
                                        $excel_code = $matches[1];
                                    } elseif (preg_match('/\b([A-Z]{2,4})\b/', $note, $matches)) {
                                        $excel_code = $matches[1];
                                    } elseif ($record['status'] === 'present') {
                                        $excel_code = 'P';
                                    } elseif ($record['status'] === 'present_offday') {
                                        if (stripos($type_note, 'TF') !== false) $excel_code = 'TF';
                                        elseif (stripos($type_note, 'TW') !== false) $excel_code = 'TW';
                                    } elseif ($record['status'] === 'annual_leave') $excel_code = 'C';
                                    elseif ($record['status'] === 'sick_leave' || $record['status'] === 'maladie') $excel_code = 'M';
                                    elseif ($record['status'] === 'weekend') $excel_code = 'RC';
                                    elseif ($record['status'] === 'holiday') $excel_code = 'JF';
                                    elseif ($record['status'] === 'absent_unjustified') $excel_code = 'ANJ';
                                    elseif ($record['status'] === 'absent_authorized_paid') $excel_code = 'AAP';
                                    elseif ($record['status'] === 'absent_authorized_unpaid') $excel_code = 'AANP';
                                    elseif ($record['status'] === 'maternity_leave') $excel_code = 'MT';
                                    elseif ($record['status'] === 'training') $excel_code = 'F';
                                    elseif ($record['status'] === 'mission') $excel_code = 'MS';
                                    elseif ($record['status'] === 'other_leave') $excel_code = 'X';

                                    if ($excel_code === 'TW') {
                                        $label = 'Présent sur jour férié (travail payé 175% + recup)';
                                        $badge_class = 'bg-danger badge';
                                        echo "<span class=\"$badge_class\">$label</span>";
                                    } elseif ($excel_code && isset($attendance_code_map[$excel_code])) {
                                        $label = $attendance_code_map[$excel_code]['label'];
                                        $badge_class = $attendance_code_map[$excel_code]['badge'] . " badge";
                                        echo "<span class=\"$badge_class\">$label</span>";
                                    } elseif ($note !== '') {
                                        echo nl2br(htmlspecialchars($note));
                                    } elseif ($type_note !== '') {
                                        echo nl2br(htmlspecialchars($type_note));
                                    } else {
                                        echo '';
                                    }
                                    ?>
                                </small>
                            </td>
                            <td class="text-center"><?= $monthly_hs_display ?>h</td>
                            <td class="text-center"><?= $monthly_retenue_display ?>h</td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <h5>Code des Statuts (légende):</h5>
                <ul>
                    <?php foreach ($attendance_code_map as $code => $desc) {
                        echo '<li><span class="badge '.$desc['badge'].'">'.$code.'</span> : '.$desc['label'].'</li>';
                    } ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<style> 
    .bg-purple { background-color: #6f42c1 !important; color: #fff !important; }
    .bg-pink { background-color: #e83e8c !important; color: #fff !important; }
    .bg-orange { background-color: #fd7e14 !important; color: #fff !important; }
    .bg-teal { background-color: #20c997 !important; color: #fff !important; }
    .bg-indigo { background-color: #6610f2 !important; color: #fff !important; }
    .badge.bg-info { color: #fff !important; } 
    .badge.bg-info.text-dark { color: #000 !important; }
</style>
<?php include __DIR__.'../../../../includes/footer.php'; ?>