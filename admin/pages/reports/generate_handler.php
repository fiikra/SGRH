<?php
// --- Vérification de sécurité ---
ob_start();
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Validation de la requête ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Méthode non autorisée.');
}

// --- Nettoyage des entrées ---
$nin = sanitize($_POST['employee_nin'] ?? '');
$formType = sanitize($_POST['type'] ?? 'Attestation');
$contentFromForm = sanitizeTextarea($_POST['content'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($nin)) {
    die("NIN manquant.");
}

// --- Récupération des données ---
$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$employee || !$company) {
    die("Données employé ou entreprise introuvables.");
}

// Formatage des données
$civilite = (strtolower($employee['gender'] ?? 'homme')) === 'femme' ? 'Mme.' : 'M.';
$fullname = "<b>" . htmlspecialchars(($employee['first_name'] ?? '') . " " . ($employee['last_name'] ?? '')) . "</b>";
$birthDate = formatDate($employee['birth_date'] ?? null);
$birthPlace = htmlspecialchars($employee['birth_place'] ?? 'N/A');
$poste = "<b>" . htmlspecialchars($employee['position'] ?? 'N/A') . "</b>";
$hireDate = formatDate($employee['hire_date'] ?? null);
$companyName = htmlspecialchars($company['company_name'] ?? 'AMAZIGH');
$companyNameInContent = "<b>" . $companyName . "</b>";

try {
    // --- Génération de la référence ---
    $prefix = strtoupper(substr(str_replace('_', '', $formType), 0, 3));
    $stmt = $db->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(reference_number, '-N', -1), '-', 1) AS UNSIGNED)) as last_num FROM certificates WHERE certificate_type = ?");
    $stmt->execute([$formType]);
    $lastNum = $stmt->fetch(PDO::FETCH_ASSOC)['last_num'] ?? 0;
    $orderNumLabel = str_pad($lastNum + 1, 4, "0", STR_PAD_LEFT);
    $reference = $prefix . '-N' . $orderNumLabel . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // --- Enregistrement en base ---
    $sql = "INSERT INTO certificates (employee_nin, issue_date, prepared_by, content, reference_number, certificate_type) VALUES (?, NOW(), ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$nin, $userId, $contentFromForm, $reference, $formType]);

    // --- Création du PDF ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($companyName);
    $pdf->SetTitle("Attestation de Travail");
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 15); // Réduit la marge de bas de page
    $pdf->AddPage();

    // En-tête avec logo et nom de l'entreprise
    $pdf->SetY(15);
    if (!empty($company['logo_path']) && file_exists(ROOT_PATH . $company['logo_path'])) {
        $pdf->Image(ROOT_PATH . $company['logo_path'], 20, 15, 40);
        $pdf->SetXY(70, 20);
    } else {
        $pdf->SetXY(20, 20);
    }
    
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 8, strtoupper($companyName), 0, 1);

    // Titre du document
    $pdf->SetY(50);
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->Cell(0, 10, 'ATTESTATION DE TRAVAIL', 0, 1, 'C');
    $pdf->Ln(5);

    // Référence et date
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Référence: ' . $reference, 0, 1, 'R');
    $pdf->Cell(0, 5, htmlspecialchars($company['city'] ?? '') . ', le ' . date('d/m/Y'), 0, 1, 'R');
    $pdf->Ln(10);

    // Contenu avec mise en page améliorée et gestion de l'espace
    $pdf->SetFont('helvetica', '', 11);
    
    // Calcul de l'espace disponible
    $availableSpace = $pdf->getPageHeight() - $pdf->GetY() - 60; // 60mm pour signature et QR code
    
    $htmlContent = '
    <div style="line-height: 1.6; text-align: justify;">
        <p style="margin-bottom: 6px;">
            Nous soussignés, '.$companyNameInContent.', certifions que '.$civilite.' '.$fullname.',
        </p>
        
        <p style="margin-bottom: 6px; text-indent: 20px;">
            né(e) le <b>'.$birthDate.'</b> à <b>'.$birthPlace.'</b>,
        </p>
        
        <p style="margin-bottom: 6px; text-indent: 20px;">
            est employé(e) dans notre société en qualité de '.$poste.'
        </p>
        
        <p style="margin-bottom: 6px; text-indent: 20px;">
            depuis le <b>'.$hireDate.'</b> à ce jour.
        </p>
        
        <p style="margin-top: 10px;">
            La présente attestation lui est délivrée à sa demande pour servir et valoir ce que de droit.
        </p>
    </div>';
    
    $pdf->writeHTML($htmlContent, true, false, true, false, '');
    
    // Ajustement pour forcer une seule page
    if ($pdf->GetY() > ($pdf->getPageHeight() - 60)) {
        $pdf->AddPage();
    }

    // Signature
    $pdf->SetY(-90);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $company['hr_manager_title'] ?? 'Le Responsable des Ressources Humaines,', 0, 1, 'R');
    
    if (!empty($company['signature_path']) && file_exists(ROOT_PATH . $company['signature_path'])) {
        $pdf->Image(ROOT_PATH . $company['signature_path'], 145, $pdf->GetY(), 40);
        $pdf->SetY($pdf->GetY() + 20);
    } else {
        $pdf->SetY($pdf->GetY() + 10);
    }
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, strtoupper($companyName), 0, 1, 'R');

    // QR Code
    $pdf->SetY(-50);
    $APPLINK = getenv('APP_LINK') ?: (defined('APP_LINK') ? APP_LINK : getBaseUrl());
    $pdfUrlInQr = rtrim($APPLINK, '/') . '/verify?ref=' . urlencode($reference);
    $pdf->write2DBarcode($pdfUrlInQr, 'QRCODE,H', 20, $pdf->GetY(), 20, 20);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(45, $pdf->GetY() + 5);
    $pdf->Cell(0, 5, 'Document généré par: ' . htmlspecialchars($_SESSION['username'] ?? 'Système'), 0, 1, 'L');

    // Envoi au navigateur
    ob_end_clean();
    $pdf->Output('Attestation.pdf', 'I');
    exit();

} catch (Exception $e) {
    error_log("Erreur génération PDF: " . $e->getMessage());
    die("Erreur lors de la génération du document.");
}