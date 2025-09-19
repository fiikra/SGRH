<?php
// --- Security Headers: Set before any output ---
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid request ID";
    header("Location: " . route('leave_requests'));
    exit();
}

$id = (int)$_GET['id'];

// Fetch leave request details along with employee information
$stmt = $db->prepare("SELECT l.*, e.first_name, e.last_name, e.nin, e.annual_leave_balance, e.remaining_leave_balance
                        FROM leave_requests l
                        JOIN employees e ON l.employee_nin = e.nin
                        WHERE l.id = ?");
$stmt->execute([$id]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC); // Changed to FETCH_ASSOC for consistency

if (!$leave) {
    $_SESSION['error'] = "Leave request not found";
    header("Location: " . route('leave_requests'));
    exit();
}

// Fetch pauses as before
$pauses_stmt = $db->prepare("SELECT * FROM leave_pauses WHERE leave_request_id = ? ORDER BY pause_start_date");
$pauses_stmt->execute([$leave['id']]);
$pauses = $pauses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine if currently paused (status must be 'paused')
$isPaused = false;
$currentPause = null;
if ($leave['status'] === 'paused') {
    $today_date = date('Y-m-d');
    foreach ($pauses as $pause_item) {
        if ($pause_item['pause_start_date'] <= $today_date && $today_date <= $pause_item['pause_end_date']) {
            $isPaused = true;
            $currentPause = $pause_item;
            break;
        }
    }
}

// Define non-suspendable leave types
$non_suspendable_leave_types = [
    'maladie',
    'maternite',
    'special_mariage',
    'special_naissance',
    'special_deces',
    'special_mariage_enf',
    'special_circoncision'
];

$pageTitle = "Détails de la Demande de Congé";
include __DIR__. '../../../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= $pageTitle ?></h1>
        <a href="<?= route('employees_view', ['nin' => $leave['nin']]) ?>#leaves" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour Profil Employé
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Informations Employé</h5>
                    <p>
                        <strong>Nom:</strong> <?= htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) ?><br>
                        <strong>NIN:</strong> <?= htmlspecialchars($leave['nin']) ?><br>
                        <strong>Solde Congé Annuel Actuel:</strong> <?= number_format($leave['annual_leave_balance'], 1) ?> jours<br>
                        <strong>Solde Congé Reliquat Actuel:</strong> <?= number_format($leave['remaining_leave_balance'], 1) ?> jours
                    </p>
                    <?php if ($leave['status'] === 'paused'): ?>
                        <div class="mb-3 d-flex flex-column gap-2">
                            <form method="post" action="<?= route('leave_resume_keep_enddate', ['id' => $leave['id']]) ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir reprendre ce congé en conservant la date de fin ?');">
                               <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                            <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-play-circle"></i> Reprendre (Conserver Date de Fin)
                                </button>
                            </form>
                            <form method="post" action="<?= route('leave_resume_extend_enddate', ['id' => $leave['id']]) ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir reprendre ce congé en prolongeant la date de fin ?');">
                               <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                            <button type="submit" class="btn btn-info w-100">
                                    <i class="bi bi-arrow-repeat"></i> Reprendre (Prolonger Date de Fin)
                                </button>
                            </form>
                            <form method="post" action="<?= route('leave_cancel_leave', ['id' => $leave['id']]) ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce congé ? Les jours déjà pris seront décomptés.');">
                              <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                            <button type="submit" class="btn btn-danger w-100">
                                    <i class="bi bi-x-octagon"></i> Annuler le Congé
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <?php 
                    if (($leave['status'] === 'approved' || $leave['status'] === 'prise') && !in_array($leave['leave_type'], $non_suspendable_leave_types)): ?>
                        <div class="mb-3">
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#pauseLeaveModal">
                                <i class="bi bi-pause-circle"></i> Suspendre / Interrompre ce congé
                                
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5>Détails de la Demande</h5>
                    <p>
                        <strong>Type:</strong> <?= ucfirst(htmlspecialchars($leave['leave_type'])) ?><br>
                        <strong>Statut:</strong>
                        <?php
                        $status = $leave['status'];
                        switch ($status) {
                            case 'approved': $badgeClass = 'success'; $statusLabel = 'Approuvée'; break;
                            case 'paused': $badgeClass = 'warning text-dark'; $statusLabel = 'Suspendue'; break;
                            case 'rejected': $badgeClass = 'danger'; $statusLabel = 'Rejetée'; break;
                            case 'pending': $badgeClass = 'secondary'; $statusLabel = 'En attente'; break;
                            case 'prise': $badgeClass = 'primary'; $statusLabel = 'En Cours'; break;
                            case 'cancelled': $badgeClass = 'dark'; $statusLabel = 'Annulée'; break;
                            default: $badgeClass = 'info'; $statusLabel = ucfirst($status); break;
                        }
                        ?>
                        <span class="badge bg-<?= $badgeClass ?>"><?= $statusLabel ?></span>
                        <?php if ($isPaused && $currentPause): ?>
                            <div class="alert alert-warning mt-2 small">
                                Ce congé est actuellement suspendu du <?= date('d/m/Y', strtotime($currentPause['pause_start_date'])) ?>
                                au <?= date('d/m/Y', strtotime($currentPause['pause_end_date'])) ?>.<br>
                                Motif: <?= htmlspecialchars($currentPause['reason']) ?> <br>
                                Il reprendra automatiquement après cette période sauf action manuelle.
                            </div>
                        <?php endif; ?><br>
                        <strong>Jours Demandés:</strong> <?= htmlspecialchars($leave['days_requested']) ?><br>
                        <strong>Année de Congé:</strong> <?= htmlspecialchars($leave['leave_year']) ?><br>
                        <strong>Utilise Reliquat:</strong> <?= $leave['use_remaining'] ? 'Oui' : 'Non' ?>
                    </p>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <p>
                        <strong>Période:</strong><br>
                        Du <?= date('d/m/Y', strtotime($leave['start_date'])) ?> au <?= date('d/m/Y', strtotime($leave['end_date'])) ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Date de Demande:</strong><br>
                        <?= date('d/m/Y H:i', strtotime($leave['created_at'])) ?>
                    </p>
                </div>
            </div>

            <div class="mt-3">
                <h5>Motif</h5>
                <div class="border p-3 bg-light rounded">
                    <?= nl2br(htmlspecialchars($leave['reason'])) ?>
                </div>
            </div>

            <?php if (!empty($leave['justification_path']) && file_exists($leave['justification_path'])): ?>
                <div class="mt-3">
                    <h5>Justificatif</h5>
                    <p><a href="/<?= htmlspecialchars($leave['justification_path']) ?>" target="_blank" class="btn btn-sm btn-info"><i class="bi bi-paperclip"></i> Voir Justificatif</a></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($leave['comment'])): ?>
                <div class="mt-3">
                    <h5>Commentaire Administrateur</h5>
                    <div class="border p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($leave['comment'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($leave['status'] === 'pending'): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Actions</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= route('leave_view', ['id' => $id]) ?>">
                   <?php csrf_input(); // ✅ Correct: Just call the function here ?>
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
            </div>
        </div>
    <?php elseif (!empty($leave['approval_date'])): ?>
        <div class="alert alert-info">
            Cette demande a été <?= $statusLabel ?>
            le <?= date('d/m/Y H:i', strtotime($leave['approval_date'])) ?>
            <?php if ($leave['approved_by']) {
                $approver_stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                $approver_stmt->execute([$leave['approved_by']]);
                $approver = $approver_stmt->fetchColumn();
                echo " par " . htmlspecialchars($approver ?? 'N/A');
            } ?>.
        </div>
    <?php endif; ?>

    <?php if ($pauses): ?>
        <div class="card my-3">
            <div class="card-header"><strong>Historique des Suspensions/Interruptions</strong></div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                <?php foreach ($pauses as $pause_item): ?>
                    <li class="list-group-item">
                        Suspendue du <strong><?= date('d/m/Y', strtotime($pause_item['pause_start_date'])) ?></strong>
                        au <strong><?= date('d/m/Y', strtotime($pause_item['pause_end_date'])) ?></strong>.
                        <br>Motif: <?= htmlspecialchars($pause_item['reason']) ?>
                        <?php if ($pause_item['attachment_filename']): ?>
                            <br><a href="/<?= htmlspecialchars(SICK_JUSTIFICATIONS_PATH_VIEW . $pause_item['attachment_filename']) ?>" target="_blank"><i class="bi bi-paperclip"></i> Voir le justificatif</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$today_modal = date('Y-m-d'); // Renamed for modal scope
$threeDaysAgo_modal = date('Y-m-d', strtotime('-3 days'));
$minDate_modal = max($leave['start_date'], $threeDaysAgo_modal);
$maxStartDate_modal = min($today_modal, $leave['end_date']);
$maxEndDate_modal = $leave['end_date'];
?>
<div class="modal fade" id="pauseLeaveModal" tabindex="-1" aria-labelledby="pauseLeaveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" enctype="multipart/form-data" action="<?= route('leave_pause_leave', ['id' => $leave['id']]) ?>">
      <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="pauseLeaveModalLabel">Suspendre/Interrompre le congé</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="pause_start" class="form-label">Début de la suspension*</label>
            <input type="date" class="form-control" id="pause_start" name="pause_start" required
                   min="<?= htmlspecialchars($minDate_modal) ?>"
                   max="<?= htmlspecialchars($maxStartDate_modal) ?>"
                   value="<?= htmlspecialchars($today_modal > $maxStartDate_modal ? $maxStartDate_modal : $today_modal) ?>">
          </div>
          <div class="mb-3">
            <label for="pause_end" class="form-label">Fin de la suspension*</label>
            <input type="date" class="form-control" id="pause_end" name="pause_end" required
                   min="<?= htmlspecialchars($minDate_modal) ?>"
                   max="<?= htmlspecialchars($maxEndDate_modal) ?>">
          </div>
          <div class="mb-3">
            <label for="pause_reason" class="form-label">Motif*</label>
            <textarea class="form-control" id="pause_reason" name="pause_reason" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label for="pause_attachment" class="form-label">Justificatif (PDF, facultatif)</label>
            <input type="file" class="form-control" id="pause_attachment" name="pause_attachment" accept="application/pdf">
          </div>
          <div class="form-text">
            <small>
              Exemples de motifs: Maladie (avec certificat), rappel au travail, événement familial majeur, etc.
            </small>
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
         // Set initial min for pause_end based on default pause_start
        if(pauseStartInput.value) {
            pauseEndInput.min = pauseStartInput.value;
        }
    }
</script>
<?php include __DIR__. '../../../../includes/footer.php'; ?>