<?php
/**
 * Page for listing and managing all users in the system.
 * Includes search, filtering, pagination, and 2FA status display.
 * Accessible only by HR roles and above.
 */

// This constant must be defined in your main index.php router
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// --- Authorization & Core Setup ---
redirectIfNotHR();

// --- Pagination & Filtering Setup ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$roleFilter = isset($_GET['role']) && in_array($_GET['role'], ['admin', 'hr', 'employee']) ? sanitize($_GET['role']) : '';

// --- Build Query Conditions Dynamically ---
$conditions = [];
$params = [];

if (!empty($search)) {
    // Search across multiple relevant fields
    $conditions[] = "(u.username LIKE :search OR u.email LIKE :search OR e.first_name LIKE :search OR e.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($roleFilter)) {
    $conditions[] = "u.role = :role";
    $params[':role'] = $roleFilter;
}

$whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : '';

// --- Database Queries ---
// First, get the total count of users matching the filters for pagination
$totalStmt = $db->prepare("SELECT COUNT(u.id) FROM users u LEFT JOIN employees e ON u.id = e.user_id $whereClause");
$totalStmt->execute($params);
$totalUsers = $totalStmt->fetchColumn();

// --- MODIFIED: Added 'account_locked_until' to the SELECT statement ---
$query = "SELECT u.id, u.username, u.email, u.role, u.is_active, u.is_email_otp_enabled, u.is_app_otp_enabled, u.last_login, u.account_locked_until, e.first_name, e.last_name, e.nin 
          FROM users u
          LEFT JOIN employees e ON u.id = e.user_id
          $whereClause
          ORDER BY u.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

// Bind filter parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
// PDO requires binding LIMIT/OFFSET values as integers separately
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


$pageTitle = "Gestion des Utilisateurs";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestion des Utilisateurs</h1>
        <a href="<?= route('users_register') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nouvel Utilisateur
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form action="<?= route('users_users') ?>" method="get" class="row g-3">
                <input type="hidden" name="route" value="users_users">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher par nom, email..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="">Tous les rôles</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="hr" <?= $roleFilter === 'hr' ? 'selected' : '' ?>>RH</option>
                        <option value="employee" <?= $roleFilter === 'employee' ? 'selected' : '' ?>>Employé</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
           
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Utilisateur</th>
                            <th>Rôle</th>
                            <th>Employé Associé</th>
                            <th>Statut</th>
                            <th>2FA</th>
                            <th>Dernière Connexion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="text-center">Aucun utilisateur trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-2" title="<?= htmlspecialchars($user['username']) ?>"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                                            <div>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $roleClass = ['admin' => 'danger', 'hr' => 'warning', 'employee' => 'primary'][$user['role']] ?? 'secondary'; ?>
                                        <span class="badge bg-<?= $roleClass ?>"><?= ucfirst($user['role']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['nin'])): ?>
                                            <a href="<?= route('employees_view', ['nin' => $user['nin']]) ?>"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></a>
                                        <?php else: ?><span class="text-muted">Aucun</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $isLocked = !empty($user['account_locked_until']) && (new DateTime() < new DateTime($user['account_locked_until']));

                                        if ($isLocked) {
                                            $lockDate = (new DateTime($user['account_locked_until']))->format('d/m/Y H:i');
                                            echo '<span class="badge bg-danger" title="Verrouillé jusqu\'au ' . $lockDate . '"><i class="bi bi-lock-fill"></i> Verrouillé</span>';
                                        } elseif ($user['is_active']) {
                                            echo '<span class="badge bg-success">Actif</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Inactif</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['is_app_otp_enabled'])): ?>
                                            <span class="badge bg-success" title="Activé par Application"><i class="bi bi-shield-lock-fill"></i> App</span>
                                        <?php elseif (!empty($user['is_email_otp_enabled'])): ?>
                                            <span class="badge bg-info" title="Activé par Email"><i class="bi bi-envelope-at-fill"></i> Email</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Désactivé"><i class="bi bi-shield-slash-fill"></i> Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $user['last_login'] ? formatDate($user['last_login'], 'd/m/Y H:i') : 'Jamais' ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= route('users_view', ['id' => $user['id']]) ?>" class="btn btn-outline-primary" title="Voir"><i class="bi bi-eye"></i></a>
                                            <a href="<?= route('users_edit', ['id' => $user['id']]) ?>" class="btn btn-outline-secondary" title="Modifier"><i class="bi bi-pencil"></i></a>
                                            <button class="btn btn-outline-danger" title="Supprimer" 
                                                    onclick="confirmDelete('<?= route('users_delete', ['id' => $user['id']]) ?>', '<?= htmlspecialchars(addslashes($user['username'])) ?>')">
                                                    <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPages = ceil($totalUsers / $perPage);
                    $queryParams = ['search' => $search, 'role' => $roleFilter];
                    ?>
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= route('users_users', array_merge($queryParams, ['page' => $page - 1])) ?>">&laquo;</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= route('users_users', array_merge($queryParams, ['page' => $i])) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="<?= route('users_users', array_merge($queryParams, ['page' => $page + 1])) ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script>
function confirmDelete(deleteUrl, username) {
    if (confirm(`Voulez-vous vraiment supprimer l'utilisateur "${username}" ?\n\nCette action est irréversible.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = deleteUrl;
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?= $_SESSION['csrf_token'] ?>';
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<style>
.avatar { width: 32px; height: 32px; background-color: #0d6efd; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
</style>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>