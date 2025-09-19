<?php
// --- Security Headers: Set before any output ---
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');

// --- Enforce Strong Cache Policy ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// --- Harden Session Handling ---
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);

// --- Error Handling ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// --- RBAC: Only admin can access this page ---
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// --- CSRF Protection ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- PROTECTION: Restrict admin creation if at least one admin exists ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $existingAdminCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

// --- FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- CSRF Check ---
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Erreur CSRF. Veuillez recharger la page et réessayer.");
        }

        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = sanitize($_POST['role']);
        $employeeNIN = sanitize($_POST['employee_nin'] ?? null);

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($role)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Adresse email invalide.");
        }

        if ($password !== $confirmPassword) {
            throw new Exception("Les mots de passe ne correspondent pas.");
        }

        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new Exception("Le mot de passe doit contenir au moins 8 caractères, dont des lettres et des chiffres.");
        }

        // --- Restrict admin creation if an admin exists (unless current user is a super admin) ---
        $adminCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($role === 'admin' && $adminCount > 0 && $_SESSION['user_id'] != 1) {
            throw new Exception("La création d'un second compte administrateur est interdite pour des raisons de sécurité.");
        }

        // Vérifier si l'utilisateur existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Un utilisateur avec ce nom ou email existe déjà.");
        }

        // Vérifier si l'employé est déjà associé à un compte
        if (!empty($employeeNIN)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE nin = ? AND user_id IS NOT NULL");
            $stmt->execute([$employeeNIN]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet employé est déjà associé à un compte utilisateur.");
            }
        }

        // Hasher le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Créer l'utilisateur (transaction)
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $role]);
        $userId = $db->lastInsertId();

        // Associer à un employé si NIN fourni
        if (!empty($employeeNIN)) {
            $stmt = $db->prepare("UPDATE employees SET user_id = ? WHERE nin = ?");
            $stmt->execute([$userId, $employeeNIN]);
        }

        $db->commit();

        $_SESSION['success'] = "Utilisateur créé avec succès";
        header("Location: users.php");
        exit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

// Récupérer la liste des employés sans compte
$employees = $db->query("SELECT nin, first_name, last_name FROM employees WHERE user_id IS NULL ORDER BY last_name, first_name")->fetchAll();

$pageTitle = "Créer un Utilisateur";
include '../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Créer un Utilisateur</h2>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="post" autocomplete="off" novalidate>
                    <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur*</label>
                        <input type="text" class="form-control" id="username" name="username" required maxlength="100">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email*</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="254">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe*</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$">
                        <small class="text-muted">Au moins 8 caractères, lettres et chiffres</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe*</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle*</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="employee" selected>Employé</option>
                            <option value="hr">RH</option>
                            <?php if ($existingAdminCount == 0 || $_SESSION['user_id'] == 1): ?>
                                <option value="admin">Administrateur</option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">
                            <?php if ($existingAdminCount > 0 && $_SESSION['user_id'] != 1): ?>
                                La création d'un nouvel administrateur est désactivée (seul le superadmin peut créer un autre admin).
                            <?php else: ?>
                                Seul le superadmin peut créer d'autres comptes admin.
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="employee_nin" class="form-label">Associer à un employé (optionnel)</label>
                        <select name="employee_nin" id="employee_nin" class="form-select">
                            <option value="">-- Sélectionner un employé --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['nin']) ?>">
                                    <?= htmlspecialchars($emp['last_name']) ?> <?= htmlspecialchars($emp['first_name']) ?> (<?= htmlspecialchars($emp['nin']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Seuls les employés sans compte sont listés</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Créer le compte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>