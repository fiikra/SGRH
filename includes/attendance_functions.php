<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

/**
 * Fetches attendance records for an employee for a specific month.
 * @return array Records keyed by date ('YYYY-MM-DD').
 */
function fetchAttendanceData($db, $nin, $year, $month) {
    $sql = "SELECT * FROM employee_attendance WHERE employee_nin = :nin AND YEAR(attendance_date) = :year AND MONTH(attendance_date) = :month";
    $stmt = $db->prepare($sql);
    $stmt->execute([':nin' => $nin, ':year' => $year, ':month' => $month]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_column($records, null, 'attendance_date');
}

/**
 * Calculates an attendance summary for the month.
 * @return array Summary counts.
 */
function calculateAttendanceSummary($db, $nin, $year, $month) {
    $records = fetchAttendanceData($db, $nin, $year, $month);
    $summary = ['worked_days' => 0, 'absent_justified_paid' => 0, 'absent_justified_unpaid' => 0, 'absent_unjustified' => 0, 'sick_leave' => 0, 'annual_leave' => 0];
    foreach ($records as $record) {
        switch ($record['status']) {
            case 'present':
            case 'present_weekend':
            case 'present_offday': $summary['worked_days']++; break;
            case 'absent_authorized_paid': $summary['absent_justified_paid']++; break;
            case 'absent_authorized_unpaid': $summary['absent_justified_unpaid']++; break;
            case 'absent_unjustified': $summary['absent_unjustified']++; break;
            case 'sick_leave': $summary['sick_leave']++; break;
            case 'annual_leave': $summary['annual_leave']++; break;
        }
    }
    return $summary;
}

/**
 * Checks if there are workdays in the month with no attendance record.
 * @return bool True if issues are found.
 */
function hasUnvalidatedEntries($db, $nin, $year, $month) {
    static $company_cache = null, $employee_cache = [];
    if (!isset($employee_cache[$nin])) {
        $stmt = $db->prepare("SELECT employee_rest_days FROM employees WHERE nin = ?");
        $stmt->execute([$nin]);
        $employee_cache[$nin] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($company_cache === null) {
        $company_cache = $db->query("SELECT weekend_days, jours_feries FROM company_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    }
    
    $weekends = !empty($employee_cache[$nin]['employee_rest_days']) ? explode(',', $employee_cache[$nin]['employee_rest_days']) : explode(',', $company_cache['weekend_days'] ?? '5,6');
    $holidays_raw = json_decode($company_cache['jours_feries'] ?? '[]', true);
    
    $stmt = $db->prepare("SELECT DAY(attendance_date) FROM employee_attendance WHERE employee_nin = ? AND YEAR(attendance_date) = ? AND MONTH(attendance_date) = ?");
    $stmt->execute([$nin, $year, $month]);
    $recorded_days = $stmt->fetchAll(PDO::FETCH_COLUMN);

    for ($day = 1; $day <= cal_days_in_month(CAL_GREGORIAN, $month, $year); $day++) {
        $date = new DateTime("$year-$month-$day");
        $isHoliday = false;
        foreach($holidays_raw as $h) { if (($h['mois'] == $month) && ($h['jour'] == $day)) { $isHoliday = true; break; } }
        if (!$isHoliday && !in_array($date->format('w'), $weekends) && !in_array($day, $recorded_days)) {
            return true;
        }
    }
    return false;
}

/**
 * Gets all display information for a given day in the attendance log.
 * @return array
 */
function getDayInfo($db, $currentDate, $employee, $record) {
    static $company_settings = null;
    if ($company_settings === null) {
        $company_settings = $db->query("SELECT weekend_days, jours_feries FROM company_settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    }

    $date = new DateTime($currentDate);
    $weekends = !empty($employee['employee_rest_days']) ? explode(',', $employee['employee_rest_days']) : explode(',', $company_settings['weekend_days'] ?? '5,6');
    $holidays_raw = json_decode($company_settings['jours_feries'] ?? '[]', true);
    $isHoliday = false;
    foreach($holidays_raw as $h) { if (($h['mois'] == $date->format('n')) && ($h['jour'] == $date->format('j'))) { $isHoliday = true; break; } }
    
    $isWeekend = in_array($date->format('w'), $weekends);
    $dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

    $info = ['row_class' => '', 'day_name' => $dayNames[$date->format('w')], 'badge_class' => 'bg-secondary', 'status_text' => 'Non Enregistré'];

    if ($isWeekend) $info['row_class'] = 'table-secondary';
    if ($isHoliday) $info['row_class'] = 'table-warning';

    if ($record) {
        switch ($record['status']) {
            case 'present': $info['badge_class'] = 'bg-success'; $info['status_text'] = 'Présent'; break;
            case 'present_weekend': case 'present_offday': $info['badge_class'] = 'bg-info'; $info['status_text'] = 'Travail Exceptionnel'; break;
            case 'absent_unjustified': $info['badge_class'] = 'bg-danger'; $info['status_text'] = 'Abs. Non Justifiée'; break;
            case 'absent_authorized_paid': $info['badge_class'] = 'bg-warning text-dark'; $info['status_text'] = 'Abs. Just. (Payée)'; break;
            case 'absent_authorized_unpaid': $info['badge_class'] = 'bg-secondary'; $info['status_text'] = 'Abs. Just. (Non Payée)'; break;
            case 'sick_leave': $info['badge_class'] = 'bg-purple text-white'; $info['status_text'] = 'Maladie'; break;
            case 'annual_leave': $info['badge_class'] = 'bg-primary'; $info['status_text'] = 'Congé Annuel'; break;
            default: $info['badge_class'] = 'bg-dark'; $info['status_text'] = ucfirst(str_replace('_', ' ', $record['status'])); break;
        }
    } else {
        if ($isHoliday) { $info['badge_class'] = 'bg-light text-dark'; $info['status_text'] = 'Jour Férié'; } 
        elseif ($isWeekend) { $info['badge_class'] = 'bg-light text-dark'; $info['status_text'] = 'Weekend'; }
    }
    return $info;
}

/**
 * Returns the full French name of a month.
 */
function monthName($monthNum) {
    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'MMMM');
        return ucfirst($formatter->format(mktime(0, 0, 0, $monthNum, 1)));
    }
    return date('F', mktime(0, 0, 0, $monthNum, 1));
}

/**
 * Renders Bootstrap 5 pagination HTML.
 */
function renderPagination($route, $currentPage, $totalPages, $params = []) {
    if ($totalPages <= 1) return;
    echo '<div class="card-footer py-2"><nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    $prevParams = array_merge($params, ['page' => $currentPage - 1]);
    echo "<li class='page-item " . ($currentPage <= 1 ? 'disabled' : '') . "'><a class='page-link' href='" . route($route, $prevParams) . "'>&laquo;</a></li>";
    for ($i = 1; $i <= $totalPages; $i++) {
        $pageParams = array_merge($params, ['page' => $i]);
        echo "<li class='page-item " . ($i == $currentPage ? 'active' : '') . "'><a class='page-link' href='" . route($route, $pageParams) . "'>$i</a></li>";
    }
    $nextParams = array_merge($params, ['page' => $currentPage + 1]);
    echo "<li class='page-item " . ($currentPage >= $totalPages ? 'disabled' : '') . "'><a class='page-link' href='" . route($route, $nextParams) . "'>&raquo;</a></li>";
    echo '</ul></nav></div>';
}