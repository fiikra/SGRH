<?php
// --- Security Headers: Set before any output ---
header('X-Frame-Options: DENY'); // Prevent clickjacking
header('X-Content-Type-Options: nosniff'); // Prevent MIME sniffing
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload'); // Only works over HTTPS
/*
// --- Force HTTPS if not already (optional, best to do in server config) ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $httpsUrl", true, 301);
    exit();
}
*/
// --- Harden Session Handling ---
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '', // Set to your production domain if needed
    'secure' => true,   // Only send cookie over HTTPS
    'httponly' => true, // JavaScript can't access
    'samesite' => 'Strict' // CSRF protection
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);
// --- Generic error handler (don't leak errors to users in production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Prevent session fixation ---
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdminOrHR()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nin = $input['nin'] ?? '';
$leaveType = $input['leaveType'] ?? 'annual';
$operation = $input['operation'] ?? 'add';
$days = (float)$input['days'];
$effectiveDate = $input['effectiveDate'] ?? date('Y-m-d');
$leaveYear = (int)$input['leaveYear'];
$reason = $input['reason'] ?? '';
$performedBy = $_SESSION['username'];

try {
    $db->beginTransaction();

    // 1. Get current balance
    $stmt = $db->prepare("
        SELECT annual_leave_balance, remaining_leave_balance 
        FROM employees 
        WHERE nin = ?
    ");
    $stmt->execute([$nin]);
    $current = $stmt->fetch();
    
    if (!$current) {
        throw new Exception("Employé non trouvé");
    }

    // 2. Calculate new value
    $field = $leaveType === 'annual' ? 'annual_leave_balance' : 'remaining_leave_balance';
    $previousValue = (float)$current[$field];
    
    switch ($operation) {
        case 'add':
            $newValue = $previousValue + $days;
            $daysChanged = $days;
            break;
        case 'subtract':
            $newValue = $previousValue - $days;
            $daysChanged = -$days;
            break;
        case 'set':
            $newValue = $days;
            $daysChanged = $days - $previousValue;
            break;
        default:
            throw new Exception("Type d'opération invalide");
    }

    // Validate new value
    if ($newValue < 0) {
        throw new Exception("Le solde ne peut pas être négatif");
    }
    
    if ($leaveType === 'remaining' && $newValue > 90) {
        throw new Exception("Les reliquats ne peuvent pas dépasser 90 jours");
    }

    // 3. Update balance
    $stmt = $db->prepare("
        UPDATE employees 
        SET $field = ?,
            last_leave_balance_update = NOW()
        WHERE nin = ?
    ");
    $stmt->execute([$newValue, $nin]);

    // 4. Log in history
    $stmt = $db->prepare("
        INSERT INTO leave_balance_history 
        (employee_nin, operation_date, operation_type, leave_year, month, days_added, 
         previous_balance, new_balance, performed_by, notes)
        VALUES (?, NOW(), 'manual', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nin,
        $leaveYear,
        date('m', strtotime($effectiveDate)),
        $daysChanged,
        $previousValue,
        $newValue,
        $performedBy,
        "Ajustement manuel: " . $reason
    ]);

    // 5. Log adjustment request
    $stmt = $db->prepare("
        INSERT INTO leave_adjustments 
        (employee_nin, request_date, requested_by, approved_by, adjustment_date, 
         leave_type, days_change, reason, status)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 'approved')
    ");
    $stmt->execute([
        $nin,
        $performedBy,
        $performedBy,
        $effectiveDate,
        $leaveType,
        $daysChanged,
        $reason
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Solde mis à jour avec succès',
        'data' => [
            'previous' => $previousValue,
            'new' => $newValue,
            'change' => $daysChanged
        ]
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>