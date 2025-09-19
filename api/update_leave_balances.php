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
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdminOrHR()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    $db->beginTransaction();
    
    $currentDate = new DateTime();
    $currentMonth = (int)$currentDate->format('m');
    $currentYear = (int)$currentDate->format('Y');
    $leaveYear = ($currentMonth >= 7) ? $currentYear : $currentYear - 1;
    $isJuly1st = ($currentMonth == 7 && $currentDay == 1);
    $performedBy = $_SESSION['username'];

    // Get all active employees
    $stmt = $db->prepare("SELECT * FROM employees WHERE status = 'active'");
    $stmt->execute();
    $employees = $stmt->fetchAll();

    $results = [
        'total_employees' => count($employees),
        'processed' => 0,
        'carryovers' => 0,
        'monthly_additions' => 0
    ];

    foreach ($employees as $employee) {
        $hireDate = new DateTime($employee['hire_date']);
        $seniority = $hireDate->diff($currentDate);
        $eligible = ($seniority->y >= 1 || ($seniority->y == 0 && $seniority->m >= 6));

        // 1. Handle annual carryover on July 1st
        if ($isJuly1st) {
            $remainingFromLastYear = min($employee['annual_leave_balance'], 90);
            
            if ($remainingFromLastYear > 0) {
                $newRemaining = $employee['remaining_leave_balance'] + $remainingFromLastYear;
                
                // Update balance
                $stmt = $db->prepare("UPDATE employees SET annual_leave_balance = 0, remaining_leave_balance = ? WHERE nin = ?");
                $stmt->execute([$newRemaining, $employee['nin']]);
                
                // Log history
                logLeaveChange(
                    $employee['nin'],
                    'system',
                    $leaveYear,
                    7,
                    $remainingFromLastYear,
                    $employee['remaining_leave_balance'],
                    $newRemaining,
                    $performedBy,
                    'Reliquat annuel transféré'
                );
                
                $results['carryovers']++;
            }
        }

        // 2. Monthly leave accrual (after 15th or July)
        if ($eligible && ($currentDay >= 15 || $currentMonth == 7)) {
            $daysToAdd = 2.5;
            $newAnnualBalance = min($employee['annual_leave_balance'] + $daysToAdd, 30);
            
            // Update balance
            $stmt = $db->prepare("UPDATE employees SET annual_leave_balance = ? WHERE nin = ?");
            $stmt->execute([$newAnnualBalance, $employee['nin']]);
            
            // Log history
            logLeaveChange(
                $employee['nin'],
                'system',
                $leaveYear,
                $currentMonth,
                $daysToAdd,
                $employee['annual_leave_balance'],
                $newAnnualBalance,
                $performedBy,
                'Acquisition mensuelle normale'
            );
            
            $results['monthly_additions']++;
        }

        $results['processed']++;
    }

    $db->commit();
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'message' => "Mise à jour terminée: {$results['processed']} employés traités"
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => "Erreur: " . $e->getMessage()
    ]);
}

function logLeaveChange($nin, $type, $year, $month, $daysAdded, $prevBalance, $newBalance, $user, $notes) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO leave_balance_history 
        (employee_nin, operation_date, operation_type, leave_year, month, days_added, previous_balance, new_balance, performed_by, notes)
        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nin, $type, $year, $month, $daysAdded, $prevBalance, $newBalance, $user, $notes]);
}
?>