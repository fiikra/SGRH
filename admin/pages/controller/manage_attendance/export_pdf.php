<?php
// Note: Assurez-vous que la classe TCPDF est chargée via un autoloader ou un require.

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

require_once __DIR__ . '../../../model/attendance/PdfExportModel.php';
require_once __DIR__ . '../../../../includes/attendance_functions.php'; // Pour les fonctions d'aide comme getDayInfo

$model = new PdfExportModel($db);

// --- Validation des paramètres ---
$nin = sanitize($_GET['nin'] ?? null);
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

if (!$nin || !$year || !$month) {
    flash('error', "Paramètres manquants pour l'export PDF.");
    header('Location: ' . route('attendance_history')); // Rediriger vers l'historique
    exit();
}

// --- Récupération des données ---
$employee = $model->getEmployeeDetails($nin);
$company = $model->getCompanySettings();
$attendanceRecords = $model->getAttendanceData($nin, $year, $month);
$summary = calculateAttendanceSummary($db, $nin, $year, $month); // On peut garder cette fonction helper pour le moment

if (!$employee || !$company) {
    flash('error', "Données de l'employé ou de l'entreprise introuvables.");
    header('Location: ' . route('attendance_history'));
    exit();
}

// --- Logique de génération du PDF (adaptée de votre fichier `export_attendance_pdf.php`) ---

class AttendancePDF extends TCPDF {
    public $company;
    public $employee;
    public $period;
    // ... (La classe PDF personnalisée avec Header() et Footer() reste identique)
}

if (ob_get_length()) ob_end_clean(); // Nettoyer le buffer

$pdf = new AttendancePDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->company = $company;
$pdf->employee = $employee;
$pdf->period = monthName($month) . ' ' . $year;

// ... (Configuration du PDF : SetCreator, SetAuthor, Margins, etc.)

$pdf->AddPage();

// ... (Génération du contenu HTML pour le résumé et le tableau détaillé)
// Cette partie reste identique à votre fichier original.

$filename = 'Feuille_Presence_' . $employee['nin'] . "_${year}-${month}.pdf";
$pdf->Output($filename, 'I'); // 'I' pour inline (afficher dans le navigateur)
exit();