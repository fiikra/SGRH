<?php
// Prevent direct access to this file if you have a constant defined in your router
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de congé invalide";
    // MODIFIED: Replaced hardcoded URL with route() helper
    header("Location: " . route('leave_requests'));
    exit();
}

$leave_id = (int)$_GET['id'];

// 1. Approve the leave
// Note: This logic seems to be for resuming a leave, not approving it. The status is set to 'approved'.
$stmt = $db->prepare("UPDATE leave_requests SET status = 'approved' WHERE id = ?");
$stmt->execute([$leave_id]);

// 2. If there is a pause for today, end the pause yesterday
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt = $db->prepare("UPDATE leave_pauses 
    SET pause_end_date = ? 
    WHERE leave_request_id = ? 
      AND pause_start_date <= ? 
      AND pause_end_date >= ?");
$stmt->execute([$yesterday, $leave_id, $today, $today]);

$_SESSION['success'] = "Le congé a été repris manuellement.";

// MODIFIED: Replaced hardcoded URL with route() helper, including the parameter
header("Location: " . route('leave_view', ['id' => $leave_id]));
exit();