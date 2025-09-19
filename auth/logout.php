<?php
/**
 * Handles user logout.
 * Destroys the user's session and clears any persistent "remember me" cookies and tokens.
 */
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

// You will need the database connection to delete the persistent token.
// Ensure this path is correct for your application structure.
require_once __DIR__ . '/../config/database.php'; 

// 1. START THE SESSION TO ACCESS ITS DATA.
session_start();

// 2. CLEAR THE PERSISTENT "REMEMBER ME" COOKIE AND DATABASE TOKEN.
if (isset($_COOKIE['remember_me'])) {
    // Split the cookie into selector and token.
    list($selector, $token) = explode(':', $_COOKIE['remember_me'], 2);

    if ($selector) {
        // Find and delete the token from the database to invalidate it.
        $stmt = $db->prepare("DELETE FROM persistent_logins WHERE selector = ?");
        $stmt->execute([$selector]);
    }
    
    // Instruct the browser to delete the cookie by setting its expiration to the past.
    setcookie('remember_me', '', time() - 3600, '/');
}

// 3. Unset all of the session variables.
$_SESSION = [];

// 4. Invalidate the session cookie itself.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Finally, destroy the session.
session_destroy();

// 6. Redirect to the login page.
redirect(Proute('login'));
exit();
?>