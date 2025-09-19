<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// Config is already included by public/index.php
header('Content-Type: application/json');

$response = [
    'last_check_ins' => [],
    'last_check_outs' => [],
    'currently_present' => []
];

try {
    // Last 10 Check-ins today
    $stmt_in = $db->query("
        SELECT TIME_FORMAT(s.scan_time, '%H:%i') as scan_time, e.first_name, e.last_name, e.position, e.photo_path
        FROM attendance_scans s JOIN employees e ON s.employee_nin = e.nin
        WHERE s.scan_type = 'in' AND DATE(s.scan_time) = CURDATE()
        ORDER BY s.scan_time DESC LIMIT 10
    ");
    $response['last_check_ins'] = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

    // Last 10 Check-outs today
    $stmt_out = $db->query("
        SELECT TIME_FORMAT(s.scan_time, '%H:%i') as scan_time, e.first_name, e.last_name, e.position, e.photo_path
        FROM attendance_scans s JOIN employees e ON s.employee_nin = e.nin
        WHERE s.scan_type = 'out' AND DATE(s.scan_time) = CURDATE()
        ORDER BY s.scan_time DESC LIMIT 10
    ");
    $response['last_check_outs'] = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Public Feed Error: " . $e->getMessage());
}

echo json_encode($response);
exit();