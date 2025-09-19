<?php
ob_start();

// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Input Validation ---
$ref = sanitize($_GET['ref'] ?? '');
if (empty($ref)) {
    exit('Erreur: Référence de sanction non valide.');
}

// --- Data Fetching ---
try {
    $stmt = $db->prepare("
        SELECT s.*, e.first_name, e.last_name, e.gender, e.address, e.position
        FROM employee_sanctions s 
        JOIN employees e ON s.employee_nin = e.nin 
        WHERE s.reference_number = ?
    ");
    $stmt->execute([$ref]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception('Sanction non trouvée.');
    }
    
    $company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        // Provide default values to prevent errors
        $company = ['company_name' => 'Votre Société', 'logo_path' => '', 'city' => 'Votre Ville', 'signature_path' => ''];
    }

} catch (Exception $e) {
    error_log("Sanction PDF Error (Data Fetch): " . $e->getMessage());
    exit("Erreur lors de la préparation du document: " . $e->getMessage());
}

// --- Custom TCPDF Class for Header/Footer ---
class SanctionPDF extends TCPDF {
    private $company_name, $company_logo;
    public function setCompanyDetails($name, $logo) {
        $this->company_name = $name;
        $logo_path = ROOT_PATH . ltrim($logo, '/');
        $this->company_logo = ($logo && file_exists($logo_path)) ? $logo_path : null;
    }
    public function Header() {
        if ($this->company_logo) {
            $this->Image($this->company_logo, 15, 12, 30);
        }
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(50, 15);
        $this->Cell(0, 15, $this->company_name, 0, 1, 'C');
        $this->Line(15, 30, 195, 30);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// --- PDF Generation ---
$pdf = new SanctionPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setCompanyDetails($company['company_name'], $company['logo_path'] ?? '');

$pdf->SetCreator($company['company_name']);
$pdf->SetAuthor('Service RH');
$pdf->SetTitle('Notification de Sanction - ' . $ref);
$pdf->SetMargins(20, 40, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// --- Build PDF Body ---
$gender_prefix = (strtolower($data['gender']) === 'female') ? 'Madame' : 'Monsieur';
$employee_full_name = htmlspecialchars($data['first_name'] . ' ' . $data['last_name']);
$employee_address = htmlspecialchars($data['address'] ?? 'N/A');

$sanction_labels = [
    'avertissement_verbal' => 'un avertissement verbal',
    'avertissement_ecrit' => 'un avertissement écrit (sanction du 1er degré)',
    'mise_a_pied_1' => 'une mise à pied disciplinaire d\'une durée de 1 jour (sanction du 2e degré)',
    'mise_a_pied_2' => 'une mise à pied disciplinaire d\'une durée de 2 jours',
    'mise_a_pied_3' => 'une mise à pied disciplinaire d\'une durée de 3 jours',
    'licenciement' => 'un licenciement pour faute grave (sanction du 3e degré)'
];
$sanction_text = $sanction_labels[$data['sanction_type']] ?? 'une sanction';

// --- Write Content to PDF ---
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 10, htmlspecialchars($data['reference_number']) ,0, 1, 'R');
$pdf->Cell(0, 10, htmlspecialchars($company['city']) . ', le ' . formatDate(date('Y-m-d')), 0, 1, 'R');
$pdf->Ln(10);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, $employee_full_name, 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);
$pdf->MultiCell(100, 6, $employee_address, 0, 'L');
$pdf->Ln(10);

$pdf->SetFont('helvetica', 'BU', 12);
$pdf->Cell(0, 10, "Objet : Notification de sanction disciplinaire", 0, 1, 'L');
$pdf->Ln(5);

$html = "<p>$gender_prefix,</p>
<p>Nous faisons suite aux faits survenus en date du <b>" . formatDate($data['sanction_date']) . "</b>, et détaillés comme suit :</p>
<blockquote>" . nl2br(htmlspecialchars($data['reason'])) . "</blockquote>
<p>Après vous avoir entendu(e) à ce sujet, nous vous notifions par la présente <b>" . $sanction_text . "</b>.</p>";

if ($data['sanction_type'] === 'licenciement') {
    $html .= "<p>Cette décision entraîne la rupture de votre contrat de travail à compter de la date de première présentation de ce courrier.</p>";
}

$html .= "<p>Nous espérons qu'une telle attitude ne se renouvellera pas et que vous ferez preuve d'un comportement professionnel irréprochable à l'avenir.</p>
<p>Veuillez agréer, $gender_prefix, l'expression de nos salutations distinguées.</p>";

$pdf->writeHTML('<div style="line-height: 1.6;">' . $html . '</div>', true, false, true, false, '');
$pdf->Ln(20);

// Signature
$pdf->SetY(-60);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, "La Direction", 0, 1, 'R');
if (!empty($company['signature_path']) && file_exists(ROOT_PATH . ltrim($company['signature_path'], '/'))) {
    $pdf->Image(ROOT_PATH . ltrim($company['signature_path'], '/'), 145, $pdf->GetY(), 40);
}

// QR Code pointing to the view route for verification
$qrCodeUrl = route('sanctions_view_sanction', ['id' => $sanction_id]);
$pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 20, -40, 20, 20, [], 'B');

ob_end_clean(); // Clean any previous output buffer
$pdf->Output('Notification_Sanction_' . $ref . '.pdf', 'I');
exit;