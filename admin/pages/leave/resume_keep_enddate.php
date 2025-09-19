<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

$leave_id = (int)$_GET['id'];

// Get leave info
$stmt = $db->prepare("SELECT start_date, end_date, employee_nin FROM leave_requests WHERE id = ?");
$stmt->execute([$leave_id]);
$leave = $stmt->fetch();
if (!$leave) {
    $_SESSION['error'] = "Leave not found.";
    header("Location: " . route('leave_requests')); // MODIFIED
    exit();
}

$start = new DateTime($leave['start_date']);
$end = new DateTime($leave['end_date']);

// Get all pauses for this leave
$stmt = $db->prepare("SELECT pause_start_date, pause_end_date FROM leave_pauses WHERE leave_request_id = ?");
$stmt->execute([$leave_id]);
$pauses = $stmt->fetchAll();

// Calculate all TAKEN days (exclude all paused days)
$taken_days = [];
$period = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
foreach ($period as $date) {
    $isPaused = false;
    foreach ($pauses as $pause) {
        $pause_start = new DateTime($pause['pause_start_date']);
        $pause_end = new DateTime($pause['pause_end_date']);
        if ($date >= $pause_start && $date <= $pause_end) {
            $isPaused = true;
            break;
        }
    }
    if (!$isPaused) $taken_days[] = $date->format('Y-m-d');
}
$used_days = count($taken_days);

// Update leave status (approved)
$stmt = $db->prepare("UPDATE leave_requests SET status = 'approved' WHERE id = ?");
$stmt->execute([$leave_id]);

// Subtract only the used days from employee balance
$stmt = $db->prepare("SELECT annual_leave_balance FROM employees WHERE nin = ?");
$stmt->execute([$leave['employee_nin']]);
$current_balance = $stmt->fetchColumn();
$new_balance = $current_balance - $used_days;
if ($new_balance < 0) $new_balance = 0;

$stmt = $db->prepare("UPDATE employees SET annual_leave_balance = ? WHERE nin = ?");
$stmt->execute([$new_balance, $leave['employee_nin']]);

$_SESSION['success'] = "Le congé repris (dates inchangées). Jours consommés : $used_days.";
header("Location: " . route('leave_view', ['id' => $leave_id])); // MODIFIED
exit();