<?php
// --- Security Headers: Set before any output ---
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotAdminOrHR();

if (!isset($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);

// Fetch employee info
$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé";
    header("Location: " . route('employees_list'));
    exit();
}

// Process correction form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['days_change'], $_POST['reason'])) {
    $daysChange = floatval($_POST['days_change']);
    $reason = trim($_POST['reason']);
    $performedBy = $_SESSION['username'] ?? 'admin';
    $currentYear = date('Y');
    $currentMonth = date('n');

    // Fetch the latest leave balance for tracking
    $stmt = $db->prepare("SELECT annual_leave_balance FROM employees WHERE nin = ?");
    $stmt->execute([$nin]);
    $currentBalance = $stmt->fetchColumn();

    $newBalance = $currentBalance + $daysChange;

    // Insert into leave_balance_history
    $stmt = $db->prepare("INSERT INTO leave_balance_history
        (employee_nin, leave_year, month, operation_type, days_added, previous_balance, new_balance, performed_by, operation_date, notes)
        VALUES (?, ?, ?, 'correction', ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([
        $nin, $currentYear, $currentMonth, $daysChange, $currentBalance, $newBalance, $performedBy, $reason
    ]);

    // Update employee balance
    $stmt = $db->prepare("UPDATE employees SET annual_leave_balance = ? WHERE nin = ?");
    $stmt->execute([$newBalance, $nin]);

    $_SESSION['success'] = "Solde corrigé avec succès.";
    header("Location: " . route('leave_adjust_leave', ['nin' => $nin]));
    exit();
}

// Fetch leave balance history for this employee
$stmt = $db->prepare("SELECT * FROM leave_balance_history WHERE employee_nin = ? ORDER BY operation_date DESC");
$stmt->execute([$nin]);
$history = $stmt->fetchAll();
$pageTitle = "Correction de solde de congés pour " . $employee['first_name'] . " " . $employee['last_name'];
include __DIR__. '../../../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-7">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        Correction du solde de congés
                        <small class="d-block text-light fs-6"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> (NIN: <?= $employee['nin'] ?>)</small>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="mb-4">
                      <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                    <div class="mb-3">
                            <label for="days_change" class="form-label">Jours à corriger <span class="text-danger">*</span></label>
                            <input type="number" name="days_change" id="days_change" class="form-control" step="0.5" min="-30" max="30" required>
                            <div class="form-text">Valeur positive pour ajouter, négative pour retirer.</div>
                        </div>
                        <div class="mb-3">
                            <label for="reason" class="form-label">Raison de la correction <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Solde actuel</label>
                            <input type="text" class="form-control" value="<?= number_format($employee['annual_leave_balance'], 1) ?> jour(s)" readonly>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Appliquer la correction</button>
                        <a href="<?= route('employees_view', ['nin' => $nin]) ?>" class="btn btn-secondary ms-2">Retour au profil</a>
                        <a href="<?= route('settings_leave_history') ?>" class="btn btn-info ms-2"><i class="bi bi-clock-history"></i> Historique global</a>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historique des corrections</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($history): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Jours</th>
                                        <th>Ancien solde</th>
                                        <th>Nouveau solde</th>
                                        <th>Raison</th>
                                        <th>Par</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $rec): ?>
                                        <tr>
                                            <td><?= formatDate($rec['operation_date']) ?></td>
                                            <td>
                                                <span class="badge <?= $rec['operation_type'] === 'correction' ? 'bg-warning' : 'bg-secondary' ?>">
                                                    <?= ucfirst($rec['operation_type']) ?>
                                                </span>
                                            </td>
                                            <td class="<?= $rec['days_added'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $rec['days_added'] > 0 ? '+' : '' ?><?= $rec['days_added'] ?>
                                            </td>
                                            <td><?= $rec['previous_balance'] ?></td>
                                            <td><?= $rec['new_balance'] ?></td>
                                            <td><?= htmlspecialchars($rec['notes']) ?></td>
                                            <td><?= htmlspecialchars($rec['performed_by']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info m-3">Aucune correction enregistrée.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <b>Note :</b> Toute correction est tracée avec la raison, la date et la personne ayant effectué la modification.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__.'../..../../includes/footer.php'; ?>