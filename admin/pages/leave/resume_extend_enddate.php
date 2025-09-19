<?php
// --- Security Headers: Set before any output ---
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

$leave_id = (int)$_GET['id'];

// Get leave info
$stmt = $db->prepare("SELECT start_date, end_date, days_requested, employee_nin FROM leave_requests WHERE id = ?");
$stmt->execute([$leave_id]);
$leave = $stmt->fetch();

// If leave not found, redirect using the 'leave_requests' route
if (!$leave) {
    $_SESSION['error'] = "Leave not found.";
    header("Location: " . route('leave_requests')); // MODIFIED
    exit();
}

$start = new DateTime($leave['start_date']);
$original_days = (int)$leave['days_requested'];

// Get all pauses
$stmt = $db->prepare("SELECT pause_start_date, pause_end_date FROM leave_pauses WHERE leave_request_id = ?");
$stmt->execute([$leave_id]);
$pauses = $stmt->fetchAll();

// Compute the new end date so that non-paused days == original requested days
$new_end = clone $start;
$days_added = 0;
while ($days_added < $original_days) {
    $isPaused = false;
    foreach ($pauses as $pause) {
        $pause_start = new DateTime($pause['pause_start_date']);
        $pause_end = new DateTime($pause['pause_end_date']);
        if ($new_end >= $pause_start && $new_end <= $pause_end) {
            $isPaused = true;
            break;
        }
    }
    if (!$isPaused) {
        $days_added++;
    }
    if ($days_added < $original_days) {
        $new_end->modify('+1 day');
    }
}
$new_end_str = $new_end->format('Y-m-d');

// Update leave request end date and status
$stmt = $db->prepare("UPDATE leave_requests SET end_date = ?, status = 'approved' WHERE id = ?");
$stmt->execute([$new_end_str, $leave_id]);

// Subtract the original days from employee balance (as in the request)
$stmt = $db->prepare("SELECT annual_leave_balance FROM employees WHERE nin = ?");
$stmt->execute([$leave['employee_nin']]);
$current_balance = $stmt->fetchColumn();
$new_balance = $current_balance - $original_days;
if ($new_balance < 0) $new_balance = 0;

$stmt = $db->prepare("UPDATE employees SET annual_leave_balance = ? WHERE nin = ?");
$stmt->execute([$new_balance, $leave['employee_nin']]);

$_SESSION['success'] = "Le congé reprend avec décalage (fin ajustée au $new_end_str). Jours consommés : $original_days.";
// Redirect to the leave details page using the 'leave_approve' route with the ID parameter
header("Location: " . route('leave_view', ['id' => $leave_id])); // MODIFIED
exit();