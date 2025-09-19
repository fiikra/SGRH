<?php
if (!defined('APP_SECURE_INCLUDE')) { exit('No direct access allowed'); }
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- REQUIRED LIBRARIES ---
// Note: Adjust the path if your 'lib' folder is not at the root

// --- REQUIRED LIBRARIES ---
require_once __DIR__ . '/../../../lib/TwoFactorAuth/TwoFactorAuth.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/TwoFactorAuthException.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Algorithm.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Qr/IQRCodeProvider.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Qr/BaseHTTPQRCodeProvider.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Qr/QRServerProvider.php';
// --- ADD THESE TWO LINES ---
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Rng/IRNGProvider.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Rng/CSRNGProvider.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Time/ITimeProvider.php';
require_once __DIR__ . '/../../../lib/TwoFactorAuth/Providers/Time/LocalMachineTimeProvider.php';
require_once __DIR__.  '../../../../includes/flash_messages.php';




// --- Initialization ---
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;
use RobThree\Auth\Providers\Qr\QRServerProvider;

redirectIfNotLoggedIn();

try {
    // --- FIX: Correctly instantiate the library with a QR Provider object ---
    $tfa = new TwoFactorAuth(new QRServerProvider());
} catch (TwoFactorAuthException $e) {
    error_log('TFA Init Error: ' . $e->getMessage());
    flash('error', 'Erreur critique du serveur de sécurité. Impossible de continuer.', 'now');
    $tfa = null; 
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id || ($user_id !== $_SESSION['user_id'] && !isAdmin())) {
    flash('error', 'ID utilisateur invalide ou accès non autorisé.');
    redirect(route('dashboard'));
}

try {
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_data) throw new Exception("Utilisateur non trouvé.");
} catch (Exception $e) {
    flash('error', $e->getMessage());
    redirect(route('users_users'));
}

if ($tfa && empty($_SESSION['otp_setup_secret'])) {
    $_SESSION['otp_setup_secret'] = $tfa->createSecret();
}
$secret = $_SESSION['otp_setup_secret'] ?? '';

// --- Handle Verification Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tfa) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        flash('error', 'Erreur de sécurité. Veuillez réessayer.', 'now');
    } else {
        $code = sanitize($_POST['code']);
        $secret_from_session = $_SESSION['otp_setup_secret'];

        if ($secret_from_session && $tfa->verifyCode($secret_from_session, $code)) {
            // !! IMPORTANT: You MUST encrypt this secret before saving !!
            $encrypted_secret = $secret_from_session;

            $stmt = $db->prepare("UPDATE users SET otp_secret = ?, is_app_otp_enabled = 1 WHERE id = ?");
            $stmt->execute([$encrypted_secret, $user_id]);

            unset($_SESSION['otp_setup_secret']);
            flash('success', 'Authentification par application activée avec succès !');
            redirect(route('users_edit', ['id' => $user_id]));
        } else {
            flash('error', 'Code de vérification invalide. Veuillez réessayer.', 'now');
        }
    }
}

$pageTitle = "Configurer l'Authentification par Application";
include __DIR__ . '../../../../includes/header.php';
?>
<div class="container">
    <h1 class="mb-4">Configurer l'Authentification par Application</h1>
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$tfa): ?>
                <?php display_flash_messages(); ?>
            <?php else: ?>
                <p>Suivez ces étapes :</p>
                <ol>
                    <li>Installez une application d'authentification sur votre mobile.</li>
                    <li>Scannez le QR Code ci-dessous.</li>
                    <li>Entrez le code à 6 chiffres généré pour vérifier la configuration.</li>
                </ol>
                <hr>
                <div class="text-center my-4">
                    <img src="<?= $tfa->getQRCodeImageAsDataUri($user_data['email'], $secret) ?>" alt="QR Code" class="img-thumbnail">
                    <p class="mt-3">Ou entrez cette clé manuellement :</p>
                    <p><strong><code class="fs-5 p-2 bg-light rounded user-select-all"><?= htmlspecialchars($secret) ?></code></strong></p>
                </div>
                <hr>
                <h4 class="text-center">Vérifier le code</h4>
                <?php display_flash_messages(); ?>
                <form method="POST" action="<?= route('users_setup_otp_app', ['id' => $user_id]) ?>" class="row justify-content-center" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="col-md-4">
                        <label for="code" class="form-label">Code de vérification</label>
                        <input type="text" name="code" id="code" class="form-control form-control-lg text-center" required maxlength="6" inputmode="numeric" pattern="\d{6}" autocomplete="one-time-code">
                    </div>
                    <div class="col-12 text-center mt-3">
                        <button type="submit" class="btn btn-primary">Activer</button>
                        <a href="<?= route('users_edit', ['id' => $user_id]) ?>" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '../../../../includes/footer.php'; ?>