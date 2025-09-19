<?php
/**
 * Handles the second factor of authentication (OTP via Email).
 */
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

if (!isset($_SESSION['otp_user_id']) && !isset($_SESSION['otp_app_user_id'])) {
    redirect(Proute('login'));
}

if (isset($_GET['action']) && $_GET['action'] === 'request_email' && isset($_SESSION['otp_app_user_id'])) {
    $_SESSION['otp_user_id'] = $_SESSION['otp_app_user_id'];
    unset($_SESSION['otp_app_user_id']);
    $_GET['action'] = 'resend';
}
$otp_user_id = $_SESSION['otp_user_id'];

if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $now = time();
    $cooldown = 180; // Cooldown is 180 seconds
    $last_sent = $_SESSION['otp_last_sent'] ?? 0;

    if (($now - $last_sent) < $cooldown) {
        flash('error', 'Veuillez attendre avant de demander un nouveau code.');
    } else {
        try {
            $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$otp_user_id]);
            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $otp_code = random_int(100000, 999999);
                if (send_otp_email($user['email'], (string)$otp_code)) {
                    $otp_hashed = password_hash((string)$otp_code, PASSWORD_DEFAULT);
                    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")->execute([$otp_hashed, $otp_expires_at, $otp_user_id]);
                    $_SESSION['otp_last_sent'] = $now;
                    flash('success', 'Un nouveau code OTP a été envoyé à votre adresse e-mail.');
                } else {
                    flash('error', "Impossible d'envoyer le nouveau code.");
                }
            }
        } catch (Exception $e) {
            error_log("OTP Resend Exception: " . $e->getMessage());
            flash('error', 'Une erreur est survenue lors du renvoi.');
        }
    }
    redirect(Proute('login_email_otp'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        flash('error', 'Requête invalide. Veuillez réessayer.', 'now');
    } else {
        $otp_code = preg_replace('/[^0-9]/', '', $_POST['otp_code']);
        if (strlen($otp_code) !== 6) {
            flash('error', 'Le code OTP doit comporter 6 chiffres.', 'now');
        } else {
            try {
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$otp_user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !$user['otp_code'] || strtotime($user['otp_expires_at']) < time()) {
                    flash('error', 'Code OTP invalide ou expiré. Veuillez réessayer de vous connecter.', 'now');
                    unset($_SESSION['otp_user_id']);
                } elseif (password_verify($otp_code, $user['otp_code'])) {
                    // --- SUCCESS ---
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    if (isset($_SESSION['request_remember_me']) && $_SESSION['request_remember_me']) {
                        create_remember_me_token($db, $user['id']);
                    }
                    
                    unset($_SESSION['otp_user_id'], $_SESSION['otp_last_sent'], $_SESSION['request_remember_me']);
                    $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    
                    redirect(APP_LINK . '/admin/');
                } else {
                    flash('error', 'Code OTP incorrect.', 'now');
                }
            } catch (PDOException $e) {
                error_log("OTP Email Verify PDOException: " . $e->getMessage());
                flash('error', "Une erreur de base de données est survenue.", 'now');
            }
        }
    }
}

$cooldown_seconds = 180; // Cooldown for JavaScript timer is 180 seconds
$time_since_last_send = time() - ($_SESSION['otp_last_sent'] ?? 0);
$seconds_left = $cooldown_seconds - $time_since_last_send;
$is_cooldown_active = $seconds_left > 0;

$pageTitle = "Vérification par Email";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Vérification par Email</h2>
                <p class="text-center text-muted">Un code a été envoyé à votre adresse email. Veuillez le saisir ci-dessous.</p>
                
                <form method="post" action="<?= Proute('login_email_otp') ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mb-3">
                        <label for="otp_code" class="form-label">Code OTP</label>
                        <input type="text" class="form-control" id="otp_code" name="otp_code" required maxlength="6" pattern="\d{6}" inputmode="numeric" autofocus>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Vérifier et se connecter</button>
                    </div>
                </form>

                <div class="mt-3 text-center">
                    <span id="resend-text">Vous n'avez pas reçu de code ?</span>
                    <a href="<?= Proute('login_email_otp', ['action' => 'resend']) ?>" id="resend-link" class="<?= $is_cooldown_active ? 'd-none' : '' ?>">Renvoyer</a>
                    <span id="cooldown-timer" class="text-muted <?= !$is_cooldown_active ? 'd-none' : '' ?>">
                        Vous pouvez renvoyer dans <span id="timer-countdown"><?= floor($seconds_left / 60) . ':' . str_pad($seconds_left % 60, 2, '0', STR_PAD_LEFT) ?></span>
                    </span>
                </div>

                <div class="mt-2 text-center">
                    <a href="<?= Proute('login') ?>">Retour à la connexion</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resendLink = document.getElementById('resend-link');
    const cooldownTimer = document.getElementById('cooldown-timer');
    const timerCountdown = document.getElementById('timer-countdown');
    
    let secondsLeft = <?= $is_cooldown_active ? $seconds_left : 0 ?>;

    if (secondsLeft > 0) {
        const interval = setInterval(function() {
            secondsLeft--;
            const minutes = Math.floor(secondsLeft / 60);
            const seconds = secondsLeft % 60;
            timerCountdown.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            if (secondsLeft <= 0) {
                clearInterval(interval);
                cooldownTimer.classList.add('d-none');
                resendLink.classList.remove('d-none');
            }
        }, 1000);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>