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
    exit('Erreur: Référence de notification non valide.');
}

// --- Data Fetching ---
try {
    // Fetch all necessary data in a single transaction
    $stmt = $db->prepare("
        SELECT 
            tn.*, 
            e.first_name, e.last_name, e.gender, e.position,
            u.username as issuer_name
        FROM trial_notifications tn
        JOIN employees e ON tn.employee_nin = e.nin
        LEFT JOIN users u ON tn.created_by = u.id
        WHERE tn.reference_number = ?
    ");
    $stmt->execute([$ref]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception('Notification non trouvée ou données associées manquantes.');
    }

    $company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        // Provide default values if company settings are missing
        $company = ['company_name' => 'Votre Société', 'logo_path' => '', 'city' => 'Votre Ville', 'signature_path' => ''];
    }

} catch (Exception $e) {
    error_log("PDF Generation Error (Data Fetch): " . $e->getMessage());
    exit("Erreur lors de la préparation du document: " . $e->getMessage());
}

// --- Custom TCPDF Class ---
class NotificationPDF extends TCPDF {
    private $company_name, $company_logo;
    public function setCompanyDetails($name, $logo) {
        $this->company_name = $name;
        $logo_path = ROOT_PATH . ltrim($logo, '/');
        $this->company_logo = ($logo && file_exists($logo_path)) ? $logo_path : null;
    }
    public function Header() {
        if ($this->company_logo) $this->Image($this->company_logo, 15, 12, 28);
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(50, 15);
        $this->Cell(0, 10, $this->company_name, 0, 1, 'L');
        $this->Line(15, 28, 195, 28);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// --- Prepare and Generate PDF ---
$pdf = new NotificationPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setCompanyDetails($company['company_name'], $company['logo_path'] ?? '');
$pdf->SetCreator($company['company_name']);
$pdf->SetAuthor($company['company_name']);
$pdf->SetTitle("Notification Période d'Essai - $ref");
$pdf->SetMargins(20, 38, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// --- Build PDF Body ---
$gender = (strtolower($data['gender']) === 'female') ? 'Madame' : 'Monsieur';
$fullName = htmlspecialchars($data['first_name'] . ' ' . $data['last_name']);
$position = htmlspecialchars($data['position']);

switch ($data['decision']) {
    case 'confirm':
        $objet = "Confirmation au poste";
        $body = "$gender $fullName,<br><br>Nous avons le plaisir de vous informer que votre période d’essai est <b>terminée avec succès</b>. Vous êtes confirmé(e) au poste de <b>$position</b>.<br><br>Nous vous félicitons pour votre implication et vous souhaitons plein succès.";
        break;
    case 'renew':
        $objet = "Renouvellement de la période d’essai";
        $periodText = !empty($data['renew_period']) ? " pour une durée supplémentaire de <b>" . htmlspecialchars($data['renew_period']) . "</b>" : "";
        $body = "$gender $fullName,<br><br>Nous vous informons que votre période d’essai est <b>renouvelée</b>$periodText à compter de ce jour.<br><br>Nous vous invitons à poursuivre vos efforts.";
        break;
    case 'terminate':
        $objet = "Fin de contrat (Période d’essai non concluante)";
        $body = "$gender $fullName,<br><br>Nous vous informons que votre contrat de travail prend fin à l’issue de la période d’essai, jugée non concluante.<br><br>Nous vous souhaitons bonne continuation.";
        break;
    default:
        $objet = "Notification de Période d'Essai";
        $body = "$gender $fullName,<br><br>Notification relative à votre période d'essai.";
}

// --- Write Content to PDF ---
$pdf->SetY(40);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, ($company['city'] ? htmlspecialchars($company['city']) . ', ' : '') . 'le ' . date('d/m/Y'), 0, 1, 'R');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Réf : ' . htmlspecialchars($ref), 0, 1, 'R');
$pdf->Ln(10);

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, "Objet : $objet", 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetFont('helvetica', '', 11);
$pdf->writeHTML("<p style='line-height:1.7;'>$body</p>", true, false, true, false, '');

// Signature
$pdf->SetY(-70);
$pdf->Cell(0, 8, 'La Direction', 0, 1, 'R');
if (!empty($company['signature_path']) && file_exists(ROOT_PATH . ltrim($company['signature_path'], '/'))) {
    $pdf->Image(ROOT_PATH . ltrim($company['signature_path'], '/'), 145, $pdf->GetY(), 40);
}

// QR Code
$pdf->SetY(-50);
// [ROUTING] Use the route() function for the QR code URL
$qrCodeUrl = route('trial_notifications_generate_notification_pdf', ['ref' => $ref]);
$pdf->write2DBarcode($qrCodeUrl, 'QRCODE,H', 20, $pdf->GetY(), 20, 20);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetXY(42, $pdf->GetY() + 5);
$pdf->Cell(0, 5, 'Vérifier l\'authenticité du document', 0, 1, 'L');

ob_end_clean(); // Clean any previous output buffer
$pdf->Output("Notification_" . $data['last_name'] . "_" . $ref . ".pdf", 'I');
exit;