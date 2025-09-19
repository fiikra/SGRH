<?php
// On définit la constante de sécurité si elle n'existe pas pour l'accès direct
if (!defined('APP_SECURE_INCLUDE')) {
    define('APP_SECURE_INCLUDE', true);
}

// On inclut uniquement le strict nécessaire
require_once __DIR__ . '../../../../config/config.php';
require_once __DIR__ . '../../../../includes/functions.php'; // Pour sanitize()

// =========================================================================
// == VÉRIFICATION DE SÉCURITÉ MANUELLE POUR L'API
// =========================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. NOUVEAU : Vérification du jeton CSRF
$submittedToken = null;
// Les headers peuvent être en majuscules ou minuscules, on vérifie les deux cas.
if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
} else {
    // Certains serveurs/clients peuvent utiliser un autre format
    $headers = getallheaders();
    $headers = array_change_key_case($headers, CASE_UPPER);
    if (isset($headers['X-CSRF-TOKEN'])) {
        $submittedToken = $headers['X-CSRF-TOKEN'];
    }
}

if (empty($submittedToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403); // Interdit
    echo json_encode(['status' => 'error', 'message' => 'Erreur de sécurité (CSRF). Veuillez rafraîchir la page.']);
    exit();
}


// 2. Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Non autorisé
    echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé. Session invalide.']);
    exit();
}

// 3. Vérifie si l'utilisateur a le rôle HR ou Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin')) {
    http_response_code(403); // Interdit
    echo json_encode(['status' => 'error', 'message' => 'Permission refusée. Rôle insuffisant.']);
    exit();
}
// === FIN DE LA VÉRIFICATION DE SÉCURITÉ MANUELLE ===

// On s'assure que la réponse sera toujours en JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Requête invalide.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['nin'])) {
    $nin = sanitize($data['nin']);

    try {
        $stmt = $db->prepare("SELECT first_name, last_name, department FROM employees WHERE nin = ? AND status = 'active'");
        $stmt->execute([$nin]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            $lastScanStmt = $db->prepare(
                "SELECT scan_type FROM attendance_scans 
                 WHERE employee_nin = ? AND DATE(scan_time) = CURDATE() 
                 ORDER BY scan_time DESC LIMIT 1"
            );
            $lastScanStmt->execute([$nin]);
            $lastScan = $lastScanStmt->fetch(PDO::FETCH_ASSOC);

            $type = (!$lastScan || $lastScan['scan_type'] === 'out') ? 'in' : 'out';

            $insertStmt = $db->prepare("INSERT INTO attendance_scans (employee_nin, scan_type) VALUES (?, ?)");
            if ($insertStmt->execute([$nin, $type])) {
                $typeName = ($type === 'in') ? 'Entrée' : 'Sortie';
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
    } catch (PDOException $e) {
        error_log("Scan handler DB error: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        $response['message'] = "Erreur de base de données.";
    }
}

echo json_encode($response);
exit();
