<?php
/**
 * Page for managing SMTP email settings.
 * Accessible only by administrators.
 */

// --- Security Headers and Core Includes ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

// --- Authorization & Core Setup ---
if (!isAdmin()) {
    // Redirect to login using the application's base URL, not a relative path
    redirect(APP_LINK . '/auth/login.php');
}

// Initialize flash messaging and CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Data Fetching: Get current SMTP settings ---
$smtp_defaults = [
    'method' => 'smtp', 'host' => '', 'port' => 587, 'username' => '',
    'password' => '', 'secure' => 'tls', 'from_email' => '', 'from_name' => ''
];

try {
    $row = $db->query("SELECT * FROM smtp_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $smtp = $row ? array_merge($smtp_defaults, $row) : $smtp_defaults;
} catch (PDOException $e) {
    error_log("SMTP settings fetch error: " . $e->getMessage());
    flash('error', 'Impossible de charger les paramètres SMTP.', 'now');
    $smtp = $smtp_defaults; // Use defaults if fetch fails
}


// --- Form Submission Logic for SAVING settings ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Erreur de sécurité (CSRF). Veuillez soumettre à nouveau.");
        }

        // Sanitize and Validate Input
        $method = in_array($_POST['method'], ['smtp', 'phpmail']) ? $_POST['method'] : 'smtp';
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = filter_input(INPUT_POST, 'smtp_port', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = $_POST['smtp_pass'] ?? '';
        $smtp_secure = in_array($_POST['smtp_secure'], ['tls', 'ssl']) ? $_POST['smtp_secure'] : 'tls';
        $smtp_from = filter_input(INPUT_POST, 'smtp_from', FILTER_VALIDATE_EMAIL);
        $smtp_fromname = sanitize($_POST['smtp_fromname'] ?? '');

        $final_password = $smtp_pass ?: ($smtp['password'] ?? '');

        if (!$smtp_from) {
            throw new Exception("L'email d'expédition est invalide ou manquant.");
        }
        if ($method === 'smtp' && (!$smtp_host || !$smtp_port || !$smtp_user || !$final_password)) {
            throw new Exception("Pour SMTP, tous les champs (serveur, port, utilisateur, mot de passe) sont obligatoires.");
        }

        // Database Transaction
        $db->beginTransaction();
        $db->exec("DELETE FROM smtp_settings");

        if ($method === 'smtp') {
            $stmt = $db->prepare("INSERT INTO smtp_settings (method, host, port, username, password, secure, from_email, from_name) VALUES (:method, :host, :port, :username, :password, :secure, :from_email, :from_name)");
            $stmt->execute([
                ':method' => 'smtp', ':host' => $smtp_host, ':port' => $smtp_port,
                ':username' => $smtp_user, ':password' => $final_password, ':secure' => $smtp_secure,
                ':from_email' => $smtp_from, ':from_name' => $smtp_fromname
            ]);
        } else { // phpmail
            $stmt = $db->prepare("INSERT INTO smtp_settings (method, from_email, from_name) VALUES (:method, :from_email, :from_name)");
            $stmt->execute([':method' => 'phpmail', ':from_email' => $smtp_from, ':from_name' => $smtp_fromname]);
        }
        $db->commit();
        
        flash('success', "Paramètres d'envoi d'email enregistrés avec succès.");
        redirect(route('settings_settings_smtp'));

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', $e->getMessage(), 'now');
        // Re-populate submitted data on error to avoid re-typing
        $smtp = array_merge($smtp, $_POST);
    }
}

$pageTitle = "Paramètres Email";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Paramètres Email (SMTP ou PHP mail())</h1>

    <?php display_flash_messages(); ?>

    <form method="post" action="<?= route('settings_settings_smtp') ?>" autocomplete="off" id="smtp-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
            <label for="method-select" class="form-label">Méthode d'envoi*</label>
            <select name="method" id="method-select" class="form-select" required onchange="toggleSmtpFields()">
                <option value="smtp" <?= ($smtp['method'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP (Recommandé)</option>
                <option value="phpmail" <?= ($smtp['method'] ?? '') === 'phpmail' ? 'selected' : '' ?>>PHP mail()</option>
            </select>
        </div>

        <div id="smtp-fields" style="display:<?= ($smtp['method'] ?? 'smtp') === 'smtp' ? 'block' : 'none' ?>">
            <div class="mb-3">
                <label for="smtp_host" class="form-label">Serveur SMTP*</label>
                <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($smtp['host'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="smtp_port" class="form-label">Port*</label>
                <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($smtp['port'] ?? '587') ?>">
            </div>
            <div class="mb-3">
                <label for="smtp_user" class="form-label">Utilisateur (email)*</label>
                <input type="text" class="form-control" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars($smtp['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="smtp_pass" class="form-label">Mot de passe*</label>
                <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" placeholder="Laisser vide pour ne pas changer">
            </div>
            <div class="mb-3">
                <label for="smtp_secure" class="form-label">Sécurité*</label>
                <select name="smtp_secure" id="smtp_secure" class="form-select">
                    <option value="tls" <?= ($smtp['secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($smtp['secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="smtp_from" class="form-label">Email d'expéditeur*</label>
            <input type="email" class="form-control" id="smtp_from" name="smtp_from" required value="<?= htmlspecialchars($smtp['from_email'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="smtp_fromname" class="form-label">Nom de l'expéditeur</label>
            <input type="text" class="form-control" id="smtp_fromname" name="smtp_fromname" value="<?= htmlspecialchars($smtp['from_name'] ?? '') ?>">
        </div>
        
        <div class="d-flex align-items-center">
            <button type="submit" name="save_settings" class="btn btn-primary">Enregistrer</button>
            <button type="button" id="test-smtp-btn" class="btn btn-secondary ms-2">
                <span id="test-btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <span id="test-btn-text">Tester la Connexion</span>
            </button>
        </div>
    </form>
    
    <div id="test-results" class="mt-3"></div>
</div>

<script>
function toggleSmtpFields() {
    var method = document.getElementById('method-select').value;
    var smtpFields = document.getElementById('smtp-fields');
    var testBtn = document.getElementById('test-smtp-btn');
    
    if (method === 'smtp') {
        smtpFields.style.display = 'block';
        testBtn.style.display = 'inline-block';
    } else {
        smtpFields.style.display = 'none';
        testBtn.style.display = 'none';
    }
}

document.getElementById('test-smtp-btn').addEventListener('click', async function() {
    const testBtn = this;
    const btnText = document.getElementById('test-btn-text');
    const spinner = document.getElementById('test-btn-spinner');
    const resultsDiv = document.getElementById('test-results');
    
    btnText.textContent = 'Envoi...';
    spinner.classList.remove('d-none');
    testBtn.disabled = true;
    resultsDiv.innerHTML = '';

    const form = document.getElementById('smtp-form');
    const formData = new FormData(form);

    try {
        const response = await fetch('<?= route('settings_ajax_test_smtp') ?>', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Erreur HTTP! Statut: ${response.status}`);
        }

        const result = await response.json();
        
        const alertClass = result.status === 'success' ? 'alert-success' : 'alert-danger';
        resultsDiv.innerHTML = `<div class="alert ${alertClass} mt-3">${result.message}</div>`;

    } catch (error) {
        console.error('Erreur Fetch:', error);
        resultsDiv.innerHTML = `<div class="alert alert-danger mt-3">Une erreur de communication est survenue: ${error.message}</div>`;
    } finally {
        btnText.textContent = 'Tester la Connexion';
        spinner.classList.add('d-none');
        testBtn.disabled = false;
    }
});

// Initialiser l'affichage au chargement de la page
toggleSmtpFields();
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>