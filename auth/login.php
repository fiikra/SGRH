<?php
/**
 * Logs a login attempt to the user_logins table.
 *
 * @param PDO $db The database connection object.
 * @param int|null $userId The ID of the user attempting to log in.
 * @param bool $success True if the login was successful, false otherwise.
 */
function log_login_attempt($db, $userId, $success) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $success_flag = $success ? 1 : 0;
    try {
        $stmt = $db->prepare(
            "INSERT INTO user_logins (user_id, ip_address, user_agent, success) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $ip_address, $user_agent, $success_flag]);
    } catch (PDOException $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

// --- Attempt to log in via cookie if not already logged in ---
if (!isLoggedIn() && isset($_COOKIE['remember_me'])) {
    login_with_remember_me_cookie($db);
}

// Redirect if now logged in (either by session or cookie)
if (isLoggedIn()) {
    if (!isAdminOrHR()) {
        redirectIfNotAdminOrHR();
    } else {
        redirectIfAdminorHR();
    }
}

// --- Login Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Erreur de sécurité (CSRF). Veuillez rafraîchir la page.");
        }

        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $remember_me_checked = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';

        if (empty($username) || empty($password)) {
            throw new Exception("Le nom d'utilisateur et le mot de passe sont requis.");
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && empty($user['account_locked_until']) && password_verify($password, $user['password'])) {
            
            if ($remember_me_checked) {
                $_SESSION['request_remember_me'] = true;
            } else {
                unset($_SESSION['request_remember_me']); 
            }
            
            log_login_attempt($db, $user['id'], true);
            
            if (!empty($user['is_app_otp_enabled'])) {
                $_SESSION['otp_app_user_id'] = $user['id'];
                redirect(Proute('login_app_otp'));
            } elseif (!empty($user['is_email_otp_enabled'])) {
                $_SESSION['otp_user_id'] = $user['id'];
                $otp_code = random_int(100000, 999999);
                if (send_otp_email($user['email'], (string)$otp_code)) {
                    $otp_hashed = password_hash((string)$otp_code, PASSWORD_DEFAULT);
                    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?")->execute([$otp_hashed, $otp_expires_at, $user['id']]);
                    redirect(Proute('login_email_otp'));
                } else {
                    throw new Exception("Impossible d'envoyer l'email de vérification. Contactez un administrateur.");
                }
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $db->prepare("UPDATE users SET failed_attempts = 0, account_locked_until = NULL, last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                
                if (isset($_SESSION['request_remember_me']) && $_SESSION['request_remember_me']) {
                    create_remember_me_token($db, $user['id']);
                    unset($_SESSION['request_remember_me']);
                }
                
                redirect(APP_LINK . '/admin/');
            }
        } else {
            if ($user) {
                log_login_attempt($db, $user['id'], false);
                if (!empty($user['account_locked_until'])) {
                    throw new Exception("Compte verrouillé. Veuillez réessayer plus tard.");
                }
                $failedAttempts = ($user['failed_attempts'] ?? 0) + 1;
                $lockUntil = ($failedAttempts >= 5) ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                $db->prepare("UPDATE users SET failed_attempts = ?, account_locked_until = ? WHERE id = ?")->execute([$failedAttempts, $lockUntil, $user['id']]);
            } else {
                log_login_attempt($db, null, false);
            }
            throw new Exception("Nom d'utilisateur ou mot de passe incorrect.");
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage(), 'now');
    }
}

$pageTitle = "Connexion";
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Connexion</h2>
                
                <form method="post" action="<?php Proute('login'); ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" name="username" required maxlength="64" autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required maxlength="128" autocomplete="current-password">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1">
                        <label class="form-check-label" for="remember_me">Se souvenir de moi</label>
                    </div>
                    <?php display_flash_messages(); ?>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Se Connecter</button>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <a href="<?= Proute('reset_password') ?>">Mot de passe oublié ?</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>