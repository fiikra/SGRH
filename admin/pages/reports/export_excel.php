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
// Adjust these paths based on where you placed the PhpSpreadsheet library
require_once '\lib/PhpSpreadsheet/src/PhpSpreadsheet/Spreadsheet.php';
require_once '\lib/PhpSpreadsheet/src/PhpSpreadsheet/Writer/Xlsx.php'; // For .xlsx
// If you want .xls (older format):
// require_once 'path/to/PhpSpreadsheet/src/PhpSpreadsheet/Writer/Xls.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// If using .xls:
// use PhpOffice\PhpSpreadsheet\Writer\Xls;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = json_decode(file_get_contents('php://input'), true);
    $headers = $postData['headers'] ?? [];
    $data = $postData['data'] ?? [];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    // Set data
    $row = 2;
    foreach ($data as $rowData) {
        $col = 'A';
        foreach ($rowData as $cellValue) {
            $sheet->setCellValue($col . $row, $cellValue);
            $col++;
        }
        $row++;
    }

    // Set response headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); // For .xlsx
    header('Content-Disposition: attachment;filename="historique_attestations.xls"'); // Or .xls
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet); // Or new Xls($spreadsheet); for .xls
    $writer->save('php://output');
    exit;
} else {
    http_response_code(405);
    echo 'Method Not Allowed';
}
?>