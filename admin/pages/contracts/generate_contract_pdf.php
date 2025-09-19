<?php
ob_start(); // <-- ADD THIS LINE AT THE VERY TOP

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}
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

    if (empty($contract['reference_number'])) {
        $ref = generate_reference_number('CT', 'contrats', 'reference_number', $db);
        $stmt_update = $db->prepare("UPDATE contrats SET reference_number = ? WHERE id = ?");
        $stmt_update->execute([$ref, $contract_id]);
    } else {
        $ref = $contract['reference_number'];
    }

    $employee_stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
    $employee_stmt->execute([$contract['employe_nin']]);
    $employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        throw new Exception('Employé associé non trouvé.');
    }
    
    $company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        $company = ['company_name' => 'Votre Société', 'logo_path' => '', 'city' => 'Votre Ville', 'address' => 'Votre Adresse'];
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
        $this->SetXY(50, 10);
        $this->Cell(0, 10, $this->company_name, 0, 1, 'L');
        $this->Line(15, 28, 195, 28);
    }

    public function Footer() {
        $this->SetY(-21);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Généré par: ' . $this->issuerName . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 6, 'Contrat de Travail - Document confidentiel', 0, 1, 'C');
    }
}

$pdf = new CustomTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setDetails($company['company_name'], $company['logo_path'] ?? '', $issuerName);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle('Contrat de Travail - ' . $employee['last_name']);
$pdf->SetMargins(20, 38, 20);
$pdf->SetAutoPageBreak(true, 28);
$pdf->AddPage();

$pdf->SetY(40);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Réf : ' . $ref, 0, 'L');
$pdf->SetFont('helvetica', '', 11);
$pdf->MultiCell(0, 6, 'Fait à ' . htmlspecialchars($company['city']) . ', le ' . date('d/m/Y'), 0, 'R');

$contract_type_full = 'À Durée ' . (strtoupper($contract['type_contrat']) == 'CDI' ? 'Indéterminée (CDI)' : 'Déterminée');
$html = '<h1 style="text-align:center;">Contrat de Travail ' . $contract_type_full . '</h1><br/><br/>';

$html .= '<p><strong>Entre les soussignés :</strong></p>';
$html .= '<p><strong>' . htmlspecialchars($company['company_name']) . '</strong><br/>Adresse: ' . htmlspecialchars($company['address']) . '</p>';
$html .= '<p><em>Ci-après dénommée "l\'Employeur",</em></p><br/>';

$html .= '<p><strong>Et :</strong></p>';
$html .= '<p><strong>M/Mme ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</strong><br/>';
$html .= 'Né(e) le: ' . htmlspecialchars(formatDate($employee['birth_date'])) . ' à ' . htmlspecialchars($employee['birth_place']) . '<br/>';
$html .= 'Demeurant à: ' . htmlspecialchars($employee['address']) . '<br/>';
$html .= 'NIN: ' . htmlspecialchars($employee['nin']) . '</p>';
$html .= '<p><em>Ci-après dénommé(e) "le/la Salarié(e)",</em></p><br/><br/><p>Il a été convenu et arrêté ce qui suit :</p>';

$html .= '<h3>Article 1: Engagement et Fonctions</h3><p>Le/la Salarié(e) est engagé(e) par l\'Employeur en qualité de <strong>' . htmlspecialchars($contract['poste']) . '</strong>.</p>';
$html .= '<h3>Article 2: Durée et Prise d\'Effet</h3><p>Le présent contrat prend effet à compter du <strong>' . htmlspecialchars(formatDate($contract['date_debut'])) . '</strong>.</p>';

if (strtoupper($contract['type_contrat']) != 'CDI' && !empty($contract['date_fin'])) {
    $html .= '<p>Il est conclu pour une durée déterminée qui s\'achèvera le <strong>' . htmlspecialchars(formatDate($contract['date_fin'])) . '</strong>.</p>';
}
if (!empty($contract['periode_essai_jours'])) {
    $html .= '<h3>Article 3: Période d\'essai</h3><p>Cet engagement est soumis à une période d\'essai de <strong>' . htmlspecialchars($contract['periode_essai_jours']) . ' jours</strong>.</p>';
}

$html .= '<h3>Article 4: Rémunération</h3><p>Le/la Salarié(e) percevra une rémunération brute mensuelle de <strong>' . number_format($contract['salaire_brut'], 2, ',', ' ') . ' DZD</strong>.</p>';
$html .= '<br/><br/><br/><p style="text-align:right;">Fait à ' . htmlspecialchars($company['city']) . ', le ' . date('d/m/Y') . '</p><br/><br/>';

$html .= '<table border="0" cellpadding="10"><tr><td style="text-align:center;"><strong>L\'Employeur</strong><br/>(Lu et approuvé)<br/><br/><br/></td><td style="text-align:center;"><strong>Le/la Salarié(e)</strong><br/>(Lu et approuvé)<br/><br/><br/></td></tr></table>';

$pdf->writeHTML($html, true, false, true, false, '');

$y_qr = 240;
$urlNoti = route('contracts_generate_pdf', ['id' => $contract_id]);
$pdf->SetXY(20, $y_qr);
$pdf->write2DBarcode($urlNoti, 'QRCODE,H', 20, $y_qr, 25, 25, ['border' => 0]);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY(20, $y_qr + 27);
$pdf->SetXY(30, $y_qr + 31);

if (ob_get_length()) {
    ob_end_clean();
}

$pdf->Output('Contrat_' . $employee['last_name'] . '_' . $ref . '.pdf', 'I');
exit;
?>