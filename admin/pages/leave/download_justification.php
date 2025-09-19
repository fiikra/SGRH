<?php
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { http_response_code(404); exit('Not found'); }

// 1. Fetch leave request and check permission
$stmt = $db->prepare("SELECT justification_path, employee_nin FROM leave_requests WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data || empty($data['justification_path'])) {
    http_response_code(404); exit('File not found');
}

// 2. (Optional) Check user is allowed to view this file (e.g., is HR/admin or owner)
//if (!isUserHR() && $_SESSION['user_nin'] !== $data['employee_nin']) {
   // http_response_code(403); exit('Forbidden');
//}

// 3. Clean up and build absolute path
// Assume: PROJECT_ROOT points to your project root (where index.php is)
// $data['justification_path'] is like 'assets/uploads/sick_justifications/filename.pdf'
$filePath = realpath(PROJECT_ROOT . '/' . $data['justification_path']);

$uploadsBaseDir = realpath(PROJECT_ROOT . '/assets/uploads/sick_justifications');

// 4. Security checks: path must exist, must be inside the allowed folder, and must be a file
if (!$filePath
    || !$uploadsBaseDir
    || strpos($filePath, $uploadsBaseDir) !== 0
    || !is_file($filePath)
) {
    http_response_code(404); exit('File not found');
}

// 5. Serve file securely
$finfo = finfo_open(FILEINFO_MIME_TYPE);
header('Content-Type: ' . finfo_file($finfo, $filePath));
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>