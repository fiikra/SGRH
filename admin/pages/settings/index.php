<?php
/**
 * Page: Company Settings
 *
 * Manages all global settings for the company, including legal information,
 * HR policies, SMTP, and organizational structure.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

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