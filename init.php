<?php
/**
 * Application Initialization and Secure Configuration Paths
 */

// --- Block Direct Access (Security Measure) ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Direct access to this file is not allowed.');
}

// --- Define Application Constants ---
define('APP_ROOT', realpath(__DIR__)); // More reliable with symbolic links
define('CONFIG_PATH', APP_ROOT . '/config/config.php');
define('INCLUDES_PATH', APP_ROOT . '/includes');
define('ADMINModel', APP_ROOT . '/admin/pages/model/');
define('ADMINController', APP_ROOT . '/admin/pages/controller/');
define('ADMINViews', APP_ROOT . '/admin/pages/views/');

// --- Optional: Autoloading Setup (if using classes or Composer) ---
// require_once APP_ROOT . '/vendor/autoload.php';

// --- Load Main Configuration ---
if (file_exists(CONFIG_PATH)) {
    require_once CONFIG_PATH;
} else {
    error_log("Critical: Missing config file at " . CONFIG_PATH);
    http_response_code(500);
    exit('Configuration error.');
}
?>