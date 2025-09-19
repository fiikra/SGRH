<?
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
$employees = $db->query("SELECT nin, first_name, last_name FROM employees ORDER BY last_name, first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nin = sanitize($_POST['employee_nin']);
        $startDate = sanitize($_POST['start_date']);
        $endDate = sanitize($_POST['end_date']);
        $leaveType = sanitize($_POST['leave_type']);
        $reason = sanitize($_POST['reason']);

        // Calculer le nombre de jours
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff(targetObject: $end);
        $days = $interval->days + 1; // Inclure le premier jour

        // Fetch employee's annual leave balance
        $stmt = $db->prepare("SELECT annual_leave_balance FROM employees WHERE nin = ?");
        $stmt->execute([$nin]);
        $employeeData = $stmt->fetch();

        if ($leaveType === 'annuel' && ($employeeData['annual_leave_balance'] < $days)) {
            throw new Exception("Employee does not have enough annual leave balance.");
        }

        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO leave_requests
            (employee_nin, leave_type, start_date, end_date, days_requested, reason, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())");

        $stmt->execute([
            $nin,
            $leaveType,
            $startDate,
            $endDate,
            $days,
            $reason,
        ]);

        // Reduce annual leave balance if applicable
        if ($leaveType === 'annuel') {
            $newBalance = $employeeData['annual_leave_balance'] - $days;
            $stmt = $db->prepare("UPDATE employees SET annual_leave_balance = ? WHERE nin = ?");
            $stmt->execute([$newBalance, $nin]);
        }

        $db->commit();

        $_SESSION['success'] = "Congé enregistré avec succès";
        header("Location: requests.php");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}



