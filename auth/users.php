<?php
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}


// Only admin can access
if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// --- CSRF Protection for all POST requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $_SESSION['error'] = "Erreur CSRF. Veuillez recharger la page et réessayer.";
        header("Location: users.php");
        exit();
    }
}

// --- Fetch users and employees ---
$users = $db->query("SELECT u.*, e.first_name, e.last_name
                        FROM users u
                        LEFT JOIN employees e ON u.id = e.user_id
                        ORDER BY u.role, u.username")->fetchAll();

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    try {
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role']);
        $employeeNIN = sanitize($_POST['employee_nin'] ?? null);

        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new Exception("Adresse email invalide.");
        }
        if (strlen($password) < 8) {
            throw new Exception("Le mot de passe doit contenir au moins 8 caractères");
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new Exception("Le mot de passe doit contenir lettres et chiffres.");
        }
        // User exists?
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Un utilisateur avec ce nom ou email existe déjà");
        }
        // Employé déjà associé ?
        if (!empty($employeeNIN)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE nin = ? AND user_id IS NOT NULL");
            $stmt->execute([$employeeNIN]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet employé est déjà associé à un compte utilisateur");
            }
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Créer l'utilisateur
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
        header("Location: users.php");
        exit();
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    try {
        $userIdToDelete = (int)$_POST['user_id_delete'];

        // Prevent deleting the admin who is logged in
        if ($userIdToDelete === $_SESSION['user_id'] && $_SESSION['role'] === 'admin') {
            throw new Exception("Vous ne pouvez pas supprimer votre propre compte administrateur.");
        }

        $db->beginTransaction();
        // Nullify user_id in employees table if associated
        $stmt = $db->prepare("UPDATE employees SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$userIdToDelete]);
        // Delete user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userIdToDelete]);
        $db->commit();

        $_SESSION['success'] = "Utilisateur supprimé avec succès";
        header("Location: users.php");
        exit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = "Erreur lors de la suppression de l'utilisateur: " . $e->getMessage();
        header("Location: users.php");
        exit();
    }
}

// --- CSRF Token for forms ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Gestion des Utilisateurs";
include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Gestion des Utilisateurs</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Ajouter un Utilisateur</h5>
                </div>
                <div class="card-body">
                    <form method="post" autocomplete="off" novalidate>
                       <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nom d'utilisateur*</label>
                            <input type="text" name="username" class="form-control" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email*</label>
                            <input type="email" name="email" class="form-control" required maxlength="254">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe*</label>
                            <input type="password" name="password" class="form-control" required minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d).{8,}$">
                            <small class="text-muted">Au moins 8 caractères, lettres et chiffres</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rôle*</label>
                            <select name="role" class="form-select" required>
                                <option value="admin">Administrateur</option>
                                <option value="hr">RH</option>
                                <option value="employee" selected>Employé</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="employee_nin_add" class="form-label">Associer à un employé (optionnel)</label>
                            <select name="employee_nin" id="employee_nin_add" class="form-select">
                                <option value="">-- Sélectionner un employé --</option>
                                <?php
                                $employeesWithoutAccount = $db->query("SELECT nin, first_name, last_name FROM employees WHERE user_id IS NULL ORDER BY last_name, first_name")->fetchAll();
                                foreach ($employeesWithoutAccount as $emp):
                                    ?>
                                    <option value="<?= htmlspecialchars($emp['nin']) ?>">
                                        <?= htmlspecialchars($emp['last_name']) ?> <?= htmlspecialchars($emp['first_name']) ?> (<?= htmlspecialchars($emp['nin']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Seuls les employés sans compte sont listés</small>
                        </div>
                        <button type="submit" name="add_user" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus"></i> Ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Liste des Utilisateurs</h5>
                </div>
                <div class="card-body">
                    <?php if (count($users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($user['username']) ?>
                                                <?php if (!empty($user['first_name'])): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $user['role'] === 'admin' ? 'danger' :
                                                    ($user['role'] === 'hr' ? 'primary' : 'secondary')
                                                ?>">
                                                    <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_user.php?id=<?= urlencode($user['id']) ?>" class="btn btn-sm btn-primary me-1">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                        data-bs-target="#deleteUserModal<?= $user['id'] ?>">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>

                                                <div class="modal fade" id="deleteUserModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="deleteUserModalLabel<?= $user['id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteUserModalLabel<?= $user['id'] ?>">Confirmer la suppression</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Êtes-vous sûr de vouloir supprimer l'utilisateur <strong><?= htmlspecialchars($user['username']) ?></strong> ?
                                                                Cette action est irréversible.
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <form method="post" class="d-inline">
                                                                  <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                                    <input type="hidden" name="user_id_delete" value="<?= htmlspecialchars($user['id']) ?>">
                                                                    <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Supprimer</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Aucun utilisateur trouvé.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>