<?php
ob_start();

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Récupération de la référence ---
$reference = sanitize($_GET['ref'] ?? '');
if (empty($reference)) {
    die("Référence de document manquante.");
}

try {
    // --- Récupération des données du certificat depuis la base de données ---
    $stmt = $db->prepare("SELECT * FROM certificates WHERE reference_number = ?");
    $stmt->execute([$reference]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$certificate) {
        die("Document non trouvé avec cette référence.");
    }

    // --- Récupération des données associées ---
    $stmt_emp = $db->prepare("SELECT * FROM employees WHERE nin = ?");
    $stmt_emp->execute([$certificate['employee_nin']]);
    $employee = $stmt_emp->fetch(PDO::FETCH_ASSOC);

    $company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $stmt_user = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt_user->execute([$certificate['prepared_by']]);
    $issuerName = $stmt_user->fetchColumn() ?: 'Système';

    if (!$employee || !$company) {
        die("Données employé ou entreprise introuvables pour ce document.");
    }

    // [CORRECTION] Reconstitution du template HTML identique au générateur original
    $civilite = (strtolower($employee['gender'] ?? 'homme')) === 'femme' ? 'Mme.' : 'M.';
    $fullname = "<b>" . htmlspecialchars(($employee['first_name'] ?? '') . " " . ($employee['last_name'] ?? '')) . "</b>";
    $birthDate = formatDate($employee['birth_date'] ?? null);
    $birthPlace = htmlspecialchars($employee['birth_place'] ?? 'N/A');
    $poste = "<b>" . htmlspecialchars($employee['position'] ?? 'N/A') . "</b>";
    $hireDate = formatDate($employee['hire_date'] ?? null);
    $companyName = htmlspecialchars($company['company_name'] ?? 'AMAZIGH');
    $companyNameInContent = "<b>" . $companyName . "</b>";
    $docTitle = "Attestation de Travail"; // Default title

    // Determine the content based on the certificate type stored in the database
    $formType = $certificate['certificate_type'];
    if ($formType === 'Attestation') {
        $docTitle = 'ATTESTATION DE TRAVAIL';
        $htmlBody = '
        <p style="margin-bottom: 6px;">Nous soussignés, '.$companyNameInContent.', certifions que '.$civilite.' '.$fullname.',</p>
        <p style="margin-bottom: 6px; text-indent: 20px;">né(e) le <b>'.$birthDate.'</b> à <b>'.$birthPlace.'</b>,</p>
        <p style="margin-bottom: 6px; text-indent: 20px;">est employé(e) dans notre société en qualité de '.$poste.'</p>
        <p style="margin-bottom: 6px; text-indent: 20px;">depuis le <b>'.$hireDate.'</b> à ce jour.</p>';
    } else {
        // Fallback to the content stored in the database if it's another type
        $docTitle = strtoupper(str_replace('_', ' ', $formType));
        $htmlBody = nl2br($certificate['content']);
    }

    $closingPhrase = '<p style="margin-top: 10px;">La présente attestation lui est délivrée à sa demande pour servir et valoir ce que de droit.</p>';
    $finalHtmlContent = '<div style="line-height: 1.6; text-align: justify;">' . $htmlBody . $closingPhrase . '</div>';

    // --- Création du PDF ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($companyName);
    $pdf->SetTitle($docTitle . " (Archive)");
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // En-tête
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
    $pdf->Cell(0, 10, $docTitle, 0, 1, 'C');
    $pdf->Ln(5);

    // Référence et date
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Référence: ' . htmlspecialchars($certificate['reference_number']), 0, 1, 'R');
    $pdf->Cell(0, 5, htmlspecialchars($company['city'] ?? '') . ', le ' . formatDate($certificate['issue_date']), 0, 1, 'R');
    $pdf->Ln(10);

    // Contenu
    $pdf->SetFont('helvetica', '', 11);
    $pdf->writeHTML($finalHtmlContent, true, false, true, false, '');

    // Signature
    $pdf->SetY(-90);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $company['hr_manager_title'] ?? 'Le Responsable des Ressources Humaines,', 0, 1, 'R');
    if (!empty($company['signature_path']) && file_exists(ROOT_PATH . $company['signature_path'])) {
        $pdf->Image(ROOT_PATH . $company['signature_path'], 145, $pdf->GetY(), 40);
        $pdf->SetY($pdf->GetY() + 20);
    }
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, strtoupper($companyName), 0, 1, 'R');

    // QR Code
    $pdf->SetY(-50);
    $pdfUrlInQr = route('reports_view_certificate', ['ref' => $reference]);
    $pdf->write2DBarcode($pdfUrlInQr, 'QRCODE,H', 20, $pdf->GetY(), 20, 20);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(45, $pdf->GetY() + 5);
    $pdf->Cell(0, 5, 'Document généré par: ' . htmlspecialchars($issuerName), 0, 1, 'L');

    // Envoi au navigateur
    ob_end_clean();
    $pdf->Output('Archive_' . $reference . '.pdf', 'I');
    exit();

} catch (Exception $e) {
    error_log("Erreur affichage PDF archivé: " . $e->getMessage());
    die("Erreur lors de la récupération du document.");
}