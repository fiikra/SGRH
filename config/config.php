<?php
// --- PROJECT_ROOT MUST be defined first! ---
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}



define('APP_NAME', 'SGRH - Système de Gestion des Ressources Humaines');
define('APP_LINK', 'http://localhost');
define('BASE_URL', 'http://localhost');
define('APP_VERSION', '3.0.1');
define('APP_DEBUG', false);

// --- NOW safely include the database config ---
require_once __DIR__ . '/database.php';


// --- Error Reporting ---
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// --- Project Root (never expose outside webroot for config/database) ---
if (!defined('PROJECT_ROOT')) {
    // Use dirname(__DIR__, 1) for one level up, adjust as needed
    define('PROJECT_ROOT', dirname(__DIR__));
}

// --- Autoloader for Libraries (Hardened) ---
if (defined('PROJECT_ROOT')) {
    spl_autoload_register(function ($class_name) {
        $prefixes = [
            'PhpOffice\\PhpSpreadsheet\\' => PROJECT_ROOT . '/lib/PhpSpreadsheet/',
            'Psr\\SimpleCache\\'          => PROJECT_ROOT . '/lib/psr/simple-cache/src/',
            'Composer\\Pcre\\'            => PROJECT_ROOT . '/lib/composer/pcre/src/',
            'ZipStream\\'                 => PROJECT_ROOT . '/lib/maennchen/zipstream-php/src/',
            // Add more as needed...
        ];

        foreach ($prefixes as $prefix => $base_dir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class_name, $len) !== 0) continue;
            $relative_class = substr($class_name, $len);
            $file = rtrim($base_dir, '/') . '/' . str_replace('\\', '/', $relative_class) . '.php';
            // Protect against directory traversal
            if (strpos($file, '..') !== false) {
                error_log("SECURITY ALERT: Directory traversal attempt in autoloader with class $class_name");
                continue;
            }
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
        // Secure: do not output error details to user
    });
} else {
    error_log("CRITICAL ERROR: PROJECT_ROOT constant is not defined. Autoloader for libraries cannot be set up.");
    // die("Critical configuration error.");
}
// Define the absolute path to the project's root directory
define('APP_ROOT', dirname(__DIR__)); // This assumes config.php is in a /config folder one level down from the root


// Use PROJECT_ROOT if defined, else fallback

if (defined('PROJECT_ROOT')) {

    define('UPLOAD_DIR', PROJECT_ROOT . '/assets/uploads/');

} else {

    define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');

}

define('MAX_FILE_SIZE', 5 * 1024 * 1024);

define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// --- Further hardening for uploads recommended ---

// - Randomize filenames before saving

// - Validate file type by content, not just extension or MIME

// - Store uploads outside webroot, serve via PHP if possible

// - Set proper permissions on upload dir

?>