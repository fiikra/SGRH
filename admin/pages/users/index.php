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



// --- Generic error handler (don't leak errors to users in production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Prevent session fixation (regenerate on login or privilege change) ---
// If you do login logic here, always call session_regenerate_id(true) after login

require_once 'config/config.php';
require_once 'includes/auth.php';

// --- Rediriger les utilisateurs connectés vers leur tableau de bord ---
if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? 'admin/dashboard.php' : 'employee/dashboard.php'));
    exit();
}

// --- Render page as usual ---
$pageTitle = "Accueil - Système de Gestion RH";
include 'includes/header.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="display-4 fw-bold">Système de Gestion des Ressources Humaines</h1>
                <p class="lead">Solution complète pour la gestion du personnel, des congés et des documents</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="auth/login.php" class="btn btn-primary btn-lg px-4">Connexion</a>
                    <?php if (isAdmin()): ?>
                        <a href="auth/register.php" class="btn btn-outline-secondary btn-lg px-4">Créer un compte</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <img src="./assets/images/hero-illustration.svg.webp" alt="Gestion RH" class="img-fluid d-none d-md-block">
            </div>
        </div>
    </div>
</div>

<div class="features-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Fonctionnalités Principales</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon bg-primary bg-gradient mb-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3>Gestion des Employés</h3>
                        <p>Gérez les fiches employés complètes avec photos, documents et informations professionnelles.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon bg-success bg-gradient mb-3">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h3>Gestion des Congés</h3>
                        <p>Suivez et approuvez les demandes de congé avec un système de workflow intégré.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="feature-icon bg-info bg-gradient mb-3">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h3>Documents & Attestations</h3>
                        <p>Générez automatiquement des attestations de travail et archivez les documents.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>