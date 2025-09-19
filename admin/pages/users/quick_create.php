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
include_once("../../includes/functions.php");
// Ensure no output before headers
ob_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Set headers first to prevent any output
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only admin/HR can access
if (!isAdmin() && !isHR()) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]));
}

if (!isset($_GET['nin'])) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Paramètre NIN manquant'
    ]));
}

// Clean any output buffer
ob_end_clean();

try {
    $nin = sanitize($_GET['nin']);
    $db->beginTransaction();

    // 1. Verify employee exists and has no account
    $stmt = $db->prepare("SELECT e.first_name, e.last_name, u.id as user_id 
                         FROM employees e 
                         LEFT JOIN users u ON e.user_id = u.id 
                         WHERE e.nin = ?");
    $stmt->execute([$nin]);
    $employee = $stmt->fetch();

    if (!$employee) {
        http_response_code(404);
        die(json_encode([
            'success' => false,
            'message' => 'Employé non trouvé'
        ]));
    }

    if ($employee['user_id']) {
        die(json_encode([
            'success' => false,
            'message' => 'Cet employé a déjà un compte utilisateur'
        ]));
    }

    // 2. Generate credentials
    $username = 'USER-' . strtoupper(substr($employee['first_name'], 0, 1)) . 
                substr($employee['last_name'], 0, 3) . rand(100, 999);
    $password = generateRandomPassword(8);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $email = strtolower($employee['first_name'][0] . $employee['last_name']) . '@entreprise.com';

    // 3. Create user account
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'employee')");
    $stmt->execute([$username, $email, $hashedPassword]);
    $userId = $db->lastInsertId();

    // 4. Link to employee
    $stmt = $db->prepare("UPDATE employees SET user_id = ? WHERE nin = ?");
    $stmt->execute([$userId, $nin]);

    $db->commit();

    // 5. Return success response
    echo json_encode([
        'success' => true,
        'username' => $username,
        'password' => $password,
        'employee_first_name' => $employee['first_name'],
        'employee_last_name' => $employee['last_name']
    ]);

} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}

