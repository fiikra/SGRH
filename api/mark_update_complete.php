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

header('Content-Type: application/json');

if (!isAdminOrHR()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    // Mark update as complete for today
    $lastUpdateKey = 'last_leave_update_' . date('Y-m-d');
    $_SESSION[$lastUpdateKey] = true;
    unset($_SESSION['update_in_progress']);
    
    // Determine redirect URL
    $redirectUrl = '/admin/dashboard.php';
    if (isset($_SESSION['redirect_after_update'])) {
        $redirectUrl = $_SESSION['redirect_after_update'];
        unset($_SESSION['redirect_after_update']);
    }
    
    echo json_encode([
        'success' => true,
        'redirect' => $redirectUrl,
        'message' => 'Mise à jour marquée comme complète'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}