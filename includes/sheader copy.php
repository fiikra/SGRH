<?php

/**
 * Main Router & Security Entry Point
 */

// --- Session Handling: Must come first ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 3600, // 1 hour session
        'path' => '/',
        'domain' => '', // Set your domain if needed
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}
session_regenerate_id(true);

// --- Security Headers ---
header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload"); // 2 years HSTS
header("X-Frame-Options: DENY"); // Prevent clickjacking
header("X-Content-Type-Options: nosniff"); // Prevent MIME sniffing
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");

// --- Content Security Policy ---
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com 'unsafe-inline'; " .
    "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
    "connect-src 'self'; " . // prevents third-party APIs unless explicitly allowed
    "object-src 'none'; " .
    "media-src 'none'; " .
    "frame-ancestors 'none'; " .
    "form-action 'self'; " .
    "base-uri 'self';");

// --- Disable Caching for Sensitive Pages ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// --- Optional: Enforce HTTPS (better in server config) ---
// if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
//     $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
//     header("Location: $httpsUrl", true, 301);
//     exit();
// }

// --- Define Constant to Prevent Direct File Access ---
define('APP_SECURE_INCLUDE', true);

// --- CSRF Protection ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('CSRF validation failed. Request blocked.');
    }
}



// --- Idle Session Timeout (2 hours here) ---
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > 36000000) {
    session_unset();
    session_destroy();
    $login_url = rtrim(APP_LINK, '/') . '/index.php?route=login&timeout=1';
    header("Location: $login_url", true, 302);
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

?>