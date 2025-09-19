<?php
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// Hide E_DEPRECATED notices, but show all other errors
error_reporting(E_ALL & ~E_DEPRECATED);
//security headers
require_once __DIR__ . '/includes/sheader.php'; 
// --- Core Application Files ---
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ .  '/includes/mailer.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/flash_messages.php';


// --- Route Definitions ---
$routes = [
    // Your full list of routes here...
    'home' => 'home.php',
    'login' => '/auth/login.php',
    'logout' => '/auth/logout.php',
    'new_password' => '/auth/new_password.php',
    'reset_password' => '/auth/reset.php',
    'user_add' => '/auth/users.php',
    'profile' => '/auth/profile.php',
    'employee' => '/employee/index.php',
    'login_app_otp' => '/auth/login_app_otp.php',
    'login_email_otp' => '/auth/login_otp.php',
    'api_public_feed' => 'api/public_feed.php' // Route for our API  pointage 
   
];


// --- Routing & Dispatching Logic ---
$route = $_GET['route'] ?? 'home';

// **CRITICAL**: Validate that the requested route exists in our defined list.
if (!array_key_exists($route, $routes)) {
    http_response_code(404);
    // Optionally include a nice 404 page
    // require_once __DIR__ . '/pages/404.php';
    die('404 - Page Not Found');
}

// The route is valid, proceed to load the file.
$page = $routes[$route];
$target = __DIR__ . '/' . $page;

// Final check that the file physically exists before including.
if (!file_exists($target)) {
    http_response_code(500);
    // This indicates a server configuration error, not a user error.
    die('500 - Server Error: Route file is missing.');
}

// Load the target page.
require_once $target;