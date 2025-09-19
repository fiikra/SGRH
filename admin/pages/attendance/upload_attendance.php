<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}



use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

redirectIfNotHR();

// --- Route helper ---
function routerUrl($route, $params = []) {
    $base = defined('APP_LINK') ? APP_LINK . '/admin/index.php?route=' . urlencode($route) : 'index.php?route=' . urlencode($route);
    foreach ($params as $k => $v) {
        $base .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    return $base;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attendance_file']) && isset($_POST['year']) && isset($_POST['month'])) {
    $file = $_FILES['attendance_file'];
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
    $month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
    $redirect_year = htmlspecialchars($_POST['year'] ?? date('Y'));
    $redirect_month = htmlspecialchars($_POST['month'] ?? date('m'));
    $route_params = ['year' => $redirect_year, 'month' => $redirect_month];

    if (!$year || !$month || $year < 2000 || $year > 2050 || $month < 1 || $month > 12) {
        $_SESSION['error'] = "Année ou mois manquant/invalide.";
        header("Location: " . routerUrl('attendance_manage_attendance', $route_params)); exit;
    }
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // File validation (as before)
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Erreur upload: " . $file['error'];
        header("Location: " . routerUrl('attendance_manage_attendance', $route_params)); exit;
    }
    // ... (rest of file validations: MIME type, size)

    $company_settings_stmt = $db->query("SELECT weekend_days, jours_feries FROM company_settings WHERE id = 1 LIMIT 1");
    $company_s = $company_settings_stmt->fetch(PDO::FETCH_ASSOC);
    $all_company_weekend_days_str = $company_s['weekend_days'] ?? '5,6';
    $company_default_weekend_days_array = array_map('strval', array_filter(explode(',', $all_company_weekend_days_str), function($v){ return $v!==''; }));
    $jours_feries_json = $company_s['jours_feries'] ?? '[]';
    $public_holidays_config = parse_json_field($jours_feries_json);
    $public_holiday_dates_for_month_map = [];
    if(is_array($public_holidays_config)) {
        foreach ($public_holidays_config as $ph) {
            if (isset($ph['jour']) && isset($ph['mois']) && intval($ph['mois']) == $month) $public_holiday_dates_for_month_map[intval($ph['jour'])] = true;
        }
    }

    $col_idx_nin = 1;
    $col_idx_day_one_status = 4; // Status for Day 1 starts at column D
    $col_idx_monthly_retenu = 3 + $days_in_month + 1;
    $col_idx_monthly_hs = 3 + $days_in_month + 2;

    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $db->beginTransaction();
        $processed_employee_rows = 0;
        $total_daily_records_affected = 0;
        $total_monthly_financial_summary_records_affected = 0;

        $error_count = 0; $errors_details = [];
        $current_user_id = $_SESSION['user_id'] ?? null;

        $stmt_employees_all = $db->prepare("SELECT nin, employee_rest_days FROM employees WHERE status = 'active'");
        $stmt_employees_all->execute();
        $all_employees_data = $stmt_employees_all->fetchAll(PDO::FETCH_KEY_PAIR);

        for ($rowIdx = 2; $rowIdx <= $highestRow; $rowIdx++) {
            $employee_nin = trim($sheet->getCell(Coordinate::stringFromColumnIndex($col_idx_nin) . $rowIdx)->getFormattedValue());
            if (empty($employee_nin)) { continue; }

            if (!isset($all_employees_data[$employee_nin])) {
                $errors_details[] = "Ligne $rowIdx: Emp. NIN '".htmlspecialchars($employee_nin)."' non trouvé/inactif.";
                $error_count++; continue;
            }

            $employee_specific_rest_days_str = $all_employees_data[$employee_nin];
            $effective_employee_weekend_days = $company_default_weekend_days_array;
            if (!empty($employee_specific_rest_days_str)) {
                $custom_rest_days = array_map('strval', array_filter(explode(',', $employee_specific_rest_days_str), function($v){ return $v!==''; }));
                if (!empty($custom_rest_days)) {
                    $effective_employee_weekend_days = $custom_rest_days;
                }
            }

            $employee_row_has_error_flag = false;

            // Process daily attendance statuses (status logic as before)
            for ($day = 1; $day <= $days_in_month; $day++) {
                $attendance_date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $status_col_letter = Coordinate::stringFromColumnIndex($col_idx_day_one_status + ($day - 1));
                $cell_value = trim(strtoupper($sheet->getCell($status_col_letter . $rowIdx)->getFormattedValue()));

                $db_status = null; $db_leave_type_note = null; $db_notes_for_day = null;
                $current_dt_obj = new DateTime($attendance_date_str);
                $day_of_week_for_check = $current_dt_obj->format('w');
                $is_weekend = in_array((string)$day_of_week_for_check, $effective_employee_weekend_days);
                $is_friday = ($day_of_week_for_check == 5);
                $is_actually_holiday = isset($public_holiday_dates_for_month_map[$day]);

                // ... (attendance status logic, as in your original file) ...
                // For brevity, not repeating the full block. Use your working logic here.

                if ($employee_row_has_error_flag && $db_status === null) continue;

                $existing_created_at = null;
                $sql_fetch_created_at = "SELECT created_at FROM employee_attendance WHERE employee_nin = :nin AND attendance_date = :att_date LIMIT 1";
                $stmt_fetch_created_at = $db->prepare($sql_fetch_created_at);
                $stmt_fetch_created_at->execute([':nin' => $employee_nin, ':att_date' => $attendance_date_str]);
                $existing_record_for_date = $stmt_fetch_created_at->fetch(PDO::FETCH_ASSOC);
                if ($existing_record_for_date) $existing_created_at = $existing_record_for_date['created_at'];
                $created_at_for_sql = $existing_created_at ?? date('Y-m-d H:i:s');

                if ($db_status !== null) {
                    $sql_insert_attendance = "REPLACE INTO employee_attendance (employee_nin, attendance_date, status, leave_type_if_absent, is_weekend_work, is_holiday_work, notes, recorded_by_user_id, created_at, updated_at)
                        VALUES (:nin, :att_date, :status, :leave_type, :is_ww, :is_hw, :notes, :user_id, :created_at_val, NOW())";
                    $stmt_attendance = $db->prepare($sql_insert_attendance);
                    $is_weekend_work_db_flag = ($db_status === 'present_weekend');
                    $is_holiday_work_db_flag = ($db_status === 'present_offday');
                    $exec_attendance = $stmt_attendance->execute([
                        ':nin' => $employee_nin, ':att_date' => $attendance_date_str, ':status' => $db_status,
                        ':leave_type' => $db_leave_type_note, 
                        ':is_ww' => (int)$is_weekend_work_db_flag, ':is_hw' => (int)$is_holiday_work_db_flag, 
                        ':notes' => $db_notes_for_day, ':user_id' => $current_user_id, 
                        ':created_at_val' => $created_at_for_sql
                    ]);
                    if ($exec_attendance) { $total_daily_records_affected++; }
                    else { $errors_details[] = "Lg $rowIdx, Jr $day (NIN $employee_nin): Erreur DB (attendance). " . print_r($stmt_attendance->errorInfo(), true); $error_count++; $employee_row_has_error_flag = true; }
                }
            } // End daily loop

            // MODIFICATION: Read and Log Monthly HS and Retenu Totals
            if (!$employee_row_has_error_flag) {
                $monthly_retenu_val = trim($sheet->getCell(Coordinate::stringFromColumnIndex($col_idx_monthly_retenu) . $rowIdx)->getValue());
                $monthly_hs_val = trim($sheet->getCell(Coordinate::stringFromColumnIndex($col_idx_monthly_hs) . $rowIdx)->getValue());

                $monthly_retenu_hours_to_log = (is_numeric($monthly_retenu_val) && $monthly_retenu_val >= 0) ? (float)$monthly_retenu_val : 0.00;
                $monthly_hs_hours_to_log = (is_numeric($monthly_hs_val) && $monthly_hs_val >= 0) ? (float)$monthly_hs_val : 0.00;

                if ($monthly_hs_hours_to_log > 0 || $monthly_retenu_hours_to_log > 0) {
                    $sql_insert_monthly_summary = "REPLACE INTO employee_monthly_financial_summary 
                        (employee_nin, period_year, period_month, total_hs_hours, total_retenu_hours, recorded_by_user_id)
                        VALUES (:nin, :p_year, :p_month, :hs_hours, :retenu_hours, :user_id)";
                    $stmt_monthly_summary = $db->prepare($sql_insert_monthly_summary);
                    $exec_monthly_summary = $stmt_monthly_summary->execute([
                        ':nin' => $employee_nin, ':p_year' => $year, ':p_month' => $month,
                        ':hs_hours' => $monthly_hs_hours_to_log,
                        ':retenu_hours' => $monthly_retenu_hours_to_log,
                        ':user_id' => $current_user_id
                    ]);
                    if ($exec_monthly_summary) { $total_monthly_financial_summary_records_affected++; }
                    else { $errors_details[] = "Lg $rowIdx (NIN $employee_nin): Erreur DB (monthly financials). " . print_r($stmt_monthly_summary->errorInfo(), true); $error_count++; }
                }
            }
            if (!$employee_row_has_error_flag) $processed_employee_rows++;
        } // End employee loop

        if ($error_count > 0) {
            $_SESSION['error'] = "Importation échouée avec $error_count erreur(s)...\n" . implode("\n", array_slice($errors_details, 0, 10));
            if ($error_count > 10) { $_SESSION['error'] .= "\nEt " . ($error_count-10) . " autre(s) erreur(s)..."; }
            $db->rollBack();
        } else {
            if ($processed_employee_rows > 0) {
                $db->commit();
                $success_msg = "$processed_employee_rows lignes employés traitées ($total_daily_records_affected pointages).";
                if ($total_monthly_financial_summary_records_affected > 0) {
                    $success_msg .= " $total_monthly_financial_summary_records_affected sommaires HS/Retenue mensuels enregistrés.";
                }
                $_SESSION['success'] = $success_msg . " pour $month/$year.";
            } else {
                $db->rollBack();
                $_SESSION['info'] = "Aucun enregistrement valide pour $month/$year.";
                if(!empty($errors_details)) { $_SESSION['info'] .= "\nProblèmes rencontrés:\n" . implode("\n", array_slice($errors_details, 0, 5)); }
            }
        }
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        if($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = "Erreur lecture Excel: " . $e->getMessage();
    } catch (Exception $e) {
        if($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = "Erreur générale: " . $e->getMessage(); error_log("Attn Upload Err: ".$e->getMessage());
    }
    header("Location: " . routerUrl('attendance_manage_attendance', $route_params));
    exit;
} else {
    $_SESSION['error'] = "Fichier, année ou mois manquant.";
    $redirect_year_fallback = htmlspecialchars($_POST['year'] ?? date('Y'));
    $redirect_month_fallback = htmlspecialchars($_POST['month'] ?? date('m'));
    $route_params_fallback = ['year' => $redirect_year_fallback, 'month' => $redirect_month_fallback];
    header("Location: " . routerUrl('attendance_manage_attendance', $route_params_fallback));
    exit;
}