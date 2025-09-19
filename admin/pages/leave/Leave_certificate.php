<?php
// --- Security Headers: Set before any output ---
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
// Hide E_DEPRECATED notices, but show all other errors
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__.'../../../../lib/phpqrcode/qrlib.php';


redirectIfNotHR();

if (!isset($_GET['leave_id']) || !is_numeric($_GET['leave_id'])) {
    die("Paramètre manquant ou invalide.");
}
$leave_id = (int)$_GET['leave_id'];

// 1. Get leave info
$leaveStmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ? AND (status = 'approved' OR status = 'prise')");
$leaveStmt->execute([$leave_id]);
$leave = $leaveStmt->fetch(PDO::FETCH_ASSOC);
if (!$leave) die("Congé introuvable, non approuvé, ou déjà pris.");

// 2. Get employee info
$employeeStmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$employeeStmt->execute([$leave['employee_nin']]);
$employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) die("Employé introuvable.");

// 3. Get company info
$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$weekendDays = isset($company['weekend_days']) ? explode(',', $company['weekend_days']) : ['6','0'];

// 4. Calculate resumption date
if (!function_exists('getResumptionDate')) {
    if(file_exists('../../includes/specific_leave_functions.php')) {
         require_once '../../includes/specific_leave_functions.php';
    }
    if (!function_exists('getResumptionDate')) {
        die("Erreur: La fonction getResumptionDate n'est pas définie. Vérifiez includes/functions.php");
    }
}
$resumptionDate = getResumptionDate($leave['end_date'], $weekendDays);

// 5. Reliquat mention
$reliquatMention = "";
if ($leave['leave_type'] === 'reliquat' || (isset($leave['use_reliquat']) && $leave['use_reliquat'] > 0)) {
    $reliquatMention = " (Reliquat année précédente)";
}

// 6. Determine leave type string
switch (strtolower($leave['leave_type'])) {
    case 'annuel': $typeLabel = "Annuel"; break;
    case 'reliquat': $typeLabel = "Reliquat"; break;
    case 'recup': $typeLabel = "Récupération"; break;
    case 'unpaid': $typeLabel = "Sans Solde"; break;
    case 'anticipe': $typeLabel = "Anticipé"; break;
    case 'maternite': $typeLabel = "Maternité"; break;
    case 'maladie': case 'sick_leave': $typeLabel = "Maladie"; break;
    default: $typeLabel = ucfirst(htmlspecialchars($leave['leave_type'])); break;
}

// 7. Prep gendered strings
$prefix = 'M.';
if (isset($employee['gender'])) {
    $genderLower = strtolower($employee['gender']);
    if ($genderLower === 'femme' || $genderLower === 'f') $prefix = "Mme";
}
$genderedPronoun = (strtolower($employee['gender'] ?? '') === 'femme' || strtolower($employee['gender'] ?? '') === 'f') ? "née" : "né";
$genderedEmployee = (strtolower($employee['gender'] ?? '') === 'femme' || strtolower($employee['gender'] ?? '') === 'f') ? "employée" : "employé";
$genderedInterested = (strtolower($employee['gender'] ?? '') === 'femme' || strtolower($employee['gender'] ?? '') === 'f') ? "l'intéressée" : "l'intéressé";

// 8. Soldes restants
$recupStmt = $db->prepare("SELECT IFNULL(SUM(nb_jours),0) FROM employee_recup_days WHERE employee_nin = ? AND status = 'not_taked'");
$recupStmt->execute([$employee['nin']]);
$soldeRecup = floatval($recupStmt->fetchColumn());

$empSolde = $db->prepare("SELECT annual_leave_balance, remaining_leave_balance FROM employees WHERE nin = ?");
$empSolde->execute([$employee['nin']]);
$empSoldeArr = $empSolde->fetch(PDO::FETCH_ASSOC);
$soldeAnnuel = $empSoldeArr['annual_leave_balance'] ?? 0;
$soldeReliquat = $empSoldeArr['remaining_leave_balance'] ?? 0;

// --- Reference Number (TC-0001-2025-DX9B pattern, reset yearly, increment globally) ---
$reference_number = $leave['certificate_reference_number'];
$currentYear = date('Y');
if (empty($reference_number)) {
    // Get current max sequence for this year (across all employees)
    $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(certificate_reference_number, '-', 2), '-', -1) AS UNSIGNED)) 
        FROM leave_requests 
        WHERE certificate_reference_number IS NOT NULL
        AND SUBSTRING_INDEX(SUBSTRING_INDEX(certificate_reference_number, '-', 3), '-', -1) = ?");
    $stmt->execute([$currentYear]);
    $max_seq = $stmt->fetchColumn();
    $next_seq = ($max_seq !== null) ? (int)$max_seq + 1 : 1;
    $sequence_number = str_pad($next_seq, 4, '0', STR_PAD_LEFT);
    $random_part = substr(strtoupper(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ')), 0, 4);
    $reference_number = "TC-{$sequence_number}-{$currentYear}-{$random_part}";
    // Save it for future use
    $update_stmt = $db->prepare("UPDATE leave_requests SET certificate_reference_number = ? WHERE id = ?");
    $update_stmt->execute([$reference_number, $leave_id]);
}

// 9. QR code generation
$qrVerifyPath = defined('APP_LINK_QR_VERIFY') ? APP_LINK_QR_VERIFY : '/verify_document.php';
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_path_base = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return $protocol . $host . $script_path_base;
}
$qrUrl = route('leave_Leave_certificate', ['leave_id' => $leave['id']]);
$qrTempFile = tempnam(sys_get_temp_dir(), 'qr_leave_cert_') . '.png';
QRcode::png($qrUrl, $qrTempFile, QR_ECLEVEL_L, 3, 1);

// 10. Prepare PDF variables
$ville = !empty($company['city']) ? htmlspecialchars($company['city']) : '___________';
$dateAttestation = date('d/m/Y');
$titreDocument = "ATTESTATION DE CONGÉ";
$nomDocumentForPdfOutput = 'Attestation_Conge_' . preg_replace('/[^a-z0-9_]/i', '_', $employee['last_name'] ?? 'employe') . '_' . $leave_id . '_' . date('YmdHis') . '.pdf';

$leaveDurationText = "Durée N/A";
if (!empty($leave['start_date']) && !empty($leave['end_date'])) {
    try {
        $startDateObjCert = new DateTime($leave['start_date']);
        $endDateObjCert = new DateTime($leave['end_date']);
        $intervalCert = $startDateObjCert->diff($endDateObjCert);
        $daysCountCert = $intervalCert->days + 1;
        $leaveDurationText = $daysCountCert . ($daysCountCert > 1 ? " jours" : " jour") . " calendaires";
    } catch (Exception $e) { /* Handled by default */ }
}

// 11. Get issuer
$issuerName = "Service RH";
if (isset($_SESSION['user_id'])) {
    $issuerStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $issuerStmt->execute([$_SESSION['user_id']]);
    $issuerDb = $issuerStmt->fetch(PDO::FETCH_ASSOC);
    if ($issuerDb && !empty($issuerDb['username'])) {
        $issuerName = htmlspecialchars($issuerDb['username']);
    }
}

// --- Precompute variables for HTML content ---
$companyNameCert = htmlspecialchars($company['company_name'] ?? 'NOM DE LA SOCIÉTÉ');
$employeePrefixCert = htmlspecialchars($prefix);
$employeeFullNameCert = htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
$employeeGenderedPronounCert = htmlspecialchars($genderedPronoun);
$employeeBirthDateCert = formatDate($employee['birth_date']);
$employeeBirthPlaceCert = htmlspecialchars($employee['birth_place'] ?? '');
$employeeGenderedCert = htmlspecialchars($genderedEmployee);
$employeePositionCert = htmlspecialchars($employee['position'] ?? '');
$employeeHireDateCert = formatDate($employee['hire_date']);

$leaveTypeLabelCert = htmlspecialchars($typeLabel);
$leaveStartDateCert = formatDate($leave['start_date']);
$leaveEndDateCert = formatDate($leave['end_date']);
$leaveDurationTextCert = htmlspecialchars($leaveDurationText);
$leaveResumptionDateCert = formatDate($resumptionDate);
$genderedInterestedCert = htmlspecialchars($genderedInterested);

// --- Construct HTML Main Content ---
$htmlMainContent = '';
$htmlMainContent .= '<p style="text-align:right;">Réf: <strong>' . htmlspecialchars($reference_number) . '</strong></p>';
$htmlMainContent .= '<p style="text-align:right;">Date d\'émission: <strong>' . $dateAttestation . '</strong></p>';
$htmlMainContent .= '<br>';
$htmlMainContent .= '<p style="line-height: 1.4;">Nous soussignés, <b>' . $companyNameCert . '</b>, certifions par la présente que :</p>';
$htmlMainContent .= '<br>';
$htmlMainContent .= '<table cellpadding="4" cellspacing="0" border="0" style="width:100%; font-size:11pt;">';
$htmlMainContent .= '<tr><td width="35%"><b>' . $employeePrefixCert . ' Nom et Prénom</b></td><td width="65%">: ' . $employeePrefixCert . ' ' . $employeeFullNameCert . '</td></tr>';
$htmlMainContent .= '<tr><td><b>' . $employeeGenderedPronounCert . ' le</b></td><td>: ' . $employeeBirthDateCert . ' à ' . $employeeBirthPlaceCert . '</td></tr>';
$htmlMainContent .= '<tr><td><b>' . $employeeGenderedCert . ' en qualité de</b></td><td>: ' . $employeePositionCert . '</td></tr>';
$htmlMainContent .= '<tr><td><b>Date d\'embauche</b></td><td>: ' . $employeeHireDateCert . '</td></tr>';
$htmlMainContent .= '</table><br>';
$htmlMainContent .= '<p style="line-height: 1.4;">A bénéficié d\'un congé de type <b>' . $leaveTypeLabelCert . "</b>" . $reliquatMention . " pour la période suivante :</p><br>";
$htmlMainContent .= '<table cellpadding="4" cellspacing="0" border="0" style="width:100%; font-size:11pt;">';
$htmlMainContent .= '<tr><td width="35%"><b>Période du congé</b></td><td width="65%">: Du <b>' . $leaveStartDateCert . '</b> au <b>' . $leaveEndDateCert . '</b></td></tr>';
$htmlMainContent .= '<tr><td><b>Nombre de jours accordés</b></td><td>: ' . $leaveDurationTextCert . '</td></tr>';
$htmlMainContent .= '<tr><td><b>Date de reprise du travail</b></td><td>: <b>' . $leaveResumptionDateCert . '</b></td></tr>';
$htmlMainContent .= '</table><br>';
$htmlMainContent .= '<p style="line-height: 1.4;">Cette attestation est délivrée à la demande de ' . $genderedInterestedCert . ' pour servir et valoir ce que de droit.</p>';

// --- PDF Generation Class (MYPDF) ---
class MYPDF extends TCPDF {
    public $companyDataPdf;
    public $qrTempFilePdf;
    public $issuerNamePdf;
    public $referenceNumberPdf;

    public function __construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $companyIn, $qrPathIn, $issuerIn, $refNumIn) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
        $this->companyDataPdf = $companyIn;
        $this->qrTempFilePdf = $qrPathIn;
        $this->issuerNamePdf = $issuerIn;
        $this->referenceNumberPdf = $refNumIn;
        $this->SetMargins(15, 35, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(20);
        $this->SetAutoPageBreak(TRUE, 25);
    }

  public function Header() {
    $logoPath = $this->companyDataPdf['logo_path'] ?? '';
    // This corrected logic reliably finds the web root directory.
    $logoFullPath = !empty($logoPath) ? $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logoPath, '/') : '';

    if ($logoFullPath && file_exists($logoFullPath)) {
        $this->Image($logoFullPath, 15, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    } else {
        $this->SetFont('helvetica', 'B', 10);
        $this->SetXY(15,10);
        $this->Cell(30,15,'[Logo]',1,0,'C',0,'',0,false,'T','M');
    }

    $this->SetFont('dejavusans', 'B', 11);
    $this->SetXY(50, 10);
    $this->Cell(0, 7, strtoupper(htmlspecialchars($this->companyDataPdf['company_name'] ?? 'NOM DE LA SOCIÉTÉ')), 0, 1, 'R');
    $this->SetFont('dejavusans', '', 8);
    $currentY = $this->GetY();
    if (!empty($this->companyDataPdf['address'])) $this->MultiCell(0, 4, htmlspecialchars($this->companyDataPdf['address']), 0, 'R', 0, 1, 50, $currentY); $currentY = $this->GetY();
    if (!empty($this->companyDataPdf['phone_number'])) $this->MultiCell(0, 4, "Tél : " . htmlspecialchars($this->companyDataPdf['phone_number']), 0, 'R', 0, 1, 50, $currentY); $currentY = $this->GetY();
    if (!empty($this->companyDataPdf['email'])) $this->MultiCell(0, 4, "Email : " . htmlspecialchars($this->companyDataPdf['email']), 0, 'R', 0, 1, 50, $currentY);

    $this->Ln(2);
    $this->Line($this->getX(), $this->getY(), $this->getPageWidth() - $this->getMargins()['right'], $this->getY());
    $this->Ln(3);
}

    public function Footer() {
        $this->SetY(-22);
        $this->SetFont('dejavusans', 'I', 7);
        $this->Line($this->getX(), $this->getY(), $this->getPageWidth() - $this->getMargins()['right'], $this->getY());
        $this->Ln(1);

        $leftText = 'Réf: ' . htmlspecialchars($this->referenceNumberPdf);
        $centerText = 'Document généré par ' . ($this->issuerNamePdf ?? 'Système') . ' le ' . date('d/m/Y H:i');
        $rightText = 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages();

        $this->Cell(60, 5, $leftText, 0, 0, 'L');
        $this->Cell(0, 5, $centerText, 0, 0, 'C');
        $this->Cell(0, 5, $rightText, 0, 1, 'R');

        $this->SetFont('dejavusans', '', 6);
        $this->Cell(0, 5, htmlspecialchars($this->companyDataPdf['company_name'] ?? '') . ' - RC: ' . htmlspecialchars($this->companyDataPdf['trade_register'] ?? '') . ' - NIF: ' . htmlspecialchars($this->companyDataPdf['tax_id'] ?? ''), 0, 0, 'C');

        if ($this->qrTempFilePdf && file_exists($this->qrTempFilePdf)) {
            $this->Image($this->qrTempFilePdf, $this->getPageWidth() - $this->getMargins()['right'] - 15, $this->getY() -15, 15, 15, 'PNG');
        }
    }
}

// --- Instantiate PDF Object ---
$pdf = new MYPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false, $company, $qrTempFile, $issuerName, $reference_number);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(htmlspecialchars($company['company_name'] ?? 'Société'));
$pdf->SetTitle($nomDocumentForPdfOutput);
$pdf->SetSubject($titreDocument);

$pdf->AddPage();
$pdf->SetFont('dejavusans', '', 11);

$pdf->SetY(max($pdf->GetY(), $pdf->getHeaderMargin() + 30));
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, $titreDocument, 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(0, 7, "Fait à " . $ville . ", le " . $dateAttestation, 0, 1, 'R');
$pdf->Ln(7);

$pdf->writeHTML($htmlMainContent, true, false, true, false, '');
$pdf->Ln(7);

if (strtolower($leave['leave_type']) !== 'unpaid') {
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(0, 7, "Soldes de congés restants à la date de cette attestation :", 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 10);

    $soldeAnnuelVal = htmlspecialchars(number_format($soldeAnnuel,1));
    $soldeReliquatVal = htmlspecialchars(number_format($soldeReliquat,1));
    $soldeRecupVal = htmlspecialchars(number_format($soldeRecup,0));

    $balancesHtml = "<div style=\"line-height:1.4; font-size:10pt;\">" .
                    "- Congé Annuel : <b>{$soldeAnnuelVal}</b> jours<br>" .
                    "- Congé Reliquat : <b>{$soldeReliquatVal}</b> jours<br>" .
                    "- Jours de Récupération : <b>{$soldeRecupVal}</b> jours" .
                    "</div>";
    $pdf->writeHTML($balancesHtml, true, false, true, false, '');
    $pdf->Ln(7);
}

$signatureY = $pdf->getPageHeight() - $pdf->getFooterMargin() - 45;
if ($pdf->GetY() > $signatureY - 10) {
    // If content is already too close to where signature should be
} else {
   $pdf->SetY($signatureY);
}

$pdf->SetFont('dejavusans', '', 11);
$responsibleTitle = htmlspecialchars($company['hr_manager_title'] ?? 'Le Responsable des Ressources Humaines');
$pdf->Cell(0, 7, $responsibleTitle, 0, 1, 'R');
$pdf->Ln(3);

if (!empty($company['signature_path']) && file_exists(realpath(__DIR__ . '/../../' . ltrim($company['signature_path'], '/')))) {
    $signatureFullPath = realpath(__DIR__ . '/../../' . ltrim($company['signature_path'], '/'));
    $imgSize = @getimagesize($signatureFullPath);
    if ($imgSize !== false) {
        list($imgWidth, $imgHeight) = $imgSize;
        $aspectRatio = $imgWidth / $imgHeight;
        $signatureImageHeight = 15;
        $signatureImageWidth = $signatureImageHeight * $aspectRatio;
        if ($signatureImageWidth > 50) {
            $signatureImageWidth = 50;
            $signatureImageHeight = $signatureImageWidth / $aspectRatio;
        }
        $signatureImageX = $pdf->GetPageWidth() - $pdf->getMargins()['right'] - $signatureImageWidth;
        if ($pdf->GetY() + $signatureImageHeight + 5 < ($pdf->getPageHeight() - $pdf->getFooterMargin())) {
             $pdf->Image($signatureFullPath, $signatureImageX, $pdf->GetY(), $signatureImageWidth, $signatureImageHeight, '', '', 'T', false, 300, '', false, false, 0);
             $pdf->SetY($pdf->GetY() + $signatureImageHeight + 1);
        } else {
             $pdf->Cell(0, 15, '_________________________', 0, 1, 'R');
        }
    } else {
        $pdf->Cell(0, 15, '_________________________', 0, 1, 'R');
    }
} else {
    $pdf->Cell(0, 15, '_________________________', 0, 1, 'R');
}

$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(0, 6, htmlspecialchars($company['company_name'] ?? 'NOM DE LA SOCIÉTÉ'), 0, 1, 'R');

if (file_exists($qrTempFile)) {
    @unlink($qrTempFile);
}

if (ob_get_length()) ob_end_clean();
$pdf->Output($nomDocumentForPdfOutput, 'I');
exit();
?>