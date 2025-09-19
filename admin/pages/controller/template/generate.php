<?php

//generate Excel template for attendance management
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

require_once __DIR__ . '../../../model/attendance/ExcelTemplateModel.php';
$model = new ExcelTemplateModel($db);

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['default' => date('Y')]]);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['default' => date('m')]]);

// --- Récupération des données ---
$companySettings = $model->getCompanySettings();
$employees = $model->getActiveEmployeesWithRestDays();
$firstDayOfMonth = "$year-$month-01";
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
$leaves = $model->getApprovedLeavesForPeriod($firstDayOfMonth, $lastDayOfMonth);

// --- Logique de construction du fichier Excel ---
// (Cette logique est directement adaptée de votre fichier `generate_template.php`)
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Pointage_" . date('F_Y', strtotime($firstDayOfMonth)));

// Headers
$sheet->setCellValue('A1', 'NIN*')->setCellValue('B1', 'Nom*')->setCellValue('C1', 'Prénom*');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
for ($day = 1; $day <= $daysInMonth; $day++) {
    $colLetter = Coordinate::stringFromColumnIndex(3 + $day);
    $sheet->setCellValue($colLetter . '1', $day);
}

// Remplissage des données
$rowNum = 2;
foreach ($employees as $emp) {
    $sheet->setCellValue('A' . $rowNum, $emp['nin']);
    $sheet->setCellValue('B' . $rowNum, $emp['last_name']);
    $sheet->setCellValue('C' . $rowNum, $emp['first_name']);
    // ... (Logique pour pré-remplir les weekends, jours fériés, congés)
    $rowNum++;
}

// ... (Logique pour le style, la validation des données, la feuille d'instructions)

// --- Envoi du fichier au navigateur ---
if (ob_get_length()) ob_end_clean(); // Nettoyer le buffer

$filename = "modele_pointage_" . sprintf('%04d-%02d', $year, $month) . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();