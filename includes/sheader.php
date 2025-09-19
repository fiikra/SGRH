<?php

/**
 * Main Router & Security Entry Point
 */

// ✅ BEST PLACE: Put session handling right at the top.

// --- Reinforced Security Headers ---
header("Permissions-Policy: camera=(self), microphone=(), geolocation=(self), payment=()");

//header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " . // ✅ Allows inline styles (style="...")
    "script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com 'unsafe-inline'; " . // ✅ Allows jQuery CDN and inline scripts
    "img-src 'self' data:; " .
    "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " . // Added Google Fonts just in case
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'none';");
/*
// --- Force HTTPS if not already (optional, best to do in server config) ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $httpsUrl", true, 301);
    exit();
}
*/
// --- Constant to Block Direct File Access ---
define('APP_SECURE_INCLUDE', true);

// --- Hardened Session Handling ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 7200, // 2 hour
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}
session_regenerate_id(true);

// --- CSRF Token Generation & Validation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// **CRITICAL**: Validate CSRF token on every POST request to prevent attacks.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF validation failed. Request blocked.');
    }
}
require_once __DIR__ . '../../config/config.php';
// --- Session timeout (30min idle) ---
if (isset($_SESSION['LAST_ACTIVITY']) && time() - $_SESSION['LAST_ACTIVITY'] > 7200) {
    session_unset();
    session_destroy();
    $login_url = rtrim(APP_LINK, '/') . '/index.php?route=login&timeout=1';
    header("Location:". $login_url, true, 302);
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

?>