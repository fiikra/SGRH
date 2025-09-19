<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}



redirectIfNotHR();
$pageTitle = "Gestion de la Présence";

// Initialisation des filtres
$current_year_default = (int)date('Y');
$current_month_default = (int)date('m');
$filter_year = $current_year_default;
$filter_month_num = $current_month_default;

$filter_year_from_get = $_GET['year'] ?? null;
if ($filter_year_from_get !== null && filter_var($filter_year_from_get, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2050]])) {
    $filter_year = (int)$filter_year_from_get;
}
$filter_month_from_get = $_GET['month'] ?? null;
if ($filter_month_from_get !== null && filter_var($filter_month_from_get, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]])) {
    $filter_month_num = (int)$filter_month_from_get;
}
$filter_employee_nin = sanitize($_GET['employee_nin'] ?? '');
$filter_month_for_sql = sprintf('%04d-%02d', $filter_year, $filter_month_num);

$month_name_display = "Mois";
try {
    $dateObjDisp = DateTime::createFromFormat('!m', $filter_month_num);
    if ($dateObjDisp) {
        $month_name_display = class_exists('IntlDateFormatter')
            ? ucfirst((new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM'))->format($dateObjDisp))
            : $dateObjDisp->format('F');
    }
} catch(Exception $e) {}

include __DIR__. '../../../../includes/header.php';

// Légende des codes et classes de badge associées
//"Absence non justifier,Absence autorise paye,Absence autorise non payé,
//Maladie,Maternite,Formation,Mission,autre,1,P,Travaille feries,travaille weekend";

$attendance_code_map = [
    'P'    => ['label' => 'Présent (jour ouvré normal)', 'badge' => 'bg-success'],
    'Travaille feries'   => ['label' => 'Présent sur jour férié (travail payé 175% + recup)', 'badge' => 'bg-danger'],
    'travaille weekend'   => ['label' => 'Présent sur weekend*', 'badge' => 'bg-orange'],
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
    'Travaille feries' => ['label' => 'Présent Jour Férié (TF)', 'badge' => 'bg-danger'],
    'travaille weekend' => ['label' => 'Présent Weekend (TW)', 'badge' => 'bg-orange'],
];

// Récupérer les pointages
$sql_attendance = "SELECT a.*, e.first_name, e.last_name FROM employee_attendance a JOIN employees e ON a.employee_nin = e.nin";
$conditions = [];
$params = [];
if ($filter_employee_nin) {
    $conditions[] = "a.employee_nin = :nin";
    $params[':nin'] = $filter_employee_nin;
}
$conditions[] = "DATE_FORMAT(a.attendance_date, '%Y-%m') = :month";
$params[':month'] = $filter_month_for_sql;
if (!empty($conditions)) {
    $sql_attendance .= " WHERE " . implode(" AND ", $conditions);
}
$sql_attendance .= " ORDER BY a.attendance_date ASC, e.last_name ASC, e.first_name ASC LIMIT 500";
$stmt_attendance = $db->prepare($sql_attendance);
$stmt_attendance->execute($params);
$attendance_records = $stmt_attendance->fetchAll(PDO::FETCH_ASSOC);

$monthly_summaries = []; // Placeholder (à remplir si tu veux afficher des totaux)

$employees_stmt = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status='active' ORDER BY last_name, first_name");
$employees_list = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion de la Présence (<?= htmlspecialchars($month_name_display . ' ' . $filter_year) ?>)</h1>
        <div>
            <a href="<?= APP_LINK ?>/admin/index.php?route=attendance_generate_template&year=<?= $filter_year ?>&month=<?= $filter_month_num ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Modèle (<?= sprintf('%02d/%04d', $filter_month_num, $filter_year) ?>)
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['info'])): ?><div class="alert alert-info"><?= $_SESSION['info']; unset($_SESSION['info']); ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-upload me-1"></i> Importer Pointage pour <strong><?= sprintf('%02d/%04d', $filter_month_num, $filter_year) ?></strong></div>
        <div class="card-body">
            <form action="<?= APP_LINK ?>/admin/index.php?route=attendance_upload" method="post" enctype="multipart/form-data">
                <?php csrf_input(); // ✅ Correct: Just call the function here ?>
            <input type="hidden" name="year" value="<?= $filter_year ?>">
                <input type="hidden" name="month" value="<?= $filter_month_num ?>">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label for="attendance_file" class="form-label">Fichier Excel (.xlsx, .xls):</label>
                        <input class="form-control" type="file" id="attendance_file" name="attendance_file" accept=".xlsx, .xls" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Importer</button>
                    </div>
                </div>
                <small class="form-text text-muted mt-1">Fichier basé sur le modèle du mois sélectionné.</small>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-list-check me-1"></i> Registre pour <?= sprintf('%02d/%04d', $filter_month_num, $filter_year) ?> (max 500 lignes)</div>
        <div class="card-body">
            <form method="get" action="<?= APP_LINK ?>/admin/index.php" class="row g-3 mb-3 align-items-end">
                <?php csrf_input(); // ✅ Correct: Just call the function here ?>
            <input type="hidden" name="route" value="attendance_manage">
                <div class="col-md-3">
                    <label for="filter_employee_nin_select" class="form-label">Employé</label>
                    <select name="employee_nin" id="filter_employee_nin_select" class="form-select form-select-sm">
                        <option value=''>Tous</option>
                        <?php foreach ($employees_list as $emp): ?>
                            <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= $filter_employee_nin == $emp['nin'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name'].' ('.$emp['nin'].')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_year_input" class="form-label">Année</label>
                    <input type="number" name="year" id="filter_year_input" class="form-control form-control-sm" value="<?= $filter_year ?>" min="2000" max="2050" required>
                </div>
                <div class="col-md-3">
                    <label for="filter_month_input" class="form-label">Mois</label>
                    <select name="month" id="filter_month_input" class="form-control form-control-sm" required>
                        <?php for ($m = 1; $m <= 12; $m++):
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
                        ?>
                            <option value="<?= $m ?>" <?= $filter_month_num == $m ? 'selected' : '' ?>>
                                <?= htmlspecialchars($month_loop_name_display) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-info btn-sm w-100"><i class="bi bi-filter"></i> Filtrer</button>
                </div>
                <div class="col-md-2">
                    <a href="<?= APP_LINK ?>/admin/index.php?route=attendance_manage" class="btn btn-secondary btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a>
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
                        <?php else: ?>
                            <?php foreach ($attendance_records as $record): ?>
                                <?php
                                $db_status_lc = strtolower($record['status'] ?? '');
                                $status_badge_class = 'bg-secondary text-white';
                                $status_display_text = htmlspecialchars(ucfirst(str_replace('_', ' ', $record['status'] ?? 'N/A')));
                                switch ($db_status_lc) {
                                    case 'present': $status_badge_class = 'bg-success text-white'; $status_display_text = 'Présent (P)'; break;
                                    case 'present_offday': $status_badge_class = 'bg-danger text-white'; $status_display_text = 'Présent Jour Férié (TF)'; break;
                                    case 'present_weekend': $status_badge_class = 'bg-orange text-white'; $status_display_text = 'Présent Weekend (TW)'; break;
                                    case 'weekend': $status_badge_class = 'bg-primary text-dark border'; $status_display_text = 'Repos (RC)'; break;
                                    case 'absent_unjustified':
                                    case 'absent_from_excel_anj': $status_badge_class = 'bg-danger text-white'; $status_display_text = 'Absent NJ (ANJ)'; break;
                                    case 'absent_authorized_paid':
                                    case 'absent_from_excel_aap': $status_badge_class = 'bg-warning text-dark'; $status_display_text = 'Absent AP (AAP)'; break;
                                    case 'absent_authorized_unpaid':
                                    case 'absent_from_excel_aanp': $status_badge_class = 'bg-secondary text-white'; $status_display_text = 'Absent ANP (AANP)'; break;
                                    case 'sick_leave':
                                    case 'maladie': $status_badge_class = 'bg-purple text-white'; $status_display_text = 'Maladie (M)'; break;
                                    case 'annual_leave':
                                    case 'on_leave_from_excel_c': $status_badge_class = 'bg-info text-dark'; $status_display_text = 'Congé (C)'; break;
                                    case 'maternity_leave':
                                    case 'on_leave_from_excel_mt': $status_badge_class = 'bg-pink text-dark'; $status_display_text = 'Maternité (MT)'; break;
                                    case 'training':
                                    case 'on_leave_from_excel_f': $status_badge_class = 'bg-teal text-white'; $status_display_text = 'Formation (F)'; break;
                                    case 'mission':
                                    case 'on_leave_from_excel_ms': $status_badge_class = 'bg-orange text-white'; $status_display_text = 'Mission (MS)'; break;
                                    case 'other_leave':
                                    case 'on_leave_from_excel_x': $status_badge_class = 'bg-indigo text-white'; $status_display_text = 'Autre Congé (X)'; break;
                                    case 'holiday': $status_badge_class = 'bg-primary text-dark border'; $status_display_text = 'Férié (JF)'; break;
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
                                        <a href="<?= APP_LINK ?>/admin/index.php?route=employees_view&nin=<?= htmlspecialchars($record['employee_nin']) ?>">
                                            <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                        </a>
                                        <small class="text-muted d-block">(<?= htmlspecialchars($record['employee_nin']) ?>)</small>
                                    </td>
                                    <td><span class="badge <?= $status_badge_class ?>"><?= $status_display_text ?></span></td>
                                    <td class="text-center">
                                        <?php if($record['is_holiday_work'] == 1): ?>
                                            <span class="badge bg-info text-white" title="Travail Jour Férié">JF</span>
                                        <?php elseif($record['is_weekend_work'] == 1): ?>
                                            <span class="badge bg-warning text-dark" title="Travail Weekend">WE</span>
                                        <?php else: ?>
                                            <small class="text-muted">Normal</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php
                                            $type_note = trim($record['leave_type_if_absent'] ?? '');
                                            $note = trim($record['notes'] ?? '');
                                            $excel_code = null;
                                            if (preg_match('/\b(ANJ|AAP|AANP|M|MT|F|MS|X|TF|TW|C|RC|JF|P)\b/', $type_note, $matches)) {
                                                $excel_code = $matches[1];
                                            } elseif (preg_match('/\b(ANJ|AAP|AANP|M|MT|F|MS|X|TF|TW|C|RC|JF|P)\b/', $note, $matches)) {
                                                $excel_code = $matches[1];
                                            }
                                            if (!$excel_code) {
                                                switch ($record['status']) {
                                                    //"Absence non justifier,Absence autorise paye,Absence autorise non payé,
//Maladie,Maternite,Formation,Mission,autre,1,P,Travaille feries,travaille weekend";
                                                    case 'present': $excel_code = 'P'; break;
                                                    case 'present_offday': $excel_code = 'Travaille feries'; break;
                                                    case 'present_weekend': $excel_code = 'travaille weekend'; break;
                                                    case 'weekend': $excel_code = 'weekend'; break;
                                                    case 'holiday': $excel_code = 'Jour Feries'; break;
                                                    case 'annual_leave': $excel_code = 'Conges'; break;
                                                    case 'sick_leave': case 'maladie': $excel_code = 'Maladie'; break;
                                                    case 'absent_unjustified': $excel_code = 'Absence non justifier'; break;
                                                    case 'absent_authorized_paid': $excel_code = 'Absence autorise paye'; break;
                                                    case 'absent_authorized_unpaid': $excel_code = 'Absence autorise non payé'; break;
                                                    case 'maternity_leave': $excel_code = 'Maternite'; break;
                                                    case 'training': $excel_code = 'Formation'; break;
                                                    case 'mission': $excel_code = 'Mission'; break;
                                                    case 'other_leave': $excel_code = 'autre'; break;
                                                }
                                            }
                                            if ($excel_code && isset($attendance_code_map[$excel_code])) {
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
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <h5>Code des Statuts (légende):</h5>
                <ul>
                    <li><span class="badge bg-success">P</span> : Présent (jour ouvré normal)</li>
                    <li><span class="badge bg-danger">TF</span> : Présent sur jour férié (travail payé 175% + recup)</li>
                    <li><span class="badge bg-orange">TW</span> : Présent sur weekend*</li>
                    <li><span class="badge bg-info text-dark">C</span> : Congé Annuel</li>
                    <li><span class="badge bg-purple">M</span> : Maladie</li>
                    <li><span class="badge bg-primary text-dark border">RC</span> : Repos (Weekend)</li>
                    <li><span class="badge bg-primary text-dark border">JF</span> : Jour Férié</li>
                    <li><span class="badge bg-danger">ANJ</span> : Absent non justifié</li>
                    <li><span class="badge bg-warning text-dark">AAP</span> : Absent autorisé payé</li>
                    <li><span class="badge bg-secondary">AANP</span> : Absent autorisé non payé</li>
                    <li><span class="badge bg-pink text-dark">MT</span> : Maternité</li>
                    <li><span class="badge bg-teal">F</span> : Formation</li>
                    <li><span class="badge bg-orange">MS</span> : Mission</li>
                    <li><span class="badge bg-indigo">X</span> : Autre absence</li>
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