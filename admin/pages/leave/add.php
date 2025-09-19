<?php
/**
 * Page: Add Leave Request
 *
 * Allows HR to add a leave request for an employee with validation
 * and automatic balance deduction.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// =========================================================================
// == HELPERS & INITIALIZATION
// =========================================================================

// Helper: Get recup balance for an employee (only not_taked)
function getRecupBalance($db, $nin) {
    $stmt = $db->prepare("SELECT IFNULL(SUM(nb_jours),0) FROM employee_recup_days WHERE employee_nin = ? AND status = 'not_taked'");
    $stmt->execute([$nin]);
    return floatval($stmt->fetchColumn());
}

// Fetch employees and their leave balances
$employees = $db->query("SELECT nin, first_name, last_name, annual_leave_balance, remaining_leave_balance FROM employees WHERE status='active' ORDER BY last_name, first_name")->fetchAll();

// Define leave types for the form dropdown and logic
$leave_types = [
    'annuel'                => ['label' => 'Annuel', 'has_sold' => true],
    'reliquat'              => ['label' => 'Reliquat', 'has_sold' => true],
    'recup'                 => ['label' => 'Récupération', 'has_sold' => true],
    'special_mariage'       => ['label' => 'Spécial - Mariage (3 jours)', 'has_sold' => false],
    'special_naissance'     => ['label' => 'Spécial - Naissance (3 jours)', 'has_sold' => false],
    'special_deces'         => ['label' => 'Spécial - Décès (3 jours)', 'has_sold' => false],
    'special_mariage_enf'   => ['label' => 'Spécial - Mariage Enfant (1 jour)', 'has_sold' => false],
    'special_circoncision'  => ['label' => 'Spécial - Circoncision (1 jour)', 'has_sold' => false],
    'anticipe'              => ['label' => 'Anticipé', 'has_sold' => true],
    'unpaid'                => ['label' => 'Sans Solde', 'has_sold' => false],
];

// Check for success flash session to trigger modal
$showLeaveSuccessModal = false;
$newLeaveId = null;
if (!empty($_SESSION['leave_success'])) {
    $showLeaveSuccessModal = true;
    $newLeaveId = $_SESSION['new_leave_id'] ?? null;
    unset($_SESSION['leave_success'], $_SESSION['new_leave_id']);
}

// =========================================================================
// == FORM SUBMISSION HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize all inputs
    $nin = sanitize($_POST['employee_nin']);
    $startDate = sanitize($_POST['start_date']);
    $endDate = sanitize($_POST['end_date']);
    $leaveType = sanitize($_POST['leave_type']);
    $reason = sanitize($_POST['reason']);

    // Calculate duration
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $days = $start->diff($end)->days + 1;

    // Fetch employee balances for validation
    $stmt = $db->prepare("SELECT annual_leave_balance, remaining_leave_balance FROM employees WHERE nin = ?");
    $stmt->execute([$nin]);
    $employeeBalances = $stmt->fetch();

    $recupBalance = getRecupBalance($db, $nin);
    $maxReliquat = min(30, $employeeBalances['remaining_leave_balance']);
    $annualBalance = $employeeBalances['annual_leave_balance'];
    $totalSold = $annualBalance + $maxReliquat + $recupBalance;

    // --- VALIDATION LOGIC ---
    $error = null;
    $useAnnuelDays = 0;
    $useReliquatDays = 0;
    $useRecupDays = 0;
    $useAnticipeDays = 0;
    $useUnpaidDays = 0;
    $leaveYear = date('Y');
    $remainingDays = $days;

    // 1. Overlap check
    $overlapStmt = $db->prepare("
        SELECT COUNT(*) FROM leave_requests 
        WHERE employee_nin = ? AND status IN ('pending', 'approved', 'prise', 'paused')
          AND (start_date <= ? AND end_date >= ?)
    ");
    $overlapStmt->execute([$nin, $endDate, $startDate]);
    if ($overlapStmt->fetchColumn() > 0) {
        $error = "L'employé a déjà un congé qui chevauche la période sélectionnée.";
    }

    // 2. Balance and type specific checks
    if (!$error) {
        if (in_array($leaveType, ['anticipe', 'unpaid']) && $totalSold > 0) {
            $error = "Un congé anticipé ou sans solde ne peut être demandé que si tous les autres soldes sont épuisés.";
        } else {
            switch($leaveType) {
                case 'annuel':
                    if ($totalSold < $days) {
                        $error = "Le solde total disponible ($totalSold jours) est insuffisant pour les $days jours demandés.";
                    } else {
                        $useReliquatDays = min($maxReliquat, $remainingDays);
                        $remainingDays -= $useReliquatDays;
                        if ($remainingDays > 0) {
                            $useRecupDays = min($recupBalance, $remainingDays);
                            $remainingDays -= $useRecupDays;
                        }
                        if ($remainingDays > 0) {
                            $useAnnuelDays = min($annualBalance, $remainingDays);
                        }
                    }
                    break;
                case 'reliquat':
                    if ($maxReliquat < $days) $error = "Le solde de reliquat disponible est de $maxReliquat jours.";
                    else $useReliquatDays = $days;
                    break;
                case 'recup':
                    if ($recupBalance < $days) $error = "Le solde de récupération disponible est de $recupBalance jours.";
                    else $useRecupDays = $days;
                    break;
                case 'anticipe':
                    if ($days > 30) $error = "Le congé anticipé ne peut pas dépasser 30 jours.";
                    else $useAnticipeDays = $days;
                    break;
                case 'unpaid':
                    if ($days > 365) $error = "Le congé sans solde ne peut pas dépasser 365 jours.";
                    else $useUnpaidDays = $days;
                    break;
                // Special leaves
                case 'special_mariage': case 'special_deces': case 'special_naissance':
                    if ($days > 3) $error = "Ce congé spécial est limité à 3 jours.";
                    break;
                case 'special_mariage_enf': case 'special_circoncision':
                    if ($days > 1) $error = "Ce congé spécial est limité à 1 jour.";
                    break;
                default:
                    $error = "Type de congé invalide.";
            }
        }
    }

    // --- DATABASE INSERTION ---
    if ($error) {
        flash('error', $error);
    } else {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO leave_requests
                (employee_nin, leave_type, start_date, end_date, days_requested, reason, status, approved_by, approval_date, created_at, leave_year, use_annuel, use_reliquat, use_recup, use_anticipe, use_unpaid)
                VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $nin, $leaveType, $startDate, $endDate, $days, $reason, $_SESSION['user_id'] ?? null,
                $leaveYear, $useAnnuelDays, $useReliquatDays, $useRecupDays, $useAnticipeDays, $useUnpaidDays
            ]);
            $new_leave_id = $db->lastInsertId();

            // Deduct from main balances
            $update_employee_stmt = $db->prepare(
                "UPDATE employees 
                 SET annual_leave_balance = annual_leave_balance - ?, 
                     remaining_leave_balance = remaining_leave_balance - ?
                 WHERE nin = ?"
            );
            $update_employee_stmt->execute([$useAnnuelDays + $useAnticipeDays, $useReliquatDays, $nin]);

            // Deduct from recup days
            if ($useRecupDays > 0) {
                $recup_stmt = $db->prepare("SELECT id, nb_jours FROM employee_recup_days WHERE employee_nin = ? AND status = 'not_taked' AND nb_jours > 0 ORDER BY year, month, id");
                $recup_stmt->execute([$nin]);
                $recupRows = $recup_stmt->fetchAll(PDO::FETCH_ASSOC);
                $toDeduct = $useRecupDays;
                foreach ($recupRows as $row) {
                    if ($toDeduct <= 0) break;
                    if ($row['nb_jours'] > $toDeduct) {
                        $upd = $db->prepare("UPDATE employee_recup_days SET nb_jours = nb_jours - ? WHERE id = ?");
                        $upd->execute([$toDeduct, $row['id']]);
                        $toDeduct = 0;
                    } else {
                        $upd = $db->prepare("UPDATE employee_recup_days SET nb_jours = 0, status = 'taked' WHERE id = ?");
                        $upd->execute([$row['id']]);
                        $toDeduct -= $row['nb_jours'];
                    }
                }
            }

            $db->commit();
            $_SESSION['leave_success'] = true;
            $_SESSION['new_leave_id'] = $new_leave_id;
            header("Location: " . route('leave_add'));
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            flash('error', "Erreur de base de données: " . $e->getMessage());
        }
    }
}

$pageTitle = "Ajouter un Congé";
include __DIR__. '../../../../includes/header.php';
?>

<div class="container my-4">
    <h1 class="h3 mb-4"><i class="bi bi-calendar-plus"></i> <?= htmlspecialchars($pageTitle) ?></h1>

    <form method="post" id="leaveForm">
        <?php csrf_input(); ?>
        <div class="row">
            <!-- Main Form Column -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Détails de la Demande</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="employee_nin" class="form-label">Employé*</label>
                                <select id="employee_nin" name="employee_nin" class="form-select" required onchange="updateLeaveBalance(this)">
                                    <option value="" selected disabled>-- Sélectionner un employé --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <?php $recupBalance = getRecupBalance($db, $emp['nin']); ?>
                                        <option value="<?= htmlspecialchars($emp['nin']) ?>"
                                            data-annual-balance="<?= htmlspecialchars($emp['annual_leave_balance']) ?>"
                                            data-remaining-balance="<?= htmlspecialchars($emp['remaining_leave_balance']) ?>"
                                            data-recup-balance="<?= htmlspecialchars($recupBalance) ?>">
                                            <?= htmlspecialchars($emp['last_name'] . ' ' . $emp['first_name']) ?> (<?= htmlspecialchars($emp['nin']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="leave_type" class="form-label">Type de Congé*</label>
                                <select name="leave_type" class="form-select" required id="leave_type">
                                    <?php foreach ($leave_types as $key => $type): ?>
                                        <option value="<?= $key ?>"><?= htmlspecialchars($type['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Date Début*</label>
                                <input type="date" name="start_date" class="form-control" required id="start_date">
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Date Fin*</label>
                                <input type="date" name="end_date" class="form-control" required id="end_date">
                            </div>
                            <div class="col-12">
                                <label for="reason" class="form-label">Motif*</label>
                                <textarea name="reason" id="reason" class="form-control" rows="4" required></textarea>
                            </div>
                        </div>
                        <hr class="my-4">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= route('leave_requests') ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Enregistrer le Congé
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balances Column -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-wallet2"></i> Soldes Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Les soldes de l'employé sélectionné s'afficheront ici.</p>
                        <ul class="list-group" id="balances-list" style="display: none;">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Annuel
                                <span id="annual_leave_balance" class="badge bg-primary rounded-pill">--</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Reliquat (max 30j)
                                <span id="remaining_leave_balance" class="badge bg-info rounded-pill">--</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Récupération
                                <span id="recup_balance" class="badge bg-secondary rounded-pill">--</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal: Congé enregistré avec succès -->
<div class="modal fade" id="leaveSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Succès</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        Congé enregistré avec succès. Voulez-vous imprimer l'attestation de congé ?
      </div>
      <div class="modal-footer">
        <?php if ($showLeaveSuccessModal && $newLeaveId): ?>
        <a href="<?= route('leave_Leave_certificate', ['leave_id' => htmlspecialchars($newLeaveId)]) ?>" target="_blank" class="btn btn-primary" id="printLeaveCertificateBtn">
          Oui, imprimer
        </a>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Non, fermer</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const employeeSelect = document.getElementById('employee_nin');
    const balancesList = document.getElementById('balances-list');
    const balancesPlaceholder = balancesList.previousElementSibling;

    // Set default start date to today
    if (!startDateInput.value) {
        startDateInput.valueAsDate = new Date();
    }
    endDateInput.min = startDateInput.value;

    startDateInput.addEventListener('change', function() {
        if (!endDateInput.value || new Date(endDateInput.value) < new Date(this.value)) {
            endDateInput.value = this.value;
        }
        endDateInput.min = this.value;
    });

    window.updateLeaveBalance = function(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        if (!selectedOption.value) {
            balancesList.style.display = 'none';
            balancesPlaceholder.style.display = 'block';
            return;
        }
        
        balancesList.style.display = 'block';
        balancesPlaceholder.style.display = 'none';

        const annualBalance = parseFloat(selectedOption.getAttribute('data-annual-balance') || 0).toFixed(1);
        const remainingBalance = parseFloat(selectedOption.getAttribute('data-remaining-balance') || 0).toFixed(1);
        const recupBalance = parseFloat(selectedOption.getAttribute('data-recup-balance') || 0).toFixed(1);

        document.getElementById('annual_leave_balance').textContent = annualBalance + ' j';
        document.getElementById('remaining_leave_balance').textContent = remainingBalance + ' j';
        document.getElementById('recup_balance').textContent = recupBalance + ' j';
    };
});
</script>

<?php if ($showLeaveSuccessModal && $newLeaveId): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var leaveModal = new bootstrap.Modal(document.getElementById('leaveSuccessModal'));
        leaveModal.show();
        
        // Redirect after printing/closing modal to avoid resubmission on refresh
        document.getElementById('leaveSuccessModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = "<?= route('leave_view', ['id' => htmlspecialchars($newLeaveId)]) ?>";
        });
    });
</script>
<?php endif; ?>

<?php include __DIR__.'../../../../includes/footer.php'; ?>
