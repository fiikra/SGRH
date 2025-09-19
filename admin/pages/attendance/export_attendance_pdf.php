<?php
ob_start(); // Start output buffering at the very beginning
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();
require_once __DIR__ . '../../../../includes/attendance_functions.php';

// --- Parameter Validation ---
$nin = sanitize($_GET['nin'] ?? null);
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

if (!$nin || !$year || !$month) {
    flash('error', "Paramètres manquants ou invalides pour l'export PDF.");
    header('Location: ' . route('attendance_log'));
    exit();
}

// --- Data Fetching ---
$employee_stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$employee_stmt->execute([$nin]);
$employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

$company_stmt = $db->query("SELECT * FROM company_settings LIMIT 1");
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee || !$company) {
    flash('error', "Données de l'employé ou de l'entreprise introuvables.");
    header('Location: ' . route('attendance_log'));
    exit();
}

$attendance_records = fetchAttendanceData($db, $nin, $year, $month);
$summary = calculateAttendanceSummary($db, $nin, $year, $month);

// --- PDF Custom Class for Header/Footer ---
class AttendancePDF extends TCPDF {
    public $company;
    public $employee;
    public $period;

    //Page header
    public function Header() {
        $logo = $this->company['logo_path'] ?? '';
        if ($logo && file_exists(__DIR__ . '/../../../../' . $logo)) {
            $this->Image(__DIR__ . '/../../../../' . $logo, 15, 10, 30, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        $this->SetY(10);
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'FEUILLE DE PRÉSENCE MENSUELLE', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 8, htmlspecialchars($this->company['company_name']), 0, 1, 'C');
        $this->Ln(5);

        $header_data = '
        <table border="0" cellpadding="4" style="font-size: 10pt;">
            <tr>
                <td width="50%"><b>Employé:</b> ' . htmlspecialchars($this->employee['first_name'] . ' ' . $this->employee['last_name']) . '</td>
                <td width="50%"><b>NIN:</b> ' . htmlspecialchars($this->employee['nin']) . '</td>
            </tr>
            <tr>
                <td width="50%"><b>Poste:</b> ' . htmlspecialchars($this->employee['position']) . '</td>
                <td width="50%"><b>Période:</b> ' . htmlspecialchars($this->period) . '</td>
            </tr>
        </table>';
        $this->writeHTML($header_data, true, false, true, false, '');
        $this->Line(15, $this->getY(), $this->getPageWidth() - 15, $this->getY());
    }

    // Page footer
    public function Footer() {
        $this->SetY(-20);
        $this->Line(15, $this->getY(), $this->getPageWidth() - 15, $this->getY());
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, 'Généré le ' . date('d/m/Y à H:i'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, htmlspecialchars($this->company['company_name']), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// --- PDF Generation ---
$pdf = new AttendancePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->company = $company;
$pdf->employee = $employee;
$pdf->period = monthName($month) . ' ' . $year;

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle('Présence - ' . $employee['first_name'] . ' ' . $employee['last_name']);
$pdf->SetMargins(15, 55, 15); // Top margin must be larger than header height
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(20);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Summary Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'RÉSUMÉ DU MOIS', 0, 1, 'L');
$summaryHtml = '
<table border="1" cellpadding="5" style="width:100%;">
    <tr style="background-color:#EAEAEA; font-weight:bold; text-align:center;">
        <th>Jours Travaillés</th><th>Abs. Just. Payées</th><th>Abs. Just. Non-Payées</th><th>Abs. Non Just.</th><th>Congé Maladie</th><th>Congé Annuel</th>
    </tr>
    <tr style="text-align:center;">
        <td>' . $summary['worked_days'] . '</td>
        <td>' . $summary['absent_justified_paid'] . '</td>
        <td>' . $summary['absent_justified_unpaid'] . '</td>
        <td>' . $summary['absent_unjustified'] . '</td>
        <td>' . $summary['sick_leave'] . '</td>
        <td>' . $summary['annual_leave'] . '</td>
    </tr>
</table>';
$pdf->writeHTML($summaryHtml, true, false, true, false, '');
$pdf->Ln(8);

// Detailed Log
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'JOURNAL DÉTAILLÉ DES PRÉSENCES', 0, 1, 'L');
$html = '<table border="1" cellpadding="4" style="width:100%; font-size: 9pt;">
    <thead style="background-color:#EAEAEA; font-weight:bold; text-align:center;">
        <tr>
            <th width="15%">Date</th><th width="15%">Jour</th><th width="25%">Statut</th>
            <th width="10%">Entrée</th><th width="10%">Sortie</th><th width="25%">Notes</th>
        </tr>
    </thead>
    <tbody>';

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
for ($day = 1; $day <= $daysInMonth; $day++) {
    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $record = $attendance_records[$currentDate] ?? null;
    $dayInfo = getDayInfo($db, $currentDate, $employee, $record);
    $html .= '<tr>
        <td align="center">' . date('d/m/Y', strtotime($currentDate)) . '</td>
        <td align="center">' . htmlspecialchars($dayInfo['day_name']) . '</td>
        <td>' . htmlspecialchars($dayInfo['status_text']) . '</td>
        <td align="center">' . ($record && $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '--:--') . '</td>
        <td align="center">' . ($record && $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '--:--') . '</td>
        <td>' . htmlspecialchars($record['notes'] ?? '') . '</td>
    </tr>';
}
$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');

$filename = 'Feuille_Presence_' . $employee['nin'] . "_${year}-${month}.pdf";
ob_end_clean(); // Clean the buffer before outputting the PDF
$pdf->Output($filename, 'I');
exit();