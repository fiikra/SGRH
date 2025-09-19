<?php
// Note : Ce contrôleur suppose que la bibliothèque PhpSpreadsheet est chargée via un autoloader.
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

// Le modèle est nécessaire pour les interactions avec la base de données
// require_once __DIR__ . '../../../model/ManageAttendanceModel.php';
// $model = new ManageAttendanceModel($db);
// NOTE: Pour rester simple, le code de `upload_attendance.php` est directement adapté ici.
// Une version plus avancée déplacerait les requêtes SQL dans des méthodes du modèle.

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['attendance_file'])) {
    header("Location: " . route('attendance_manage'));
    exit;
}

// --- Validation des entrées ---
$file = $_FILES['attendance_file'];
$year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
$route_params = ['year' => $year ?? date('Y'), 'month' => $month ?? date('m')];

if (!$year || !$month || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Données ou fichier invalide/manquant.";
    header("Location: " . route('attendance_manage', $route_params));
    exit;
}

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Logique de traitement du fichier Excel (adaptée de votre fichier `upload_attendance.php`)
try {
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    $db->beginTransaction();

    $processed_employee_rows = 0;
    $error_count = 0;
    $errors_details = [];
    $current_user_id = $_SESSION['user_id'] ?? null;

    // Colonnes
    $col_idx_nin = 1;
    $col_idx_day_one_status = 4;
    $col_idx_monthly_retenu = 3 + $days_in_month + 1;
    $col_idx_monthly_hs = 3 + $days_in_month + 2;

    $stmt_employees_all = $db->query("SELECT nin FROM employees WHERE status = 'active'");
    $all_employees_nins = $stmt_employees_all->fetchAll(PDO::FETCH_COLUMN);

    for ($rowIdx = 2; $rowIdx <= $highestRow; $rowIdx++) {
        $employee_nin = trim($sheet->getCell(Coordinate::stringFromColumnIndex($col_idx_nin) . $rowIdx)->getFormattedValue());
        if (empty($employee_nin)) continue;

        if (!in_array($employee_nin, $all_employees_nins)) {
            $errors_details[] = "Ligne $rowIdx: NIN '".htmlspecialchars($employee_nin)."' non trouvé.";
            $error_count++;
            continue;
        }

        // ... (Logique complète de traitement des statuts journaliers à insérer ici) ...
        // Pour la concision, cette boucle interne est omise, mais elle doit être présente
        // comme dans votre fichier `upload_attendance.php` original.

        // Traitement des totaux mensuels
        $monthly_retenu_val = trim($sheet->getCell(Coordinate::stringFromColumnIndex($col_idx_monthly_retenu) . $rowIdx)->getValue());
        $monthly_hs_val = trim($sheet->getCell(Coordinate::stringFromColumnIndex($col_idx_monthly_hs) . $rowIdx)->getValue());
        
        $retenu_hours = (is_numeric($monthly_retenu_val) && $monthly_retenu_val >= 0) ? (float)$monthly_retenu_val : 0.00;
        $hs_hours = (is_numeric($monthly_hs_val) && $monthly_hs_val >= 0) ? (float)$monthly_hs_val : 0.00;

        if ($hs_hours > 0 || $retenu_hours > 0) {
             $sql_insert_summary = "REPLACE INTO employee_monthly_financial_summary 
                (employee_nin, period_year, period_month, total_hs_hours, total_retenu_hours, recorded_by_user_id)
                VALUES (:nin, :p_year, :p_month, :hs_hours, :retenu_hours, :user_id)";
            $stmt_summary = $db->prepare($sql_insert_summary);
            $stmt_summary->execute([
                ':nin' => $employee_nin, ':p_year' => $year, ':p_month' => $month,
                ':hs_hours' => $hs_hours, ':retenu_hours' => $retenu_hours,
                ':user_id' => $current_user_id
            ]);
        }
        $processed_employee_rows++;
    }

    if ($error_count > 0) {
        $db->rollBack();
        $_SESSION['error'] = "Importation échouée avec $error_count erreur(s). " . implode(" ", array_slice($errors_details, 0, 5));
    } else {
        $db->commit();
        $_SESSION['success'] = "$processed_employee_rows lignes employés traitées avec succès pour $month/$year.";
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error'] = "Erreur de traitement: " . $e->getMessage();
}

header("Location: " . route('attendance_manage', $route_params));
exit;