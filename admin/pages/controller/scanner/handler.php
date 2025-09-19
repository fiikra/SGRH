<?php
if (!defined('APP_SECURE_INCLUDE')) define('APP_SECURE_INCLUDE', true);


// --- API Security ---
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. CSRF Token Check (from header)
$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (empty($submittedToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Erreur de sécurité (CSRF).']);
    exit();
}
// 2. Auth Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['hr', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Permission refusée.']);
    exit();
}

// --- Logic ---
$response = ['status' => 'error', 'message' => 'Requête invalide.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['nin'])) {
    $nin = sanitize($data['nin']);
    
    require_once __DIR__ . '../../../model/attendance/ScannerModel.php';
    $model = new ScannerModel($db);
    
    $employee = $model->getActiveEmployeeByNin($nin);

    if ($employee) {
        $scanType = $model->getNextScanType($nin);
        if ($model->recordScan($nin, $scanType)) {
            $typeName = ($scanType === 'in') ? 'Entrée' : 'Sortie';
            $response = [
                'status' => 'success',
                'message' => "Pointage ($typeName) enregistré!",
                'employeeName' => htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']),
                'scanTime' => date('H:i:s')
            ];
        } else {
            $response['message'] = 'Erreur base de données lors du pointage.';
        }
    } else {
        $response['message'] = 'Employé non trouvé ou inactif.';
    }
}

echo json_encode($response);
exit();