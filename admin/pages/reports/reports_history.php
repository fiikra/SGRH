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

    // --- Création du PDF (identique à votre générateur) ---
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($company['company_name']);
    $pdf->SetTitle("Attestation de Travail (Archive)");
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // En-tête
    $pdf->SetY(15);
    if (!empty($company['logo_path']) && file_exists(ROOT_PATH . $company['logo_path'])) {
        $pdf->Image(ROOT_PATH . $company['logo_path'], 20, 15, 40);
    }
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(70, 20);
    $pdf->Cell(0, 8, strtoupper($company['company_name']), 0, 1);

    // Titre
    $pdf->SetY(50);
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->Cell(0, 10, strtoupper(str_replace('_', ' ', $certificate['certificate_type'])), 0, 1, 'C');
    $pdf->Ln(5);

    // Référence et date
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Référence: ' . htmlspecialchars($certificate['reference_number']), 0, 1, 'R');
    $pdf->Cell(0, 5, htmlspecialchars($company['city'] ?? '') . ', le ' . formatDate($certificate['issue_date']), 0, 1, 'R');
    $pdf->Ln(10);

    // Contenu (depuis la base de données)
    $pdf->SetFont('helvetica', '', 11);
    $htmlContent = '<div style="line-height: 1.6; text-align: justify;">' . nl2br($certificate['content']) . '</div>';
    $pdf->writeHTML($htmlContent, true, false, true, false, '');

    // Signature
    $pdf->SetY(-90);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $company['hr_manager_title'] ?? 'Le Responsable des Ressources Humaines,', 0, 1, 'R');
    if (!empty($company['signature_path']) && file_exists(ROOT_PATH . $company['signature_path'])) {
        $pdf->Image(ROOT_PATH . $company['signature_path'], 145, $pdf->GetY(), 40);
        $pdf->SetY($pdf->GetY() + 20);
    }
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, strtoupper($company['company_name']), 0, 1, 'R');

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