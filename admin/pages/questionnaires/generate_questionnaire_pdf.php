<?php
ob_start(); // Catches any stray output that could corrupt the PDF

// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- Input Validation (REFACTORED) ---
// Use filter_input for better validation of integer IDs.
$q_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect with a flash message if the ID is missing or invalid.
if (!$q_id) {
    flash('error', 'ID de questionnaire invalide ou manquant.');
    header("Location: " . route('employees_list')); // Redirect to a safe page
    exit();
}

// --- Data Fetching (REFACTORED with better error handling) ---
try {
    // Fetch questionnaire data along with employee details and sanction reason
    $stmt = $db->prepare("
        SELECT q.*, e.first_name, e.last_name, e.position, s.reason AS sanction_reason, s.fault_date
        FROM employee_questionnaires q
        JOIN employees e ON q.employee_nin = e.nin
        LEFT JOIN employee_sanctions s ON q.sanction_id = s.id
        WHERE q.id = :q_id
    ");
    $stmt->execute([':q_id' => $q_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Redirect if the questionnaire was not found in the database.
    if (!$data) {
        flash('error', 'Le questionnaire demandé n\'a pas été trouvé.');
        header("Location: " . route('employees_list'));
        exit();
    }
    
    // Fetch company info for the header
    $company_stmt = $db->query("SELECT * FROM company_settings LIMIT 1");
    $company = $company_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("PDF Generation DB Error: " . $e->getMessage());
    flash('error', 'Une erreur de base de données est survenue lors de la récupération des informations.');
    header("Location: " . route('employees_list'));
    exit();
}


// --- PDF Generation ---

// Custom TCPDF class to standardize headers and footers
class QuestionnairePDF extends TCPDF {
    private $company_name;
    private $company_logo;

    public function setCompanyDetails($name, $logo) {
        $this->company_name = $name;
        // Use APP_ROOT for a more reliable file path (assuming defined in config.php)
        $logo_path = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($logo, '/') : $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logo, '/');
        if ($logo && file_exists($logo_path)) {
            $this->company_logo = $logo_path;
        } else {
            $this->company_logo = null;
        }
    }

    public function Header() {
        if ($this->company_logo) {
            $this->Image($this->company_logo, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 15, $this->company_name, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Line(10, 30, 200, 30);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// PDF generation starts here
$pdf = new QuestionnairePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set company details for the header
$pdf->setCompanyDetails($company['company_name'] ?? 'Votre Entreprise', $company['logo_path'] ?? '');

// Set document metadata
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($company['company_name'] ?? 'Service RH');
$pdf->SetTitle('Questionnaire - ' . htmlspecialchars($data['questionnaire_type']));
$pdf->SetSubject('Questionnaire pour ' . htmlspecialchars($data['first_name'] . ' ' . $data['last_name']));

// Set default PDF settings
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, 40, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page and content
$pdf->AddPage();
$pdf->SetFont('helvetica', 'BU', 16);
$pdf->Cell(0, 15, 'Questionnaire - ' . htmlspecialchars($data['questionnaire_type']), 0, 1, 'C');
$pdf->Ln(10);

// Basic Information Section
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 7, "Employé(e):", 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 7, htmlspecialchars($data['first_name'] . ' ' . $data['last_name']), 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 7, "Poste:", 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 7, htmlspecialchars($data['position']), 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 7, "Date :", 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 7, "____ / ____ / ________", 0, 1);
$pdf->Ln(10);
$pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX()+180, $pdf->GetY());
$pdf->Ln(5);

// Questions Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, "Questions", 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

$questions = json_decode($data['questions'], true) ?: []; // Use null coalescing operator for cleaner code

// If it's a sanction-related interview, prepend the context question
if ($data['questionnaire_type'] === 'Entretien préalable à une sanction' && !empty($data['sanction_reason'])) {
    $context_question = "Veuillez décrire votre version des faits concernant l'incident du " . formatDate($data['fault_date']) . " lié à : '" . htmlspecialchars($data['sanction_reason']) . "'.";
    array_unshift($questions, $context_question);
}

if (!empty($questions)) {
    foreach($questions as $index => $question) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell(0, 7, ($index + 1) . ". " . htmlspecialchars($question), 0, 'L', 0, 1);
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 40, "Réponse de l'employé(e):\n...................................................................................................................................................................................................................................................................................................................................................................................................................................................................................", 'B', 'L', 0, 1);
        $pdf->Ln(8);
    }
} else {
    $pdf->SetFont('helvetica', 'I', 11);
    $pdf->Cell(0, 10, "Aucune question définie pour ce questionnaire.", 0, 1);
}

$pdf->Ln(15);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(90, 10, "Signature de l'Employé(e)", 0, 0, 'C');
$pdf->Cell(90, 10, "Signature du Représentant RH", 0, 1, 'C');
$pdf->Cell(90, 10, "(Précédée de la mention 'Lu et approuvé')", 0, 0, 'C');

// --- Output the PDF ---
// Clean the output buffer before sending the PDF to prevent corruption
if (ob_get_length()) {
    ob_end_clean();
}

// 'I' sends the file inline to the browser
$pdf->Output('questionnaire_' . $data['employee_nin'] . '_' . $q_id . '.pdf', 'I');
exit(); // Ensure no other code runs after sending the PDF