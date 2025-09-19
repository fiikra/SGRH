<?php
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}


$error = '';
$success = '';

// --- Handle POST request to send the reset link ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez recharger la page et réessayer.";
    } else {
        // 2. Validate email address
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $error = "L'adresse email fournie n'est pas valide.";
        } else {
            // Use a try-catch block for database operations
            try {
                // Find user by email (case-insensitive)
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Timing-attack safe: always show a success message
                $success = "Si un compte est associé à cette adresse email, un lien de réinitialisation a été envoyé.";

                if ($user) {
                    // 3. Generate a secure token and link
                    $token = bin2hex(random_bytes(32)); // Raw token for the URL
                    $hashedToken = hash('sha256', $token); // Hashed token for the database
                    $expiresAt = new DateTime('+1 hour');
                    $expiresAtFormatted = $expiresAt->format('Y-m-d H:i:s');

                    // 4. Store the token in the database
                    // First, remove any old tokens for this user
                    $db->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
                    // Insert the new token
                    $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $hashedToken, $expiresAtFormatted]);

                    // Construct the full reset link
                    // The Prout() function should generate the full URL
                    $resetLink = Proute('new_password', ['token' => $token]);

                    // 5. Send the email using your dedicated function
                    send_password_reset_email($email, $resetLink);
                }
            } catch (PDOException $e) {
                error_log("Database Error on password reset request: " . $e->getMessage());
                $error = "Une erreur technique est survenue. L'administrateur a été notifié.";
            } catch (Exception $e) {
                error_log("General Error on password reset request: " . $e->getMessage());
                $error = "Une erreur inattendue est survenue. Veuillez réessayer.";
            }
        }
    }
}

// Generate a new CSRF token for the session if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Réinitialisation de mot de passe";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-lg">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Mot de passe oublié</h2>
                <p class="text-center text-muted mb-4">Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <div class="text-center mt-4">
                         <a href="<?= Proute('login') ?>" class="btn btn-secondary">Retour à la connexion</a>
                    </div>
                <?php else: ?>
                    <form method="post" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">Adresse email</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary">Envoyer le lien</button>
                        </div>
                    </form>
                <?php endif; ?>

                 <?php if (!$success): ?>
                <div class="mt-4 text-center">
                    <a href="<?= Proute('login') ?>">Retour à la connexion</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>