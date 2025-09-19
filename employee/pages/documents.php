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
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

redirectIfNotLoggedIn();

// Récupérer les informations de l'employé
$stmt = $db->prepare("SELECT e.* FROM employees e 
                     JOIN users u ON e.user_id = u.id 
                     WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Profil employé non trouvé";
    header("Location: /auth/logout.php");
    exit();
}

// Récupérer les documents de l'employé
$documents = $db->prepare("SELECT * FROM employee_documents WHERE employee_nin = ? ORDER BY upload_date DESC");
$documents->execute([$employee['nin']]);

$pageTitle = "Mes Documents";
 include $_SERVER['DOCUMENT_ROOT'] .'/includes/header.php'; 
?>

<div class="container">
    <h1 class="mb-4">Mes Documents</h1>
    
    <div class="card">
        <div class="card-body">
            <?php if ($documents->rowCount() > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Titre</th>
                                <th>Date Upload</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = $documents->fetch()): ?>
                                <tr>
                                    <td><?= ucfirst($doc['document_type']) ?></td>
                                    <td><?= $doc['title'] ?></td>
                                    <td><?= formatDate($doc['upload_date'], 'd/m/Y H:i') ?></td>
                                    <td>
                                        <a href="/<?= $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-success">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                        <a href="/<?= $doc['file_path'] ?>" download class="btn btn-sm btn-primary">
                                            <i class="bi bi-download"></i> Télécharger
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Aucun document trouvé dans votre dossier.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] .'/includes/footer.php'; ?>