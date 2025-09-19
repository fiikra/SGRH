<?php
if (ob_get_length()) ob_end_clean();
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

redirectIfNotHR();

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, [
    'options' => ['default' => date('Y'), 'min_range' => 2000, 'max_range' => 2050]
]);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, [
    'options' => ['default' => date('m'), 'min_range' => 1, 'max_range' => 12]
]);

$month_name_full = "Mois Inconnu";
try {
    $dateObj = DateTime::createFromFormat('!m', $month);
    if ($dateObj && class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM');
        $month_name_full = ucfirst($formatter->format($dateObj));
    } else if ($dateObj) {
        $month_name_full = $dateObj->format('F');
    }
} catch (Exception $e) {
    error_log("Error formatting month name: " . $e->getMessage());
}

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$company_settings_stmt = $db->query("SELECT weekend_days, jours_feries, paie_mode FROM company_settings WHERE id = 1 LIMIT 1");
$company_s = $company_settings_stmt->fetch(PDO::FETCH_ASSOC);
$all_company_weekend_days_str = $company_s['weekend_days'] ?? '5,6';
$company_default_weekend_days_array = array_map('strval', array_filter(explode(',', $all_company_weekend_days_str), function($v){ return $v!==''; }));

$paie_mode = isset($company_s['paie_mode']) ? intval($company_s['paie_mode']) : 30;
$jours_feries_json = $company_s['jours_feries'] ?? '[]';
$public_holidays_config = parse_json_field($jours_feries_json);
$public_holidays_map = [];
if (is_array($public_holidays_config)) {
    foreach ($public_holidays_config as $ph) {
        if (isset($ph['jour']) && isset($ph['mois']) && intval($ph['mois']) == $month) {
            $public_holidays_map[intval($ph['jour'])] = 'JF';
        }
    }
}

$stmt_employees = $db->query("SELECT nin, first_name, last_name, employee_rest_days FROM employees WHERE status = 'active' ORDER BY last_name, first_name");
$employees = $stmt_employees->fetchAll(PDO::FETCH_ASSOC);

$first_day_of_selected_month_str = sprintf('%04d-%02d-01', $year, $month);
$last_day_of_selected_month_str = date('Y-m-t', strtotime($first_day_of_selected_month_str));

$sql_leaves = "SELECT employee_nin, start_date, end_date, leave_type FROM leave_requests 
               WHERE status = 'approved' 
               AND (leave_type = 'annuel' OR leave_type = 'annual_leave' OR leave_type = 'Maladie' OR leave_type = 'maladie' OR leave_type = 'sick_leave' OR leave_type = 'M')
               AND start_date <= :last_day_of_month
               AND end_date >= :first_day_of_month";
$stmt_leaves = $db->prepare($sql_leaves);
$stmt_leaves->execute([
    ':last_day_of_month' => $last_day_of_selected_month_str,
    ':first_day_of_month' => $first_day_of_selected_month_str
]);
$approved_leaves_raw = $stmt_leaves->fetchAll(PDO::FETCH_ASSOC);

$employee_leaves_map = [];
foreach ($approved_leaves_raw as $leave) {
    if (!isset($employee_leaves_map[$leave['employee_nin']])) {
        $employee_leaves_map[$leave['employee_nin']] = [];
    }
    try {
        $employee_leaves_map[$leave['employee_nin']][] = [
            'start_dt' => new DateTime($leave['start_date']),
            'end_dt' => new DateTime($leave['end_date']),
            'type' => $leave['leave_type']
        ];
    } catch (Exception $e) {
        error_log("Invalid date format for leave record: NIN {$leave['employee_nin']}, Start {$leave['start_date']}, End {$leave['end_date']}");
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Pointage {$month_name_full}_{$year}");

$colIdx = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . '1', 'NIN*');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . '1', 'Nom*');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . '1', 'Prénom*');

$day_status_columns_map = []; // Only for status
for ($day = 1; $day <= $days_in_month; $day++) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx) . '1', $day); // Header is just the day number
    $day_status_columns_map[$day] = $colIdx; // Store column index for status
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))->setWidth(5); // Width for status column
    $colIdx++;
}

// Monthly summary columns for HS and Retenu (manual input)
$col_idx_monthly_retenu = $colIdx;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . '1', 'Heures Retenue (Total Mois)');
$col_idx_monthly_hs = $colIdx;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx++) . '1', 'HS (Total Mois)');
$last_header_col_idx = $colIdx - 1;

$lastColLetter = Coordinate::stringFromColumnIndex($last_header_col_idx);

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];
$sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(35);
$sheet->freezePane(Coordinate::stringFromColumnIndex(4) . '2');

$sheet->getColumnDimension(Coordinate::stringFromColumnIndex(1))->setWidth(18); // NIN
$sheet->getColumnDimension(Coordinate::stringFromColumnIndex(2))->setWidth(20); // Nom
$sheet->getColumnDimension(Coordinate::stringFromColumnIndex(3))->setWidth(20); // Prénom

$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col_idx_monthly_retenu))->setWidth(20);
$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col_idx_monthly_hs))->setWidth(20);

$rowNum = 2;
if (!empty($employees)) {
    foreach ($employees as $emp) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(1) . $rowNum, $emp['nin']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(2) . $rowNum, $emp['last_name']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(3) . $rowNum, $emp['first_name']);

        $effective_employee_weekend_days = $company_default_weekend_days_array;
        if (!empty($emp['employee_rest_days'])) {
            $custom_rest_days = array_map('strval', array_filter(explode(',', $emp['employee_rest_days']), function($v){ return $v!==''; }));
            if (!empty($custom_rest_days)) {
                $effective_employee_weekend_days = $custom_rest_days;
            }
        }

        for ($day = 1; $day <= $days_in_month; $day++) {
            $statusColLetter = Coordinate::stringFromColumnIndex($day_status_columns_map[$day]);
            $current_date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
            try { $current_dt = new DateTime($current_date_str); } catch (Exception $e) { continue; }
            $day_of_week = $current_dt->format('w');
            $cellValue = '';
            $is_on_annual_leave_today = false;
            $is_on_sick_leave_today = false;

            if (isset($employee_leaves_map[$emp['nin']])) {
                foreach ($employee_leaves_map[$emp['nin']] as $leave_period) {
                    if ($current_dt >= $leave_period['start_dt'] && $current_dt <= $leave_period['end_dt']) {
                        if ($leave_period['type'] === 'annuel' || $leave_period['type'] === 'annual_leave') $is_on_annual_leave_today = true;
                        if (in_array($leave_period['type'], ['Maladie','maladie','sick_leave','M'])) $is_on_sick_leave_today = true;
                    }
                }
            }

            if ($is_on_annual_leave_today) {
                $cellValue = 'Congés';
                $sheet->getStyle($statusColLetter . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFADD8E6');
            } elseif ($is_on_sick_leave_today) {
                $cellValue = 'Maladie';
                $sheet->getStyle($statusColLetter . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
            } elseif (isset($public_holidays_map[$day])) {
                $cellValue = 'JourFerie';
                $sheet->getStyle($statusColLetter . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFABF8F');
            } elseif (in_array((string)$day_of_week, $effective_employee_weekend_days)) {
                $cellValue = 'weekend';
                $sheet->getStyle($statusColLetter . $rowNum)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCE');
            }
            $sheet->setCellValue($statusColLetter . $rowNum, $cellValue);
        }
        // Monthly HS and Retenu columns are left blank for manual input.
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col_idx_monthly_retenu) . $rowNum, '');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col_idx_monthly_hs) . $rowNum, '');
        $rowNum++;
    }
} else {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex(1) . '2', 'NIN_Exemple');
    $sheet->setCellValue(Coordinate::stringFromColumnIndex(2) . '2', 'Nom_Exemple');
    $sheet->setCellValue(Coordinate::stringFromColumnIndex(3) . '2', 'Prénom_Exemple');
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col_idx_monthly_retenu) . '2', '');
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col_idx_monthly_hs) . '2', '');
    $rowNum = 3;
}
$dataEndRow = max($rowNum - 1, 2);
$validationRangeStartRow = 2;
$validationRangeEndRow = $dataEndRow + 100;

$valid_codes_for_user_input = "Absence non justifier,Absence autorise paye,Absence autorise non payé,Maladie,Maternite,Formation,Mission,autre,1,P,Travaille feries,travaille weekend";
$dailyStatusValidation = new DataValidation();
$dailyStatusValidation->setType(DataValidation::TYPE_LIST)
    ->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowInputMessage(true)
    ->setShowErrorMessage(true)->setShowDropDown(true)->setPromptTitle('Statut du Jour')
    ->setPrompt('Entrez un code (voir instructions).')
    ->setErrorTitle('Valeur invalide')->setError('Utilisez les codes spécifiés ou laissez vide pour Présent.')
    ->setFormula1('"' . $valid_codes_for_user_input . ',Conges,weekend,Jour Feries"');

foreach ($day_status_columns_map as $day_col_idx_val) {
    $colLetter = Coordinate::stringFromColumnIndex($day_col_idx_val);
    for ($i = $validationRangeStartRow; $i <= $validationRangeEndRow; $i++) {
        $sheet->getCell($colLetter . $i)->setDataValidation(clone $dailyStatusValidation);
    }
}

$numericFormat = NumberFormat::FORMAT_NUMBER_00;
$numericValidation = new DataValidation();
$numericValidation->setType(DataValidation::TYPE_DECIMAL)
    ->setErrorStyle(DataValidation::STYLE_INFORMATION)->setAllowBlank(true)
    ->setShowInputMessage(true)->setShowErrorMessage(true)
    ->setPromptTitle('Heures (Numérique)')->setPrompt('Entrez le nombre d\'heures (ex: 2.5) ou laissez vide si 0.')
    ->setErrorTitle('Entrée Invalide')->setError('Seuls les nombres positifs sont autorisés.')
    ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1(0);

$monthlyRetenuColLetter = Coordinate::stringFromColumnIndex($col_idx_monthly_retenu);
$monthlyHSColLetter = Coordinate::stringFromColumnIndex($col_idx_monthly_hs);

$sheet->getStyle($monthlyRetenuColLetter . $validationRangeStartRow . ':' . $monthlyRetenuColLetter . $validationRangeEndRow)->getNumberFormat()->setFormatCode($numericFormat);
for ($i = $validationRangeStartRow; $i <= $validationRangeEndRow; $i++) {
    $sheet->getCell($monthlyRetenuColLetter . $i)->setDataValidation(clone $numericValidation);
}

$sheet->getStyle($monthlyHSColLetter . $validationRangeStartRow . ':' . $monthlyHSColLetter . $validationRangeEndRow)->getNumberFormat()->setFormatCode($numericFormat);
for ($i = $validationRangeStartRow; $i <= $validationRangeEndRow; $i++) {
    $sheet->getCell($monthlyHSColLetter . $i)->setDataValidation(clone $numericValidation);
}

// INSTRUCTIONS & CODE MAP SHEET
try {
    $instructionsSheet = $spreadsheet->createSheet();
    $instructionsSheet->setTitle('Instructions');
    $instructionsSheet->setCellValue('A1', "Instructions - Feuille de Pointage pour {$month_name_full} {$year}");
    $instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $instructionsSheet->mergeCells('A1:D1');

    $mode_libelle_map = [ /* ... */ ]; // As before
    $mode_libelle = $mode_libelle_map[$paie_mode] ?? "Mode paie: $paie_mode (config non standard)";

    $instructionsData = [
        ['Important (Logique d\'Upload)', 'Laissez une case Statut journalière VIDE pour un jour de travail normal.', 'Le système remplira "P" (Présent) à l\'import, SAUF si c\'est un RC, JF, C, ou M pré-rempli. Utilisez TF ou TW pour travail sur jour férié/weekend.', 'Codes à saisir par l\'Utilisateur (colonne Statut)'],
        ['Mode de Paie Actuel', $mode_libelle, "Les RC (Repos) sont pré-remplis.", ''],
        ['Colonne', 'Description', 'Codes Utilisateur (pour Statut)', 'Pré-rempli / Auto (pour Statut)'],
        ['NIN*, Nom*, Prénom*', 'Identifiants de l\'employé.', '', 'Auto-rempli'],
        ['1-' . $days_in_month, 'Statut journalier. Remplir seulement si différent de "P" sur un jour ouvrable.',
         "Absence non justifier, Absence autorise payé, Absence autorise non payé, Maladie, Maternite, Formation, Mission, autre, 1, P, Travaille ferie, Travaille weekend",
         "Conges, Maladie, weekend, JF\nVide (sera traité comme P)"],
        ['Heures Retenue (Total Mois)', '**Saisir le total mensuel** des heures de retenue.', 'Numérique (ex: 2.5)', ''],
        ['HS (Total Mois)', '**Saisir le total mensuel** des Heures Supplémentaires.', 'Numérique (ex: 10.75)', '']
    ];

    $rowOffset = 3;
    foreach ($instructionsData as $idx => $dataRow) {
        $instructionsSheet->fromArray($dataRow, NULL, 'A'.($rowOffset + $idx));
        if ($idx <= 2) {
            $instructionsSheet->getStyle('A'.($rowOffset+$idx).':D'.($rowOffset+$idx))->getFont()->setBold(true);
            if ($idx == 0) $instructionsSheet->mergeCells('B'.($rowOffset+$idx).':C'.($rowOffset+$idx));
        }
    }

    $legendStart = $rowOffset + count($instructionsData) + 2;
    $instructionsSheet->setCellValue('A'.$legendStart, "Table de correspondance des codes (légende pour colonnes STATUT journalier)");
    $instructionsSheet->getStyle('A'.$legendStart)->getFont()->setBold(true);
    $instructionsSheet->mergeCells('A'.$legendStart.':B'.$legendStart);
    $legend = [ /* ... your legend data ... */ ];
    $legendRow = $legendStart+1;
    foreach ($legend as $row) {
        $instructionsSheet->setCellValue('A'.$legendRow, $row[0]);
        $instructionsSheet->setCellValue('B'.$legendRow, $row[1]);
        $legendRow++;
    }
    $instructionsSheet->getColumnDimension('A')->setWidth(25);
    $instructionsSheet->getColumnDimension('B')->setWidth(70);
    $instructionsSheet->getColumnDimension('C')->setWidth(45);
    $instructionsSheet->getColumnDimension('D')->setWidth(45);

    foreach(range($rowOffset, $legendRow) as $instrRowNum) {
         $instructionsSheet->getStyle('A'.$instrRowNum.':D'.$instrRowNum)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    error_log("Erreur création feuille d'instructions: " . $e->getMessage());
}

$spreadsheet->setActiveSheetIndex(0);
$filename = "modele_pointage_mensuel_" . sprintf('%04d-%02d', $year, $month) . "_" . date('Ymd-His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
try {
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
    error_log("Erreur sauvegarde Excel: " . $e->getMessage());
}
exit;