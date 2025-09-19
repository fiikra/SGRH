<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- Data Fetching & Validation ---
$decision_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$decision_id) {
    flash('error', 'ID de décision invalide.');
    header("Location: " . route('promotions_index'));
    exit();
}

$stmt = $db->prepare("
    SELECT d.*, e.first_name, e.last_name, e.gender
    FROM promotion_decisions d 
    JOIN employees e ON d.employee_nin = e.nin 
    WHERE d.id = ?
");
$stmt->execute([$decision_id]);
$decision = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$decision) {
    flash('error', 'Décision non trouvée.');
    header("Location: " . route('promotions_index'));
    exit();
}

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

$issuerName = 'Service RH';
if (!empty($decision['created_by'])) {
    $issuerStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $issuerStmt->execute([$decision['created_by']]);
    $issuerName = $issuerStmt->fetchColumn() ?: $issuerName;
}

// --- PDF Generation Class ---
class DecisionPDF extends TCPDF {
    public array $company;
    public string $issuer;
    public string $referenceNumber;

    public function __construct($company, $issuer, $reference) {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->company = $company;
        $this->issuer = $issuer;
        $this->referenceNumber = $reference;
    }

    public function Header() {
        $logo_path = APP_ROOT . '/' . ltrim($this->company['logo_path'] ?? '', '/');
        if (!empty($this->company['logo_path']) && file_exists($logo_path)) {
            $this->Image($logo_path, 15, 5, 28);
        }
        $this->SetFont('helvetica', 'B', 16);
        $this->SetXY(50, 15);
        $this->Cell(0, 10, $this->company['company_name'] ?? 'NOM ENTREPRISE', 0, 1, 'L');
        $this->Line(15, 28, 195, 28);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Réf: ' . htmlspecialchars($this->referenceNumber) . ' | Généré par: '.htmlspecialchars($this->issuer).' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// --- PDF Preparation ---
$pdf = new DecisionPDF($company, $issuerName, $decision['reference_number']);

// --- Dynamic Content Generation ---
$ref = htmlspecialchars($decision['reference_number']);
$genderCher = (isset($decision['gender']) && strtolower($decision['gender']) === 'femme') ? 'Chère' : 'Cher';
$genderFormal = (isset($decision['gender']) && strtolower($decision['gender']) === 'femme') ? 'Madame' : 'Monsieur';
$fullName = htmlspecialchars($decision['last_name'] . ' ' . $decision['first_name']);

$decisionTitle = "Décision de Carrière";
$body = "Notification concernant une décision de carrière.";

// Safely format dates, providing a fallback
$effectiveDateFormatted = !empty($decision['effective_date']) ? formatDate($decision['effective_date']) : 'N/A';

switch ($decision['decision_type']) {
    case 'promotion_only':
        $decisionTitle = "décision de Promotion";
        $body = "Nous avons le plaisir de vous informer de votre promotion au poste de <strong>" . htmlspecialchars($decision['new_position']) . "</strong>. Cette promotion prendra effet à compter du <strong>" . $effectiveDateFormatted . "</strong>.";
        break;
    case 'promotion_salary':
        $decisionTitle = "décision de Promotion et Augmentation";
        $body = "Nous avons le plaisir de vous informer de votre promotion au poste de <strong>" . htmlspecialchars($decision['new_position']) . "</strong>. Cette promotion, qui prendra effet le <strong>" . $effectiveDateFormatted . "</strong>, s'accompagne d'une revalorisation de votre salaire qui s'élèvera à <strong>" . number_format($decision['new_salary'], 2, ',', ' ') . " DZD</strong> brut mensuel.";
        break;
    case 'salary_only':
        $decisionTitle = "décision d'Augmentation de Salaire";
        $body = "Suite à l'évaluation de vos performances, nous avons le plaisir de vous annoncer une revalorisation de votre salaire, qui s'élèvera à <strong>" . number_format($decision['new_salary'], 2, ',', ' ') . " DZD</strong> brut mensuel. Cette augmentation prendra effet le <strong>" . $effectiveDateFormatted . "</strong>.";
        break;
}

// --- Set PDF Metadata and Add Page ---
$pdf->SetTitle($decisionTitle . " ($ref)");
$pdf->SetCreator('Application SGRH');
$pdf->SetAuthor($company['company_name'] ?? '');
$pdf->SetMargins(20, 38, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// --- Write Content to PDF ---
$pdf->SetFont('helvetica', '', 11);
$issueDateFormatted = !empty($decision['issue_date']) ? formatDate($decision['issue_date']) : date('d/m/Y');
$pdf->Cell(0, 10, ($company['city'] ?? 'Alger') . ', le ' . $issueDateFormatted, 0, 1, 'R');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Réf : ' . $ref, 0, 1, 'L');
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, $decisionTitle, 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('helvetica', '', 12);
$html = "<p>" . $genderCher . " " . $genderFormal . " " . $fullName . ",</p>
         <p style=\"line-height: 1.6;\">$body</p>
         <p>Nous comptons sur votre engagement continu et nous vous renouvelons notre confiance.</p>
         <p>Veuillez agréer, " . $genderCher . " " . $genderFormal . ", l'expression de nos salutations distinguées.</p>";
$pdf->writeHTML($html, true, false, true, false, '');

$pdf->SetY($pdf->getPageHeight() - 60);
$pdf->Cell(0, 10, 'La Direction', 0, 1, 'R');

// --- Final Output to Browser ---
if (ob_get_length()) ob_end_clean();
$pdf->Output("Decision_" . $decision['last_name'] . "_" . $ref . ".pdf", 'I');
exit;