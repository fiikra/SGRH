<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}


redirectIfNotHR();

require_once __DIR__ . '../../../model/attendance/ScannerModel.php';
$model = new ScannerModel($db);

// Récupérer les données nécessaires pour la vue
$kioskSettings = $model->getKioskSettings();

// Passer les variables à la vue
$company_name = $kioskSettings['company_name'];
$logo_path = $kioskSettings['logo_path'];
$attendance_method = $kioskSettings['attendance_method'];
$scan_mode = $kioskSettings['scan_mode'];

// Charger la vue du kiosque
include __DIR__ . '/../../views/scanner/kiosk.php';