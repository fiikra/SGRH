<?php
// Start output buffering immediately to catch any stray output
ob_start();

if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
redirectIfNotHR();

require_once __DIR__ . '/../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

if (!isset($_GET['nin'])) { exit('NIN required.'); }
$nin = sanitize($_GET['nin']);
$employee = $employeeModel->getEmployeeByNin($nin);
if (!$employee) { exit('Employee not found.'); }

$company = $employeeModel->getCompanySettings();
$profileUrl = route('employees_public', ['nin' => $employee['nin']]);

// --- TCPDF LOGIC ---
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle('Fiche d\'Employé - ' . $employee['first_name'] . ' ' . $employee['last_name']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Use PROJECT_ROOT for reliable absolute paths
$logoPath = PROJECT_ROOT . '/' . ltrim($company['logo_path'], '/');
if (!empty($company['logo_path']) && file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 5, 30);
}

$pdf->SetFont('helvetica', '', 8);
$pdf->SetXY(15, 30);
$pdf->Cell(0, 4, $company['company_name'], 0, 1, 'L');
$pdf->Cell(0, 4, $company['address'], 0, 1, 'L');
$pdf->Cell(0, 4, 'Tél: ' . $company['phone'], 0, 1, 'L');
$pdf->Cell(0, 4, 'Email: ' . $company['email'], 0, 1, 'L');

$photoPath = PROJECT_ROOT . '/' . ltrim($employee['photo_path'], '/');
if (!empty($employee['photo_path']) && file_exists($photoPath)) {
    $pdf->Image($photoPath, 160, 15, 40, 40);
} else {
    $pdf->Rect(160, 15, 40, 40, 'D');
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetXY(160, 15 + 15);
    $pdf->Cell(40, 6, 'Pas de photo', 0, 1, 'C');
}

$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetY(70);
$pdf->Cell(0, 10, 'FICHE D\'EMPLOYÉ', 0, 1, 'C');
$pdf->SetLineWidth(0.3);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

function addSection($pdf, $title, $data) {
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, $title, 0, 1, 'L', 1);
    $pdf->Ln(1);
    foreach ($data as $label => $value) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(50, 6, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 6, $value ?? 'N/A', 0, 'L');
    }
    $pdf->Ln(4);
}

$personal = ['NIN' => $employee['nin'], 'NSS' => $employee['nss'], /* ... other data ... */];
addSection($pdf, 'INFORMATIONS PERSONNELLES', $personal);

$pro = ['Poste' => $employee['position'], 'Département' => $employee['department'], /* ... other data ... */];
addSection($pdf, 'INFORMATIONS PROFESSIONNELLES', $pro);

$pdf->write2DBarcode($profileUrl, 'QRCODE,H', 165, $pdf->GetY(), 25, 25);

// Clean (erase) the output buffer before sending the PDF
ob_end_clean();

$filename = 'fiche_employe_' . $employee['nin'] . '.pdf';
$pdf->Output($filename, 'I');
exit();