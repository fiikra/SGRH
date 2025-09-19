<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// Start output buffering immediately to catch any stray output
ob_start();

if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
redirectIfNotHR();

require_once __DIR__ . '../../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

if (empty($_GET['nin'])) {
    // Cannot set session messages here as they would be cleared by ob_end_clean()
    exit('Error: Employee NIN is required.');
}

$nin = sanitize($_GET['nin']);
$employee = $employeeModel->getEmployeeByNin($nin);
$company = $employeeModel->getCompanySettings();

if (!$employee) {
    exit('Error: Employee not found.');
}

// The data to be encoded in the QR code is the employee's NIN.
$qrCodeData = $employee['nin'];

// --- TCPDF Initialization ---
$pdf = new TCPDF('L', 'mm', [54, 85.6], true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name'] ?? 'Company Name');
$pdf->SetTitle("Badge de " . $employee['first_name'] . " " . $employee['last_name']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(2, 2, 2);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// --- Badge Design ---

// 1. Background
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 85.6, 54, 'F');

// 2. Watermark logo (using robust, absolute path)
$logoPath = PROJECT_ROOT . '/' . ltrim($company['logo_path'] ?? '', '/');
if (!empty($company['logo_path']) && file_exists($logoPath)) {
    $pdf->SetAlpha(0.1);
    $pdf->Image($logoPath, 10, 2, 65);
    $pdf->SetAlpha(1);
}

// 3. Employee photo (using robust, absolute path)
$photo_w = 20 ; $photo_h = 20; $photo_x = 85.6 - $photo_w - 4; $photo_y = 3;
$photoPath = PROJECT_ROOT . '/' . ltrim($employee['photo_path'] ?? '', '/');

if (!empty($employee['photo_path']) && file_exists($photoPath)) {
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Rect($photo_x - 0.5, $photo_y - 0.5, $photo_w + 1, $photo_h + 1, 'D'); // Frame
    $pdf->Image($photoPath, $photo_x, $photo_y, $photo_w, $photo_h, '', '', '', true);
} else {
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->Rect($photo_x, $photo_y, $photo_w, $photo_h, 'D');
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY($photo_x, $photo_y + 7);
    $pdf->Cell($photo_w, 5, 'PHOTO', 0, 0, 'C');
}

// 4. Text Information
$text_x = 6; $text_y = 3; $text_w = $photo_x - $text_x - 2;

// Name
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetXY($text_x, $text_y);
$pdf->Cell($text_w, 6, strtoupper($employee['last_name']), 0, 2, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($text_w, 5, ucfirst($employee['first_name']), 0, 2, 'L');
$pdf->Ln(2);

// Department
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetX($text_x);
$pdf->Cell($text_w, 4, 'DÉPARTEMENT', 0, 2, 'L');
$pdf->SetFont('helvetica', '', 8);
$pdf->SetX($text_x);
$pdf->Cell($text_w, 4, $employee['department'], 0, 2, 'L');
$pdf->Ln(1);

// Position
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetX($text_x);
$pdf->Cell($text_w, 4, 'POSTE', 0, 2, 'L');
$pdf->SetFont('helvetica', '', 8);
$pdf->SetX($text_x);
$pdf->MultiCell($text_w, 4, $employee['position'], 0, 'L');

// 5. QR Code and ID
$pdf->write2DBarcode($qrCodeData, 'QRCODE,H', 8, 33, 18, 18, ['border' => 0, 'padding' => 1]);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY(20, 49);
$pdf->Cell(0, 4, 'ID: ' . $employee['nin'], 0, 0, 'R');

// 6. Border
$pdf->SetDrawColor(0, 0, 0);
$pdf->Rect(1, 1, 83.6, 52, 'D');

// --- Final Output ---

// Clean (erase) any stray output from the buffer before sending the PDF
ob_end_clean();

// Generate the PDF and send it to the browser
$pdf->Output('badge_' . $employee['nin'] . '.pdf', 'I');
exit();