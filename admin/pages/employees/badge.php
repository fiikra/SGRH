<?php
ob_start(); // Start output buffering at the very top

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

if (!isset($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);
$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé.";
    header("Location: " . route('employees_list'));
    exit();
}

$company = $db->query("SELECT company_name, logo_path FROM company_settings LIMIT 1")->fetch();

// The data to be encoded in the QR code is now ONLY the employee's NIN.
$qrCodeData = $employee['nin'];

// PDF init
$pdf = new TCPDF('L', 'mm', [54, 85.6], true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle("Badge de " . $employee['first_name'] . " " . $employee['last_name']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(2, 2, 2);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// White background
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 85.6, 54, 'F');

// Watermark logo
if (!empty($company['logo_path']) && file_exists(ROOT_PATH . $company['logo_path'])) {
    $pdf->SetAlpha(0.1);
    $pdf->Image(ROOT_PATH . $company['logo_path'], 10, 8, 65, 0, '', '', '', false, 300, '', false, false, 0);
    $pdf->SetAlpha(1);
}

// Employee photo
$photo_w = 15;
$photo_h = 18;
$photo_x = 85.6 - $photo_w - 4;
$photo_y = 8;

if (!empty($employee['photo_path']) && file_exists(ROOT_PATH . $employee['photo_path'])) {
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Rect($photo_x - 1, $photo_y - 1, $photo_w + 2, $photo_h + 2, 'D');
    $pdf->Image(ROOT_PATH . $employee['photo_path'], $photo_x, $photo_y, $photo_w, $photo_h, '', '', '', true, 300, '', false, false, 0);
} else {
    $pdf->SetDrawColor(180, 180, 180);
    $pdf->Rect($photo_x, $photo_y, $photo_w, $photo_h, 'D');
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY($photo_x, $photo_y + 7);
    $pdf->Cell($photo_w, 5, 'PHOTO', 0, 0, 'C');
}

// Text info
$text_x = 6;
$text_y = 8;
$text_w = $photo_x - $text_x - 2;
$block_indent = $text_x + 2;

// Name
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetXY($text_x, $text_y);
$pdf->Cell($text_w, 6, strtoupper($employee['last_name']), 0, 2, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($text_w, 5, ucfirst($employee['first_name']), 0, 2, 'L');
$pdf->Ln(2);

// Department
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($block_indent, $pdf->GetY());
$pdf->Cell($text_w - 2, 4, 'DÉPARTEMENT :', 0, 2, 'L');
$pdf->SetFont('helvetica', '', 8);
$pdf->SetX($block_indent);
$pdf->Cell($text_w - 2, 4, $employee['department'], 0, 2, 'L');
$pdf->Ln(1);

// Position
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetX($block_indent);
$pdf->Cell($text_w - 2, 4, 'POSTE :', 0, 2, 'L');
$pdf->SetFont('helvetica', '', 8);
$pdf->SetX($block_indent);
$pdf->MultiCell($text_w - 2, 4, $employee['position'], 0, 'L');

// Separator line
$pdf->SetDrawColor(100, 100, 100);
$pdf->Line($text_x, 38, $photo_x - 1, 38);

// *** MODIFICATION CLÉ : Le QR code contient maintenant UNIQUEMENT le NIN ***
$pdf->write2DBarcode($qrCodeData, 'QRCODE,H', 3, 39, 13, 13, [
    'border' => 0,
    'padding' => 0,
    'fgcolor' => [0, 0, 0],
    'bgcolor' => false
]);

// ID at the bottom
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY(0, 49);
$pdf->Cell(85.6, 4, 'ID: ' . $employee['nin'], 0, 0, 'C');

// Border
$pdf->SetDrawColor(0, 0, 0);
$pdf->Rect(1, 1, 83.6, 52, 'D');

ob_end_clean(); // Clean any previous output before sending the PDF
$pdf->Output('badge_' . $employee['nin'] . '.pdf', 'I');
exit();
