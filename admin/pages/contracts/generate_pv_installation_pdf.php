<?php
ob_start(); // <-- ADD THIS LINE AT THE VERY TOP

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}
// Ligne 9: Vérification des droits d'accès. Si l'utilisateur n'est pas RH ou admin, le script s'arrête.
redirectIfNotHR();

$contract_id = sanitize($_GET['id'] ?? '');
if (empty($contract_id)) {
    exit('Erreur: ID de contrat non valide.');
}

$db->beginTransaction();

try {
    $stmt = $db->prepare("SELECT * FROM contrats WHERE id = ? FOR UPDATE");
    $stmt->execute([$contract_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        throw new Exception('Contrat non trouvé.');
    }

    if (empty($contract['pv_reference_number'])) {
        $ref = generate_reference_number('PV', 'contrats', 'pv_reference_number', $db);
        $stmt_update = $db->prepare("UPDATE contrats SET pv_reference_number = ? WHERE id = ?");
        $stmt_update->execute([$ref, $contract_id]);
    } else {
        $ref = $contract['pv_reference_number'];
    }

    $employee_stmt = $db->prepare("SELECT first_name, last_name FROM employees WHERE nin = ?");
    $employee_stmt->execute([$contract['employe_nin']]);
    $employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        throw new Exception('Employé associé non trouvé.');
    }

    $company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        $company = ['company_name' => 'Votre Société', 'logo_path' => '', 'city' => 'Votre Ville', 'representative' => 'La Direction'];
    }

    $issuerName = 'Service RH';
    $issuerId = $_SESSION['user_id'] ?? null;
    if (!empty($issuerId)) {
        $issuerStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $issuerStmt->execute([$issuerId]);
        $issuerRow = $issuerStmt->fetch(PDO::FETCH_ASSOC);
        if ($issuerRow) $issuerName = $issuerRow['username'];
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    exit("Erreur lors de la préparation du document: " . $e->getMessage());
}

class CustomTCPDF extends TCPDF {
    private $company_name, $company_logo, $issuerName;

    public function setDetails($companyName, $logo, $issuer) {
        $this->company_name = $companyName;
        $this->issuerName = $issuer;
        $logo_full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logo, '/');
        $this->company_logo = ($logo && file_exists($logo_full_path)) ? $logo_full_path : null;
    }

    public function Header() {
        if ($this->company_logo) $this->Image($this->company_logo, 15, 5, 28);
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(50, 15);
        $this->Cell(0, 10, $this->company_name, 0, 1, 'L');
        $this->Line(15, 28, 195, 28);
    }

    public function Footer() {
        $this->SetY(-21);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Généré par: ' . $this->issuerName . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 6, 'PV d\'Installation - Document interne', 0, 1, 'C');
    }
}

$pdf = new CustomTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setDetails($company['company_name'], $company['logo_path'] ?? '', $issuerName);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle("PV d'Installation - " . $employee['last_name']);
$pdf->SetMargins(20, 38, 20);
$pdf->SetAutoPageBreak(true, 28);
$pdf->AddPage();

$pdf->SetY(40);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Réf : ' . $ref, 0, 'L');
$pdf->SetFont('helvetica', '', 11);
$pdf->MultiCell(0, 6, 'Fait à ' . htmlspecialchars($company['city']) . ', le ' . date('d/m/Y'), 0, 'R');

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Ln(10);
$pdf->Cell(0, 15, "PROCES-VERBAL D'INSTALLATION", 0, 1, 'C');
$pdf->Ln(15);

$pdf->SetFont('helvetica', '', 12);
$body = 'Conformément aux dispositions réglementaires en vigueur, nous certifions par le présent document avoir procédé à l\'installation de :';
$pdf->writeHTMLCell(0, 0, '', '', $body, 0, 1, 0, true, 'J', true);
$pdf->Ln(10);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'M/Mme ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']), 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('helvetica', '', 12);
$body2 = 'En sa qualité de <strong>' . htmlspecialchars($contract['poste']) . '</strong>, et ce, à compter du <strong>' . htmlspecialchars(formatDate($contract['date_debut'])) . '</strong>.';
$pdf->writeHTMLCell(0, 0, '', '', $body2, 0, 1, 0, true, 'J', true);
$pdf->Ln(5);

$body3 = 'En foi de quoi, ce procès-verbal est établi et signé pour servir et valoir ce que de droit.';
$pdf->writeHTMLCell(0, 0, '', '', $body3, 0, 1, 0, true, 'J', true);
$pdf->Ln(30);

$pdf->Cell(0, 10, 'Fait à ' . htmlspecialchars($company['city']) . ', le ' . date('d/m/Y'), 0, 1, 'R');
$pdf->Ln(15);
$pdf->Cell(0, 10, 'La Direction', 0, 1, 'R', 0, '', 0, false, 'T', 'M');

$y_qr = 240;
$urlNoti = route('contracts_generate_pv', ['id' => $contract_id ]);
$pdf->SetXY(20, $y_qr);
$pdf->write2DBarcode($urlNoti, 'QRCODE,H', 20, $y_qr, 25, 25, ['border' => 0]);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY(20, $y_qr + 27);
$pdf->SetXY(30, $y_qr + 31);

if (ob_get_length()) {
    ob_end_clean();
}
ob_clean(); // <-- ADD THIS LINE RIGHT BEFORE OUTPUT
$pdf->Output('PV_Installation_' . $employee['last_name'] . '_' . $ref . '.pdf', 'I');
exit;
?>