<?php
ob_start(); // Catches any stray output
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    flash('error', "ID d'ordre de mission invalide.");
    header("Location: " . route('missions_list_missions'));
    exit();
}
$mission_id = (int)$_GET['id'];

// --- Data Fetching ---
$stmt = $db->prepare("SELECT mo.*, e.first_name, e.last_name, e.position, e.department FROM mission_orders mo JOIN employees e ON mo.employee_nin = e.nin WHERE mo.id = ?");
$stmt->execute([$mission_id]);
$mission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mission) {
    flash('error', "Ordre de mission non trouvé.");
    header("Location: " . route('missions_list_missions'));
    exit();
}

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Get the name of the user generating the document
$issuerName = 'Service RH';
if (isset($_SESSION['user_id'])) {
    $issuerStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $issuerStmt->execute([$_SESSION['user_id']]);
    $issuerName = $issuerStmt->fetchColumn() ?: $issuerName;
}


// --- PDF Generation using TCPDF ---
class MYPDF_Mission extends TCPDF {
    public array $companyData;
    public string $issuerName;
    public string $referenceNumber;

    // The constructor now accepts all necessary data
    public function __construct($company, $issuer, $reference) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->companyData = $company;
        $this->issuerName = $issuer;
        $this->referenceNumber = $reference;

        $this->SetCreator('Application SGRH');
        $this->SetAuthor($this->companyData['company_name'] ?? 'SGRH');
        $this->SetTitle('Ordre de Mission N°' . $this->referenceNumber);
        $this->SetMargins(15, 40, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(20);
        $this->SetAutoPageBreak(TRUE, 25);
    }

    public function Header() {
        if (!empty($this->companyData['logo_path']) && file_exists(APP_ROOT . '/' . $this->companyData['logo_path'])) {
            $this->Image(APP_ROOT . '/' . $this->companyData['logo_path'], 15, 1, 30);
        }
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, $this->companyData['company_name'] ?? 'NOM ENTREPRISE', 0, true, 'C', 0, '', 0, false, 'T', 'M');
        $this->SetFont('helvetica', '', 8);
        $this->Cell(0, 5, $this->companyData['address'] ?? 'Adresse complète', 0, true, 'C');
        $this->Ln(5);
       // $this->SetLineStyle(['width' => 0.5, 'color' => [0, 0, 0]]);
        $this->Line($this->GetX(), $this->GetY(), $this->getPageWidth() - $this->GetX(), $this->GetY());
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetLineStyle(['width' => 0.5, 'color' => [0, 0, 0]]);
        $this->Line($this->GetX(), $this->GetY(), $this->getPageWidth() - $this->GetX(), $this->GetY());
        $this->Ln(1);

        $this->Cell(60, 5, 'Réf: ' . htmlspecialchars($this->referenceNumber), 0, 0, 'L');
        $this->Cell(0, 5, 'Document émis par: ' . htmlspecialchars($this->issuerName), 0, 0, 'C');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 1, 'R');
    }
}

// --- Create PDF Instance and Content ---
$pdf = new MYPDF_Mission($company, $issuerName, $mission['reference_number']);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

$html = '<h1 style="text-align:center; font-size:16pt;">ORDRE DE MISSION</h1>
         <p style="text-align:right;">Réf: <strong>' . htmlspecialchars($mission['reference_number']) . '</strong></p>
         <p style="text-align:right;">Fait le: <strong>' . formatDate(date('Y-m-d')) . '</strong></p>
         <br><br>
         <p>Il est demandé à :<br>
            Mr./Mme./Mlle : <strong>' . htmlspecialchars($mission['first_name'] . ' ' . $mission['last_name']) . '</strong><br>
            Qualité : <strong>' . htmlspecialchars($mission['position']) . '</strong>
         </p>
         <p>De bien vouloir se rendre à : <strong>' . nl2br(htmlspecialchars($mission['destination'])) . '</strong></p>
         <p>Période du <strong>' . formatDate($mission['departure_date'], 'd/m/Y à H:i') . '</strong> au <strong>' . formatDate($mission['return_date'], 'd/m/Y à H:i') . '</strong>.</p>
         <p><u>Objet de la mission :</u></p>
         <div style="border:1px solid #333; padding:10px; background-color:#f9f9f9;">' . nl2br(htmlspecialchars($mission['objective'])) . '</div>';

if (!empty($mission['vehicle_registration'])) {
    $html .= '<p>Véhicule utilisé : <strong>' . htmlspecialchars($mission['vehicle_registration']) . '</strong></p>';
}

$html .= '<br><br><br><br>
         <table nobr="true" style="width:100%;">
             <tr>
                 <td style="width:50%; text-align:center;"><strong>L\'intéressé(e)</strong><br>(Lu et approuvé)</td>
                 <td style="width:50%; text-align:center;"><strong>La Direction</strong><br><small>Émis par: ' . htmlspecialchars($issuerName) . '</small></td>
             </tr>
         </table>';

$pdf->writeHTML($html, true, false, true, false, '');

// --- Output the PDF ---
if (ob_get_length()) {
    ob_end_clean();
}
$pdf_filename = 'Ordre_Mission_' . str_replace(['/', '\\'], '_', $mission['reference_number']) . '.pdf';
$pdf->Output($pdf_filename, 'I');

exit;