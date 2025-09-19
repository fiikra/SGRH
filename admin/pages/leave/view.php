<?php
/**
 * Page: View Leave Request Details
 *
 * This page displays all the details for a single leave request,
 * including employee balances, leave usage breakdown, and pause history.
 * It also handles the approval/rejection of pending requests with full balance deduction logic.
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
// == APPROVAL/REJECTION HANDLER
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    redirectIfNotHR();

    $leave_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
    $approver_id = $_SESSION['user_id'] ?? null;

    if (!$leave_id || !in_array($action, ['approve', 'reject'])) {
        flash('error', 'Action invalide ou ID de congé manquant.');
        header("Location: " . route('leave_requests'));
        exit();
    }

    try {
        // Fetch the leave request to get all necessary details for the update
        $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = :leave_id");
        $stmt->execute([':leave_id' => $leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            flash('error', 'Demande de congé non trouvée.');
            header("Location: " . route('leave_requests'));
            exit();
        }

        $db->beginTransaction();

        if ($action === 'approve') {
            // 1. Update the leave request status
            $update_leave_stmt = $db->prepare(
                "UPDATE leave_requests 
                 SET status = 'approved', approved_by = :approver_id, approval_date = NOW(), comment = :comment 
                 WHERE id = :leave_id"
            );
            $update_leave_stmt->execute([
                ':approver_id' => $approver_id,
                ':comment' => $comment,
                ':leave_id' => $leave_id
            ]);

            // 2. Update the employee's main leave balances (annuel, reliquat, anticipe)
            $update_employee_stmt = $db->prepare(
                "UPDATE employees 
                 SET annual_leave_balance = annual_leave_balance - :use_annuel, 
                     remaining_leave_balance = remaining_leave_balance - :use_reliquat
                 WHERE nin = :employee_nin"
            );
            $update_employee_stmt->execute([
                ':use_annuel' => $leave['use_annuel'] + $leave['use_anticipe'], // Anticipe is also deducted from the main balance
                ':use_reliquat' => $leave['use_reliquat'],
                ':employee_nin' => $leave['employee_nin']
            ]);

            // 3. Handle deduction for 'recup' days by updating the status of recup records
            if ($leave['use_recup'] > 0) {
                $recup_stmt = $db->prepare("SELECT id, nb_jours FROM employee_recup_days WHERE employee_nin = ? AND status = 'not_taked' AND nb_jours > 0 ORDER BY year, month, id");
                $recup_stmt->execute([$leave['employee_nin']]);
                $recupRows = $recup_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $toDeduct = $leave['use_recup'];
                foreach ($recupRows as $row) {
                    if ($toDeduct <= 0) break;

                    if ($row['nb_jours'] > $toDeduct) {
                        // Partially deduct from this recup record
                        $upd = $db->prepare("UPDATE employee_recup_days SET nb_jours = nb_jours - ? WHERE id = ?");
                        $upd->execute([$toDeduct, $row['id']]);
                        $toDeduct = 0;
                    } else {
                        // Fully use this recup record and mark it as 'taked'
                        $upd = $db->prepare("UPDATE employee_recup_days SET nb_jours = 0, status = 'taked' WHERE id = ?");
                        $upd->execute([$row['id']]);
                        $toDeduct -= $row['nb_jours'];
                    }
                }
            }
            
            flash('success', 'La demande de congé a été approuvée avec succès.');

        } elseif ($action === 'reject') {
            // Just update the leave request status
            $update_leave_stmt = $db->prepare(
                "UPDATE leave_requests 
                 SET status = 'rejected', approved_by = :approver_id, approval_date = NOW(), comment = :comment 
                 WHERE id = :leave_id"
            );
            $update_leave_stmt->execute([
                ':approver_id' => $approver_id,
                ':comment' => $comment,
                ':leave_id' => $leave_id
            ]);
            
            flash('success', 'La demande de congé a été rejetée.');
        }

        $db->commit();

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Leave approval/rejection error: " . $e->getMessage());
        flash('error', 'Une erreur de base de données est survenue lors du traitement de la demande.');
    }

    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}




// =========================================================================
// == DATA FETCHING
// =========================================================================
$leave_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$leave_data = null;
$pauses = [];

if (!$leave_id) {
    flash('error', "ID de congé invalide ou manquant.");
    header("Location: " . route('leave_leave_historique'));
    exit();
}

try {
    // Fetch leave request details along with employee information and balances
    $stmt = $db->prepare("
        SELECT 
            lr.*, 
            e.first_name, e.last_name, e.position, e.department,
            e.annual_leave_balance, e.remaining_leave_balance,
            u.username AS approver_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_nin = e.nin
        LEFT JOIN users u ON lr.approved_by = u.id
        WHERE lr.id = :leave_id
    ");
    $stmt->execute([':leave_id' => $leave_id]);
    $leave_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_data) {
        flash('error', "Demande de congé non trouvée.");
        header("Location: " . route('leave_requests'));
        exit();
    }

    // Fetch any pauses associated with this leave request
    $pauses_stmt = $db->prepare("SELECT * FROM leave_pauses WHERE leave_request_id = ? ORDER BY pause_start_date DESC");
    $pauses_stmt->execute([$leave_id]);
    $pauses = $pauses_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Leave view details error: " . $e->getMessage());
    flash('error', "Une erreur de base de données est survenue.");
    header("Location: " . route('leave_requests'));
    exit();
}

// =========================================================================
// == VIEW HELPERS & LOGIC
// =========================================================================

// Define non-suspendable leave types for action buttons logic
$non_suspendable_leave_types = [
    'maladie', 'maternite', 'special_mariage', 'special_naissance',
    'special_deces', 'special_mariage_enf', 'special_circoncision'
];

/**
 * Gets a user-friendly label and a Bootstrap badge color for a leave status.
 */
function getLeaveStatusDetails(string $status): array
{
    $map = [
        'pending'   => ['label' => 'En attente', 'badge' => 'warning'],
        'approved'  => ['label' => 'Approuvé', 'badge' => 'success'],
        'rejected'  => ['label' => 'Rejeté', 'badge' => 'danger'],
        'paused'    => ['label' => 'En pause', 'badge' => 'info'],
        'prise'     => ['label' => 'Pris', 'badge' => 'primary'],
        'cancelled' => ['label' => 'Annulé', 'badge' => 'secondary'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'badge' => 'light'];
}

/**
 * Gets a user-friendly label for a leave type.
 */
function getLeaveTypeLabel(string $type): string
{
    $map = [
        'annuel' => 'Annuel', 'maladie' => 'Maladie', 'maternite' => 'Maternité',
        'unpaid' => 'Sans solde', 'reliquat' => 'Reliquat', 'recup' => 'Récupération',
        'anticipe' => 'Anticipé', 'special_mariage' => 'Spécial - Mariage',
        'special_naissance' => 'Spécial - Naissance', 'special_deces' => 'Spécial - Décès',
        'special_mariage_enf' => 'Spécial - Mariage Enfant', 'special_circoncision' => 'Spécial - Circoncision',
    ];
    return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

$pageTitle = "Détails du Congé";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-calendar2-check"></i> <?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= route('leave_leave_historique') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour à l'historique
        </a>
    </div>

    <div class="row">
        <!-- Left Column: Employee & Leave Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Informations sur l'Employé</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nom:</strong> <?= htmlspecialchars($leave_data['first_name'] . ' ' . $leave_data['last_name']) ?></p>
                            <p><strong>NIN:</strong> <?= htmlspecialchars($leave_data['employee_nin']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Poste:</strong> <?= htmlspecialchars($leave_data['position']) ?></p>
                            <p><strong>Département:</strong> <?= htmlspecialchars($leave_data['department']) ?></p>
                        </div>
                    </div>
                    <hr>
                     <div class="row">
                        <div class="col-md-6">
                            <p class="mb-0"><strong>Solde Congé Annuel:</strong> <span class="fw-bold"><?= htmlspecialchars(number_format($leave_data['annual_leave_balance'], 1)) ?></span> jours</p>
                        </div>
                         <div class="col-md-6">
                            <p class="mb-0"><strong>Solde Congé Reliquat:</strong> <span class="fw-bold"><?= htmlspecialchars(number_format($leave_data['remaining_leave_balance'], 1)) ?></span> jours</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Détails de la Demande</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Type de Congé:</strong> <?= htmlspecialchars(getLeaveTypeLabel($leave_data['leave_type'])) ?></p>
                            <p><strong>Date de début:</strong> <?= formatDate($leave_data['start_date']) ?></p>
                            <p><strong>Date de fin:</strong> <?= formatDate($leave_data['end_date']) ?></p>
                            <p><strong>Jours demandés:</strong> <span class="badge bg-primary fs-6"><?= htmlspecialchars($leave_data['days_requested']) ?></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Année du congé:</strong> <?= htmlspecialchars($leave_data['leave_year']) ?></p>
                            <p><strong>Date de la demande:</strong> <?= formatDate($leave_data['created_at']) ?></p>
                            <p><strong>Motif:</strong></p>
                            <p class="text-muted" style="white-space: pre-wrap;"><?= htmlspecialchars($leave_data['reason']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($pauses)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pause-btn"></i> Historique des Suspensions</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pauses as $pause): ?>
                        <li class="list-group-item">
                            Suspendu du <strong><?= formatDate($pause['pause_start_date']) ?></strong> au <strong><?= formatDate($pause['pause_end_date']) ?></strong>.
                            <br><small class="text-muted">Motif: <?= htmlspecialchars($pause['reason']) ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Status, Actions, Documents -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Statut et Approbation</h5>
                </div>
                <div class="card-body">
                    <?php $statusDetails = getLeaveStatusDetails($leave_data['status']); ?>
                    <p><strong>Statut:</strong> <span class="badge bg-<?= $statusDetails['badge'] ?> fs-6"><?= htmlspecialchars($statusDetails['label']) ?></span></p>
                    <p><strong>Approuvé par:</strong> <?= htmlspecialchars($leave_data['approver_name'] ?? 'N/A') ?></p>
                    <p><strong>Date d'approbation:</strong> <?= $leave_data['approval_date'] ? formatDate($leave_data['approval_date']) : 'N/A' ?></p>
                    <hr>
                    <p><strong>Commentaire:</strong></p>
                    <p class="text-muted fst-italic"><?= htmlspecialchars($leave_data['comment'] ?? 'Aucun commentaire.') ?></p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Répartition des Jours</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($leave_data['use_annuel'] > 0): ?><li class="list-group-item">Congé Annuel: <span class="float-end fw-bold"><?= htmlspecialchars($leave_data['use_annuel']) ?> j</span></li><?php endif; ?>
                        <?php if ($leave_data['use_reliquat'] > 0): ?><li class="list-group-item">Reliquat: <span class="float-end fw-bold"><?= htmlspecialchars($leave_data['use_reliquat']) ?> j</span></li><?php endif; ?>
                        <?php if ($leave_data['use_anticipe'] > 0): ?><li class="list-group-item">Anticipé: <span class="float-end fw-bold"><?= htmlspecialchars($leave_data['use_anticipe']) ?> j</span></li><?php endif; ?>
                        <?php if ($leave_data['use_recup'] > 0): ?><li class="list-group-item">Récupération: <span class="float-end fw-bold"><?= htmlspecialchars($leave_data['use_recup']) ?> j</span></li><?php endif; ?>
                        <?php if ($leave_data['use_unpaid'] > 0): ?><li class="list-group-item">Sans Solde: <span class="float-end fw-bold"><?= htmlspecialchars($leave_data['use_unpaid']) ?> j</span></li><?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card mb-4">
                 <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <?php if ($leave_data['status'] === 'pending'): ?>
                        <form method="post" action="<?= route('leave_view', ['id' => $leave_id]) ?>">
                            <?php csrf_input(); ?>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Commentaire (optionnel)</label>
                                <textarea name="comment" id="comment" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette demande ?');">
                                    <i class="bi bi-x-circle"></i> Rejeter
                                </button>
                                <button type="submit" name="action" value="approve" class="btn btn-success" onclick="return confirm('Êtes-vous sûr de vouloir approuver cette demande ?');">
                                    <i class="bi bi-check-circle"></i> Approuver
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <?php if (($leave_data['status'] === 'approved' || $leave_data['status'] === 'prise') && !in_array($leave_data['leave_type'], $non_suspendable_leave_types)): ?>
                                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#pauseLeaveModal">
                                    <i class="bi bi-pause-circle"></i> Suspendre / Interrompre
                                </button>
                            <?php endif; ?>
                            <?php if ($leave_data['status'] === 'paused'): ?>
                                <form method="post" action="<?= route('leave_resume_keep_enddate', ['id' => $leave_data['id']]) ?>" onsubmit="return confirm('Reprendre en conservant la date de fin ?');">
                                    <?php csrf_input(); ?>
                                    <button type="submit" class="btn btn-success w-100">Reprendre (Garder Date Fin)</button>
                                </form>
                                <form method="post" action="<?= route('leave_resume_extend_enddate', ['id' => $leave_data['id']]) ?>" onsubmit="return confirm('Reprendre en prolongeant la date de fin ?');">
                                    <?php csrf_input(); ?>
                                    <button type="submit" class="btn btn-info w-100">Reprendre (Prolonger Date Fin)</button>
                                </form>
                                <form method="post" action="<?= route('leave_cancel_leave', ['id' => $leave_data['id']]) ?>" onsubmit="return confirm('Annuler ce congé ? Les jours déjà pris seront décomptés.');">
                                     <?php csrf_input(); ?>
                                    <button type="submit" class="btn btn-danger w-100">Annuler le Congé</button>
                                </form>
                            <?php endif; ?>
                             <a class="btn btn-outline-danger" href="<?= route('leave_certificate', ['leave_id' => $leave_data['id']]) ?>" target="_blank">
                                <i class="bi bi-printer-fill"></i> Imprimer le Certificat
                            </a>
                            <?php if (!empty($leave_data['justification_path'])): ?>
                                <a href="<?= APP_LINK ?>/assets/uploads/justifications/<?= htmlspecialchars($leave_data['justification_path']) ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="bi bi-file-earmark-arrow-down"></i> Voir la Justification
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pause Leave Modal -->
<div class="modal fade" id="pauseLeaveModal" tabindex="-1" aria-labelledby="pauseLeaveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" action="<?= route('leave_pause_leave', ['id' => $leave_data['id']]) ?>">
      <?php csrf_input(); ?>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="pauseLeaveModalLabel">Suspendre/Interrompre le congé</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="pause_start" class="form-label">Début de la suspension*</label>
            <input type="date" class="form-control" id="pause_start" name="pause_start" required>
          </div>
          <div class="mb-3">
            <label for="pause_end" class="form-label">Fin de la suspension*</label>
            <input type="date" class="form-control" id="pause_end" name="pause_end" required>
          </div>
          <div class="mb-3">
            <label for="pause_reason" class="form-label">Motif*</label>
            <textarea class="form-control" id="pause_reason" name="pause_reason" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-warning">Suspendre</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
    // Ensure pause_end is not before pause_start
    const pauseStartInput = document.getElementById('pause_start');
    const pauseEndInput = document.getElementById('pause_end');

    if(pauseStartInput && pauseEndInput) {
        pauseStartInput.addEventListener('change', function() {
            if (pauseEndInput.value < this.value) {
                pauseEndInput.value = this.value;
            }
            pauseEndInput.min = this.value;
        });
        if(pauseStartInput.value) {
            pauseEndInput.min = pauseStartInput.value;
        }
    }
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>
