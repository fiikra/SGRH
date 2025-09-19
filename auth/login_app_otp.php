<?php
/**
 * Handles the second factor of authentication (TOTP via an Authenticator App).
 */
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

require_once __DIR__.'/../lib/TwoFactorAuth/TwoFactorAuth.php';
require_once __DIR__.'/../lib/TwoFactorAuth/TwoFactorAuthException.php';
require_once __DIR__.'/../lib/TwoFactorAuth/Algorithm.php';
require_once __DIR__. '/../lib/TwoFactorAuth/Providers/Rng/IRNGProvider.php';
require_once __DIR__.'/../lib/TwoFactorAuth/Providers/Rng/CSRNGProvider.php';
require_once __DIR__. '/../lib/TwoFactorAuth/Providers/Time/ITimeProvider.php';
require_once __DIR__.'/../lib/TwoFactorAuth/Providers/Time/LocalMachineTimeProvider.php';
require_once __DIR__. '/../lib/TwoFactorAuth/Providers/Qr/IQRCodeProvider.php';
require_once __DIR__.'/../lib/TwoFactorAuth/Providers/Qr/BaseHTTPQRCodeProvider.php';
require_once __DIR__. '/../lib/TwoFactorAuth/Providers/Qr/QRServerProvider.php';

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;
use RobThree\Auth\Providers\Qr\QRServerProvider;

if (!isset($_SESSION['otp_app_user_id'])) {
    redirect(Proute('login'));
}
$user_id = $_SESSION['otp_app_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        flash('error', 'Erreur de sécurité. Veuillez réessayer.', 'now');
    } else {
        try {
            $tfa = new TwoFactorAuth(new QRServerProvider());
            $submitted_code = sanitize($_POST['otp_code']);

            $stmt = $db->prepare("SELECT otp_secret, role, username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['otp_secret'])) {
                throw new Exception("La configuration 2FA de l'utilisateur est introuvable ou corrompue.");
            }

            $decrypted_secret = $user['otp_secret']; // TODO: Replace with your decryption function

            if ($tfa->verifyCode($decrypted_secret, $submitted_code)) {
                // --- SUCCESS ---
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                $db->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?")->execute([$user_id]);
                
                // Create remember me token if requested
                if (isset($_SESSION['request_remember_me']) && $_SESSION['request_remember_me']) {
                    // We need the create_remember_me_token function here.
                    // Ensure it's available via a global functions file.
                    create_remember_me_token($db, $user_id);
                }
                
                // Clean up all temporary session data
                unset($_SESSION['otp_app_user_id'], $_SESSION['otp_user_id'], $_SESSION['request_remember_me']);
                
                redirect(APP_LINK . '/admin/');
            } else {
                flash('error', 'Code invalide.', 'now');
            }
        } catch (Exception $e) {
            flash('error', $e->getMessage(), 'now');
        }
    }
}

$pageTitle = "Vérification par Application";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Vérification par App</h2>
                <p class="text-center text-muted">Veuillez saisir le code de votre application d'authentification.</p>
                
                <?php display_flash_messages(); ?>
                
                <form method="post" action="<?php Proute('login_app_otp'); ?>" autocomplete="off">
                       <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                       <div class="mb-3">
                           <label for="otp_code" class="form-label">Code d'application</label>
                           <input type="text" class="form-control form-control-lg text-center" id="otp_code" name="otp_code" required maxlength="6" inputmode="numeric" autofocus autocomplete="one-time-code">
                       </div>
                       <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Se Connecter</button>
                       </div>
                </form>

                <div class="mt-3 text-center">
                    <a href="<?= Proute('login_email_otp',['action'=>'request_email']) ?>">Utiliser l'authentification par email</a>
                </div>

                <div class="mt-2 text-center">
                    <a href="<?= Proute('login'); ?>">Retour à la connexion</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>