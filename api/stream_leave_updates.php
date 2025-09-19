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
// SSE Requires no output buffering
if (ob_get_level()) {
    ob_end_clean();
}
// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Useful for Nginx

require_once dirname(__DIR__) . '/config/config.php'; // Adjusted path
require_once dirname(__DIR__) . '/includes/auth.php';   // Adjusted path
require_once dirname(__DIR__) . '/includes/functions.php'; // Adjusted path

// Ensure only authorized users can run this
if (!isAdminOrHR()) { // Assuming isAdminOrHR() is defined in auth.php
    send_sse_message(['event' => 'error', 'message' => 'Accès non autorisé.']);
    exit();
}

// Log file for this script's operations
$logFile = dirname(__DIR__) . '/admin/leave_system_updates_log.txt'; // Path relative to this script or absolute

function send_sse_message($data) {
    if (is_array($data) && isset($data['event'])) {
        echo "event: " . $data['event'] . "\n";
        echo "data: " . json_encode($data) . "\n\n";
    } elseif (is_array($data)) { // Default to 'message' event if not specified
        echo "event: message\n";
        echo "data: " . json_encode($data) . "\n\n";
    } else { // Simple string message
        echo "event: message\n";
        echo "data: " . json_encode(['message' => $data]) . "\n\n";
    }
    // Force the data to be sent to the client
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// Start session if not already started (needed for $_SESSION['user_id'])
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

global $db; // Ensure $db is accessible

$currentRunYear = (int)date('Y');
$currentRunMonth = (int)date('m');

send_sse_message(['event' => 'info', 'message' => 'Initialisation de la mise à jour du système pour ' . strftime('%B %Y', mktime(0,0,0,$currentRunMonth,1,$currentRunYear)) . '.']);
usleep(500000); // 0.5 second delay

// ==== CHECK IF 2.5 DAYS ACCRUAL ALREADY DONE FOR THIS MONTH ==== //
try {
    $checkLogStmt = $db->prepare("SELECT id, notes FROM monthly_leave_accruals_log WHERE accrual_year = ? AND accrual_month = ?");
    $checkLogStmt->execute([$currentRunYear, $currentRunMonth]);
    $existingLog = $checkLogStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingLog) {
        $alreadyDoneMsg = "L'ajout mensuel de 2.5 jours de congé pour " . strftime('%B %Y', mktime(0,0,0,$currentRunMonth,1,$currentRunYear)) . " a déjà été effectué.";
        send_sse_message(['event' => 'info', 'message' => $alreadyDoneMsg . (isset($existingLog['notes']) ? ' (Détails: '.$existingLog['notes'].')' : '') ]);
        send_sse_message(['event' => 'progress', 'value' => 100, 'message' => 'Déjà effectué.']);
        send_sse_message(['event' => 'complete', 'message' => 'Mise à jour des congés pour ce mois déjà traitée.']);
        exit();
    }
} catch (PDOException $e) {
    error_log("SSE Error checking monthly_leave_accruals_log: " . $e->getMessage());
    send_sse_message(['event' => 'error', 'message' => 'Erreur de base de données lors de la vérification du journal des attributions.']);
    exit();
}

// ==== PROCEED WITH ADDING 2.5 DAYS ==== //
send_sse_message(['event' => 'info', 'message' => 'Début du processus d\'ajout de 2.5 jours de congé...']);
usleep(500000);

$employeesStmt = $db->prepare("SELECT nin, first_name, last_name, annual_leave_balance FROM employees WHERE status = 'active'");
$employeesStmt->execute();
$active_employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
$totalEmployees = count($active_employees);
$processedCount = 0;
$failed_updates_count = 0;

if (!empty($active_employees)) {
    $updateEmployeeStmt = $db->prepare("UPDATE employees SET annual_leave_balance = annual_leave_balance + 2.5, updated_at = NOW() WHERE nin = ?");
    
    $db->beginTransaction();
    try {
        foreach ($active_employees as $emp) {
            $processedCount++;
            $progress = round(($processedCount / $totalEmployees) * 100);
            
            $oldBalance = $emp['annual_leave_balance'];
            $newBalance = (float)$oldBalance + 2.5; // Ensure float calculation

            if (!$updateEmployeeStmt->execute([$emp['nin']])) {
                $failed_updates_count++;
                $errorInfo = $updateEmployeeStmt->errorInfo();
                $errorMessage = "Échec MAJ solde pour {$emp['first_name']} {$emp['last_name']} (NIN: {$emp['nin']}). Erreur DB: " . ($errorInfo[2] ?? 'Inconnue');
                send_sse_message(['event' => 'error', 'message' => $errorMessage]);
                file_put_contents($logFile, "[SSE ERROR ".date('Y-m-d H:i:s')."] ".$errorMessage."\n", FILE_APPEND);
            } else {
                $log_message_ui = "{$emp['first_name']} {$emp['last_name']} (NIN: {$emp['nin']}) : Solde {$oldBalance}j &rarr; {$newBalance}j.";
                send_sse_message(['event' => 'employee_update', 'employee' => [
                    'nin' => $emp['nin'], 
                    'full_name' => $emp['first_name'] . ' ' . $emp['last_name'],
                    'days_added' => 2.5,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance
                ], 'log_message' => $log_message_ui ]);
                
                $log_message_file = "[SSE UPDATE ".date('Y-m-d H:i:s')."] +2.5 jours à {$emp['first_name']} {$emp['last_name']} (NIN: {$emp['nin']}); Ancien: {$oldBalance}, Nouveau: {$newBalance}";
                file_put_contents($logFile, $log_message_file."\n", FILE_APPEND);
            }
            send_sse_message(['event' => 'progress', 'value' => $progress, 'message' => "Employé {$processedCount}/{$totalEmployees} traité."]);
            usleep(100000); // Small delay for UI to update, adjust as needed
        }

        if ($failed_updates_count == 0) {
            $logAccrualStmt = $db->prepare("INSERT INTO monthly_leave_accruals_log (accrual_year, accrual_month, executed_by_user_id, notes) VALUES (?, ?, ?, ?)");
            $logAccrualStmt->execute([$currentRunYear, $currentRunMonth, $_SESSION['user_id'] ?? null, $totalEmployees . " employé(s) actifs mis à jour."]);
            $db->commit();
            send_sse_message(['event' => 'complete', 'message' => "Mise à jour mensuelle des congés (+2.5 jours) effectuée avec succès pour " . $totalEmployees . " employé(s)."]);
        } else {
            $db->rollBack();
            send_sse_message(['event' => 'error', 'message' => "{$failed_updates_count} mise(s) à jour de solde ont échoué. Le lot a été annulé. Vérifiez les logs."]);
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("SSE Transactional error during 2.5 days update: " . $e->getMessage());
        send_sse_message(['event' => 'error', 'message' => "Erreur transactionnelle majeure: " . $e->getMessage()]);
    }
} else {
    send_sse_message(['event' => 'info', 'message' => "Aucun employé actif trouvé pour l'ajout mensuel des jours de congé."]);
    // Log that the process was "run" for this month even if no employees, to prevent re-execution.
    try {
        $logAccrualStmt = $db->prepare("INSERT INTO monthly_leave_accruals_log (accrual_year, accrual_month, executed_by_user_id, notes) VALUES (?, ?, ?, ?)");
        $logAccrualStmt->execute([$currentRunYear, $currentRunMonth, $_SESSION['user_id'] ?? null, "Aucun employé actif à mettre à jour ce mois-ci."]);
        send_sse_message(['event' => 'complete', 'message' => 'Mise à jour des congés : Aucun employé actif à traiter. Processus marqué comme exécuté pour le mois.']);
    } catch (PDOException $e) { // Catch potential duplicate entry if somehow logged by another process simultaneously
        error_log("SSE Error logging 'no active employees': " . $e->getMessage());
        if ($e->getCode() == '23000') { // Integrity constraint violation (likely duplicate for year/month)
             send_sse_message(['event' => 'info', 'message' => "L'entrée du journal pour 'aucun employé actif' ce mois-ci existe déjà."]);
             send_sse_message(['event' => 'complete', 'message' => 'Mise à jour des congés : Aucun employé actif à traiter.']);
        } else {
            send_sse_message(['event' => 'error', 'message' => "Erreur lors de la journalisation pour 'aucun employé actif'."]);
        }
    }
}

// End the SSE stream
exit(); 
?>