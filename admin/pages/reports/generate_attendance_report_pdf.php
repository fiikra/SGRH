<?php


ob_start();

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}


// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
redirectIfNotHR();

// --- Input Validation ---
$nin = sanitize($_GET['nin'] ?? '');
$filter_month_str = sanitize($_GET['month'] ?? ''); // Expected: YYYY-MM

if (empty($nin) || !preg_match('/^\d{4}-\d{2}$/', $filter_month_str)) {
    flash('error', "Informations manquantes ou invalides pour générer le rapport.");
    header("Location: " . route('employees_list'));
    exit();
}

// --- Date Processing ---
$report_year = (int)substr($filter_month_str, 0, 4);
$report_month_num = (int)substr($filter_month_str, 5, 2);
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $report_month_num, $report_year);
$dateObj = DateTime::createFromFormat('!m', $report_month_num);
$formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM');
$report_month_name = ucfirst($formatter->format($dateObj));

// --- Data Fetching ---
$stmt_employee = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt_employee->execute([$nin]);
$employee = $stmt_employee->fetch(PDO::FETCH_ASSOC);

$company_stmt = $db->query("SELECT * FROM company_settings LIMIT 1");
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

$attendance_sql = "SELECT * FROM employee_attendance WHERE employee_nin = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? ORDER BY attendance_date ASC";
$attendance_stmt = $db->prepare($attendance_sql);
$attendance_stmt->execute([$nin, $filter_month_str]);
$attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$employee) {
    flash('error', "Employé non trouvé.");
    header("Location: " . route('employees_list'));
    exit();
}

// --- Summary Calculation ---
$summary = array_fill_keys(['worked', 'annual', 'sick', 'maternity', 'training', 'mission', 'other', 'aap', 'aanp', 'anj', 'tf_tw'], 0);
foreach ($attendance_records as $record) {
    $status = strtolower($record['status'] ?? '');
    switch ($status) {
        case 'present': $summary['worked']++; break;
        case 'present_offday': case 'present_weekend': $summary['tf_tw']++; break;
        case 'annual_leave': $summary['annual']++; break;
        case 'sick_leave': $summary['sick']++; break;
        case 'maternity_leave': $summary['maternity']++; break;
        case 'training': $summary['training']++; break;
        case 'mission': $summary['mission']++; break;
        case 'other_leave': $summary['other']++; break;
        case 'absent_authorized_paid': $summary['aap']++; break;
        case 'absent_authorized_unpaid': $summary['aanp']++; break;
        case 'absent_unjustified': $summary['anj']++; break;
    }
}

// --- Fetch Monthly Financials ---
$financial_stmt = $db->prepare("SELECT total_hs_hours, total_retenu_hours FROM employee_monthly_financial_summary WHERE employee_nin = ? AND period_year = ? AND period_month = ?");
$financial_stmt->execute([$nin, $report_year, $report_month_num]);
$financials = $financial_stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_hs_hours' => 0, 'total_retenu_hours' => 0];


// --- PDF Generation ---
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator($company['company_name']);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle("Relevé de Présence - {$employee['last_name']}");
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Header
if (!empty($company['logo_path']) && file_exists(ROOT_PATH . $company['logo_path'])) {
    $pdf->Image(ROOT_PATH . $company['logo_path'], 15, 10, 30);
    $pdf->SetY(10); $pdf->SetX(48);
}
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 5, htmlspecialchars($company['company_name']), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 4, htmlspecialchars($company['address']), 0, 1, 'L');

// Title
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, "RELEVÉ DE PRÉSENCE MENSUEL", 0, 1, 'C');
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 7, "Employé: " . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']), 0, 1, 'C');
$pdf->Cell(0, 7, "Mois: " . htmlspecialchars($report_month_name . ' ' . $report_year), 0, 1, 'C');
$pdf->Ln(5);

// Summary Table
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, "RÉSUMÉ DU MOIS:", 0, 1, 'L');
$summaryHtml = '<table border="0" cellpadding="2" style="font-size:9pt;">
    <tr><td width="60%">Jours Travaillés (P) :</td><td><b>' . $summary['worked'] . '</b> jour(s)</td></tr>
    <tr><td>Jours travaillés WE/JF (TF/TW) :</td><td><b>' . $summary['tf_tw'] . '</b> jour(s)</td></tr>
    <tr><td>Absences Justifiées & Congés :</td><td><b>' . ($summary['annual'] + $summary['sick'] + $summary['maternity'] + $summary['training'] + $summary['mission'] + $summary['other'] + $summary['aap'] + $summary['aanp']) . '</b> jour(s)</td></tr>
    <tr><td>Absences Non Justifiées (ANJ) :</td><td><b style="color:#b30000;">' . $summary['anj'] . '</b> jour(s)</td></tr>
    <tr><td colspan="2"><hr></td></tr>
    <tr><td>Heures Supplémentaires (HS) :</td><td><b>' . number_format($financials['total_hs_hours'], 2) . '</b> h</td></tr>
    <tr><td>Heures de Retenue :</td><td><b>' . number_format($financials['total_retenu_hours'], 2) . '</b> h</td></tr>
</table>';
$pdf->writeHTML($summaryHtml, true, false, true, false, '');
$pdf->Ln(5);

// Daily Details Table
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, "DÉTAIL JOURNALIER:", 0, 1, 'L');

$tableHtml = '<table border="1" cellpadding="3" cellspacing="0" style="font-size:8.5pt;">
    <tr bgcolor="#EEEEEE" style="font-weight:bold; text-align:center;">
        <th width="15%">Date</th><th width="15%">Jour</th><th width="30%">Statut</th><th width="40%">Note</th>
    </tr>';

// [PERFORMANCE] Create a map for instant lookups
$attendance_map = [];
foreach ($attendance_records as $record) {
    $attendance_map[$record['attendance_date']] = $record;
}

for ($day = 1; $day <= $days_in_month; $day++) {
    $current_date_str = sprintf('%04d-%02d-%02d', $report_year, $report_month_num, $day);
    $date = new DateTime($current_date_str);
    $day_name = (new IntlDateFormatter('fr_FR', 0, 0, null, null, 'EEEE'))->format($date);
    
    $record = $attendance_map[$current_date_str] ?? null;
    $status_display = '<span style="color:#888;">Non Pointé</span>';
    $row_style = '';
    $notes = '';

    if ($record) {
        $status = strtolower($record['status']);
        $notes = htmlspecialchars($record['notes'] ?? '');
        // Helper to create status display
        list($text, $color, $bg) = getAttendanceStatusDisplay($status);
        $status_display = "<span style=\"font-weight:bold; color:$color;\">$text</span>";
        $row_style = "background-color:$bg;";
    }
    
    $tableHtml .= "<tr style=\"$row_style\">
        <td style=\"text-align:center;\">" . $date->format('d/m/Y') . "</td>
        <td style=\"text-align:center;\">" . ucfirst($day_name) . "</td>
        <td style=\"text-align:center;\">$status_display</td>
        <td>" . nl2br($notes) . "</td>
    </tr>";
}
$tableHtml .= '</table>';
$pdf->writeHTML($tableHtml, true, false, true, false, '');

ob_end_clean();
$pdf_filename = "releve_presence_" . $employee['last_name'] . "_" . $filter_month_str . ".pdf";
$pdf->Output($pdf_filename, 'I');
exit;


// Helper function for styling the daily table
function getAttendanceStatusDisplay($status) {
    switch ($status) {
        case 'present': return ['Présent (P)', '#007800', '#e6ffe6'];
        case 'present_offday':
        case 'present_weekend': return ['Présent WE/JF', '#a86f00', '#fff3cd'];
        case 'absent_unjustified': return ['Absent NJ (ANJ)', '#b30000', '#ffd6d6'];
        case 'annual_leave': return ['Congé (C)', '#0d6efd', '#e7f1ff'];
        case 'sick_leave': return ['Maladie (M)', '#6f42c1', '#f1e4ff'];
        case 'weekend': return ['Repos (RC)', '#555', '#f8f9fa'];
        case 'holiday': return ['Férié (JF)', '#555', '#f8f9fa'];
        default: return [ucfirst(str_replace('_', ' ', $status)), '#444', '#f8f9fa'];
    }
}