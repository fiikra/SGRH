<?php

// --- Hardened Database Configuration ---

ini_set('display_errors', 1);

error_reporting(E_ALL);

// For production: use environment variables for credentials

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');

define('DB_USER', getenv('DB_USER') ?: 'root');

define('DB_PASS', getenv('DB_PASS') ?: '');

define('DB_NAME', getenv('DB_NAME') ?: 'hr_system');



// Optional: Allow overriding charset from environment

$charset = getenv('DB_CHARSET') ?: 'utf8mb4';



// --- PDO Connection with Security ---

try {

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset;

    $options = [

        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Associative arrays only

        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements

        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset"

    ];

    $db = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch(PDOException $e) {

    // Never show PDO errors to end users!

    if (defined('APP_DEBUG') && APP_DEBUG) {

        die("Database connection error: " . htmlspecialchars($e->getMessage()));

    } else {

        error_log("Database connection error: " . $e->getMessage());

        die("Database unavailable. Please try again later.");

    }

}




?>