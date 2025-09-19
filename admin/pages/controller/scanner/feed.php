<?php
if (!defined('APP_SECURE_INCLUDE')) define('APP_SECURE_INCLUDE', true);


header('Content-Type: application/json');

require_once __DIR__ . '../../../model/attendance/ScannerModel.php';
$model = new ScannerModel($db);

try {
    $checkIns = $model->getRecentScans('in');
    $checkOuts = $model->getRecentScans('out');

    echo json_encode([
        'last_check_ins' => $checkIns,
        'last_check_outs' => $checkOuts
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not fetch feed data.']);
}

exit();