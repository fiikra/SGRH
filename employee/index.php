<?php
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED); // Hide E_DEPRECATED, show others

// ✅ CORRECTED: Use forward slashes for directory separators for better compatibility.
require_once __DIR__ . '../../includes/sheader.php'; 
require_once __DIR__ . '../../config/config.php';
require_once __DIR__ . '../../includes/auth.php';
require_once __DIR__ . '../../includes/functions.php';
require_once __DIR__ . '../../includes/mailer.php';
require_once __DIR__ . '../../includes/flash.php';
require_once __DIR__ . '../../includes/flash_messages.php';

redirectIfNotLoggedIn();
redirectIfAdminorHR();

// ✅ CORRECTED: The routes array should only contain the filename, not the path.
$routes = [
    'dashboard'       => 'home.php',
    'certificates'    => 'certificates.php',
    'documents'       => 'documents.php',
    'leave'           => 'leave.php',
    'my_missions'     => 'my_missions.php',
    'request_mission' => 'request_mission.php',
    'view'            => 'view.php',
    'test'            => 'test.php'
];

// --- Routing & Dispatching Logic ---
$route = $_GET['route'] ?? 'dashboard'; // Default to a safe page like dashboard

// **CRITICAL**: Validate that the requested route exists in our defined list.
if (!array_key_exists($route, $routes)) {
    http_response_code(404);
    // require_once __DIR__ . '/pages/404.php'; // Optional: include a nice 404 page
    die('404 - Page Not Found');
}

// ✅ CORRECTED: Build the target path correctly.
$page_filename = $routes[$route];
$target = __DIR__ . '/pages/' . $page_filename; // Corrected the typo and path structure

// Final check that the file physically exists before including.
if (!file_exists($target)) {
    http_response_code(500);
    // This indicates a server configuration error, not a user error.
    error_log("Routing Error: The file for route '{$route}' was not found at path: {$target}");
    die('500 - Server Error: Route file is missing.');
}

// Load the target page.
require_once $target;