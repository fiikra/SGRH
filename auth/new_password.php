<?php
/**
 * Page for users to set a new password after a reset request.
 */

// It's highly recommended to have a central bootstrap/init file that handles these settings.
// require_once __DIR__ . '/../bootstrap.php';

// --- Production-Ready Error Handling ---
ini_set('display_errors', 0); // CRITICAL: Never display errors in production.
ini_set('log_errors', 1);     // Log errors to the server's log file.
error_reporting(E_ALL);

// --- 1. Validate the Reset Token from the URL ---
$error = '';
$token = $_GET['token'] ?? null;

// The token must exist and be a 64-character hexadecimal string.
if (!$token || !ctype_xdigit($token) || strlen($token) !== 64) {
    $_SESSION['error'] = "Le lien de réinitialisation est invalide ou a expiré.";
    redirect(Proute('login')); // Assuming redirect() and Proute() are defined in your bootstrap.
    exit;
}

// --- 2. Verify Token Against the Database ---
try {
    // Hash the incoming token to match what's stored in the database.
    $hashedToken = hash('sha256', $token);

    $stmt = $db->prepare(
        "SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1"
    );
    $stmt->execute([$hashedToken]);
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRequest) {
        $_SESSION['error'] = "Le lien de réinitialisation est invalide ou a expiré.";
        redirect(Proute('login'));
        exit;
    }
    $userId = $resetRequest['user_id'];

} catch (PDOException $e) {
    error_log("Database error on token verification: " . $e->getMessage());
    $_SESSION['error'] = "Une erreur technique est survenue. Veuillez réessayer.";
    redirect(Proute('login'));
    exit;
}

// --- 3. Handle Form Submission to Update the Password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez recharger la page et réessayer.";
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        try {
            // Validate password rules
            if (empty($password) || empty($confirmPassword)) {
                throw new Exception("Veuillez remplir tous les champs.");
            }
            if ($password !== $confirmPassword) {
                throw new Exception("Les mots de passe ne correspondent pas.");
            }
            if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
                throw new Exception("Le mot de passe doit contenir au moins 8 caractères, avec des lettres et des chiffres.");
            }

            // Hash the new password securely
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Use a transaction to ensure data integrity
            $db->beginTransaction();

            // Update user's password
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);

            // Invalidate all reset tokens for this user
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$userId]);

            $db->commit();

            // Success: Redirect to login with a success message
            $_SESSION['success'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
            redirect(Proute('login'));
            exit;

        } catch (Exception $e) {
            // If anything fails, roll back the transaction
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
            error_log("Password reset failed for user ID {$userId}: " . $e->getMessage());
        }
    }
}

$pageTitle = "Nouveau mot de passe";
include __DIR__ . '/../includes/header.php'; // Use __DIR__ for robust path
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-lg">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Créer un nouveau mot de passe</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
                        <div id="passwordHelp" class="form-text">Doit contenir au moins 8 caractères, incluant des lettres et des chiffres.</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Enregistrer le mot de passe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; // Use __DIR__ for robust path ?>