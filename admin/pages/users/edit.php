<?php
/**
 * Page for editing user details, including 2FA settings and account unlocking.
 * Accessible only by administrators.
 */

// This constant must be defined in your main router (e.g., index.php)
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// --- Authorization & Core Setup ---
if (!isAdmin()) {
    flash('error', 'Accès non autorisé.');
    redirect(route('dashboard'));
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Initial Validation: Ensure a valid user ID is provided ---
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    flash('error', "ID utilisateur invalide.");
    redirect(route('users_users'));
}

// --- Data Fetching: Get user and available employees ---
try {
    // Fetch all necessary user details, including lock status
    $stmt = $db->prepare("SELECT u.*, e.nin AS employee_nin
                          FROM users u
                          LEFT JOIN employees e ON u.id = e.user_id
                          WHERE u.id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        flash('error', "Utilisateur non trouvé.");
        redirect(route('users_users'));
    }

    // Fetch employees available for linking
    $stmt = $db->prepare("SELECT nin, first_name, last_name FROM employees WHERE user_id IS NULL OR user_id = :id ORDER BY last_name, first_name");
    $stmt->execute([':id' => $userId]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("User edit page DB error: " . $e->getMessage());
    flash('error', 'Une erreur est survenue lors de la récupération des données.', 'now');
    $user = []; // Prevent page from rendering with partial data
    $employees = [];
}

// --- Form Submission Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($user)) {
    try {
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Erreur de sécurité (CSRF). Veuillez soumettre à nouveau le formulaire.");
        }

        // --- Handle Account Unlock Action ---
        if (isset($_POST['action']) && $_POST['action'] === 'unlock') {
            $stmt = $db->prepare("UPDATE users SET account_locked_until = NULL, failed_attempts = 0 WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            flash('success', "Le compte a été déverrouillé avec succès.");
            redirect(route('users_edit', ['id' => $userId]));
        }

        // --- Sanitize and Validate Input for general update ---
        $input = [
            'username' => sanitize($_POST['username']),
            'email' => filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL),
            'role' => in_array($_POST['role'], ['admin', 'hr', 'employee']) ? $_POST['role'] : 'employee',
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_email_otp_enabled' => isset($_POST['is_email_otp_enabled']) ? 1 : 0,
            'is_app_otp_enabled' => isset($_POST['is_app_otp_enabled']) ? 1 : 0,
            'employee_nin' => sanitize($_POST['employee_nin'] ?? null) ?: null,
            'password' => $_POST['password'] ?? null,
            'confirm_password' => $_POST['confirm_password'] ?? null,
        ];

        if (empty($input['username']) || empty($input['email'])) {
            throw new Exception("Le nom d'utilisateur et l'email sont obligatoires.");
        }
        $stmt = $db->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
        $stmt->execute([':username' => $input['username'], ':email' => $input['email'], ':id' => $userId]);
        if ($stmt->fetch()) {
            throw new Exception("Un utilisateur avec ce nom ou cet e-mail existe déjà.");
        }

        // --- Build Dynamic SQL Query for User Update ---
        $sqlParts = [
            'username = :username',
            'email = :email',
            'role = :role',
            'is_active = :is_active',
            'is_email_otp_enabled = :is_email_otp_enabled',
            'is_app_otp_enabled = :is_app_otp_enabled'
        ];
        $params = [
            ':username' => $input['username'],
            ':email' => $input['email'],
            ':role' => $input['role'],
            ':is_active' => $input['is_active'],
            ':is_email_otp_enabled' => $input['is_email_otp_enabled'],
            ':is_app_otp_enabled' => $input['is_app_otp_enabled'],
            ':id' => $userId
        ];

        // Conditionally add password to the update
        if (!empty($input['password'])) {
            if ($input['password'] !== $input['confirm_password']) throw new Exception("Les nouveaux mots de passe ne correspondent pas.");
            if (strlen($input['password']) < 8) throw new Exception("Le mot de passe doit contenir au moins 8 caractères.");
            $sqlParts[] = 'password = :password';
            $params[':password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        $db->prepare("UPDATE users SET " . implode(', ', $sqlParts) . " WHERE id = :id")->execute($params);

        // Handle Employee Association
        if ($input['employee_nin'] !== $user['employee_nin']) {
            $db->beginTransaction();
            if ($user['employee_nin']) $db->prepare("UPDATE employees SET user_id = NULL WHERE nin = ?")->execute([$user['employee_nin']]);
            if ($input['employee_nin']) $db->prepare("UPDATE employees SET user_id = ? WHERE nin = ?")->execute([$userId, $input['employee_nin']]);
            $db->commit();
        }

        flash('success', "Utilisateur mis à jour avec succès.");
        redirect(route('users_users'));

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', $e->getMessage(), 'now');
        $user = array_merge($user, $_POST); // Keep submitted values in the form
    }
}

$pageTitle = "Modifier l'Utilisateur";
include __DIR__ . '../../../../includes/header.php';

// Check if the account is currently locked
$isLocked = !empty($user['account_locked_until']) && (new DateTime() < new DateTime($user['account_locked_until']));
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Modifier : <?= htmlspecialchars($user['username'] ?? '') ?></h2>

                <?php display_flash_messages(); ?>

                <?php if ($isLocked): ?>
                    <div class="alert alert-warning" role="alert">
                        <h4 class="alert-heading">Compte Verrouillé !</h4>
                        <p>
                            Ce compte est actuellement verrouillé. Le verrouillage expirera le
                            <strong><?= (new DateTime($user['account_locked_until']))->format('d/m/Y à H:i') ?></strong>.
                        </p>
                        <hr>
                        <form method="post" action="<?= route('users_edit', ['id' => $userId]) ?>" class="mb-0">
                           <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                           <button type="submit" name="action" value="unlock" class="btn btn-warning">
                                Déverrouiller le compte
                           </button>
                        </form>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= route('users_edit', ['id' => $userId]) ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur*</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email*</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle*</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                            <option value="hr" <?= ($user['role'] ?? '') === 'hr' ? 'selected' : '' ?>>RH</option>
                            <option value="employee" <?= ($user['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employé</option>
                        </select>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" <?= !empty($user['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Compte actif</label>
                    </div>

                    <hr>
                    <h5 class="mb-3">Sécurité 2FA</h5>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_email_otp_enabled" name="is_email_otp_enabled" value="1" <?= !empty($user['is_email_otp_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_email_otp_enabled">Activer l'authentification par Email</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_app_otp_enabled" name="is_app_otp_enabled" value="1" <?= !empty($user['is_app_otp_enabled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_app_otp_enabled">Activer l'authentification par Application</label>
                        <a href="<?= route('users_setup_otp_app', ['id' => $user['id']]) ?>" class="ms-3" title="Configurer l'application pour cet utilisateur">
                           <i class="bi bi-gear"></i> Configurer
                        </a>
                    </div>
                    
                    <hr>
                    <h5 class="mt-4">Modifier le mot de passe <small class="text-muted">(optionnel)</small></h5>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                    </div>

                    <hr>
                    <h5 class="mt-4">Association à un employé</h5>
                    <div class="mb-3">
                        <label for="employee_nin" class="form-label">Associer à un employé</label>
                        <select name="employee_nin" id="employee_nin" class="form-select">
                            <option value="">-- Non associé --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= ($user['employee_nin'] ?? '') === $emp['nin'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="action" value="update" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="<?= route('users_users') ?>" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>