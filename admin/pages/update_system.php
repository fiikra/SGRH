<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

// --- Core Includes ---
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/flash.php';
require_once '../includes/flash_messages.php';

// --- Security and Access Control ---
redirectIfNotAdminOrHR();

/**
 * Logs a message to the system_logs table.
 *
 * @param PDO $db The database connection object.
 * @param string $level Log level (e.g., 'INFO', 'ERROR', 'SUCCESS').
 * @param string $message The log message.
 * @param string $context The context of the log (e.g., 'LEAVE_ACCRUAL').
 * @param int|null $userId The ID of the user performing the action.
 */
function log_to_db($db, $level, $message, $context = 'LEAVE_SYSTEM', $userId = null) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO system_logs (log_level, message, context, created_by_user_id) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$level, $message, $context, $userId]);
    } catch (Exception $e) {
        // Fallback to the server's error log if database logging fails catastrophically.
        error_log("CRITICAL DB LOG FAILURE: " . $e->getMessage() . " | Original Log: [$level] [$context] $message");
    }
}

// --- Page Setup ---
$pageTitle = "Mise à jour du Système (Congés)";
$today = date('Y-m-d');
$currentRunYear = (int)date('Y');
$currentRunMonth = (int)date('m');
$currentUserId = $_SESSION['user_id'] ?? null;

$resumedLeavesMessages = [];
$markedPriseMessages = [];
$addedLeaveMessages = [];
$system_update_already_done_for_month = false;

// ==== AUTO RESUME PAUSED LEAVES ==== //
$resumeSql = "
    SELECT l.id, e.first_name, e.last_name, e.nin
    FROM leave_requests l
    INNER JOIN leave_pauses p ON l.id = p.leave_request_id
    JOIN employees e ON l.employee_nin = e.nin
    WHERE l.status = 'paused' AND p.pause_end_date < ?
    GROUP BY l.id
    HAVING MAX(p.pause_end_date) < ?
";
$resumeStmt = $db->prepare($resumeSql);
$resumeStmt->execute([$today, $today]);
$pausedToResume = $resumeStmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($pausedToResume)) {
    $updateLeaveStatusToApprovedStmt = $db->prepare("UPDATE leave_requests SET status = 'approved' WHERE id = ?");
    foreach ($pausedToResume as $leave) {
        try {
            $db->beginTransaction();
            $updateLeaveStatusToApprovedStmt->execute([$leave['id']]);
            $db->commit();
            $logMessage = "Congé Auto-Repris: ID={$leave['id']}, Employé={$leave['first_name']} {$leave['last_name']} (NIN: {$leave['nin']}). Statut changé à 'approved'.";
            log_to_db($db, 'SUCCESS', $logMessage, 'LEAVE_RESUME', $currentUserId);
            $resumedLeavesMessages[] = "Congé repris pour {$leave['first_name']} {$leave['last_name']} (NIN: {$leave['nin']}).";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errorMessage = "Erreur reprise auto congé ID {$leave['id']} pour {$leave['first_name']} {$leave['last_name']}: " . $e->getMessage();
            log_to_db($db, 'ERROR', $errorMessage, 'LEAVE_RESUME', $currentUserId);
            $resumedLeavesMessages[] = "<span class='text-danger'>Erreur reprise auto congé pour {$leave['first_name']} {$leave['last_name']}.</span>";
        }
    }
}

// ==== AUTO: Mark leaves as "prise" if fully consumed ==== //
$consumedLeavesSql = "
    SELECT l.id, e.first_name, e.last_name, e.nin, l.start_date, l.end_date
    FROM leave_requests l
    JOIN employees e ON l.employee_nin = e.nin
    WHERE l.status = 'approved' AND l.end_date < ?
      AND NOT EXISTS (
          SELECT 1 FROM leave_pauses lp
          WHERE lp.leave_request_id = l.id AND lp.pause_end_date >= ?
      )
";
$consumedStmt = $db->prepare($consumedLeavesSql);
$consumedStmt->execute([$today, $today]);
$leavesToMarkPrise = $consumedStmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($leavesToMarkPrise)) {
    $updatePriseStmt = $db->prepare("UPDATE leave_requests SET status = 'prise' WHERE id = ?");
    foreach ($leavesToMarkPrise as $leave) {
        try {
            $updatePriseStmt->execute([$leave['id']]);
            $logMessage = "Congé passé marqué comme 'prise' pour {$leave['first_name']} {$leave['last_name']} (NIN: {$leave['nin']}) du ".formatDate($leave['start_date'])." au ".formatDate($leave['end_date']);
            log_to_db($db, 'INFO', $logMessage, 'LEAVE_MARK_PRISE', $currentUserId);
            $markedPriseMessages[] = "Congé pour {$leave['first_name']} {$leave['last_name']} marqué comme 'Pris'.";
        } catch (Exception $e) {
            $errorMessage = "Erreur marquage 'prise' congé ID {$leave['id']}: " . $e->getMessage();
            log_to_db($db, 'ERROR', $errorMessage, 'LEAVE_MARK_PRISE', $currentUserId);
            $markedPriseMessages[] = "<span class='text-danger'>Erreur marquage 'prise' pour {$leave['first_name']} {$leave['last_name']}.</span>";
        }
    }
}

// ==== CHECK IF MONTHLY ACCRUAL IS ALREADY DONE ==== //
$checkLogStmt = $db->prepare("SELECT id FROM monthly_leave_accruals_log WHERE accrual_year = ? AND accrual_month = ?");
$checkLogStmt->execute([$currentRunYear, $currentRunMonth]);

if ($checkLogStmt->fetch()) {
    $system_update_already_done_for_month = true;
    $message = "L'ajout mensuel de 2.5 jours de congé pour " . strftime('%B %Y', mktime(0, 0, 0, $currentRunMonth, 1, $currentRunYear)) . " a déjà été effectué.";
    $addedLeaveMessages[] = $message;
    // Log that a user visited this page when the process was already complete for the month.
    log_to_db($db, 'INFO', 'Visite de la page de MAJ mensuelle alors que celle-ci était déjà effectuée pour le mois courant.', 'LEAVE_ACCRUAL', $currentUserId);
}

// --- Include Header ---
include __DIR__. '../../../includes/header.php';
?>

<?php // JavaScript messages for PHP-driven actions ?>
<script>
    var tempLogMessages = [];
    <?php foreach ($resumedLeavesMessages as $msg): ?>
        tempLogMessages.push({ message: "<?= addslashes($msg) ?>", type: 'success' });
    <?php endforeach; ?>
    <?php foreach ($markedPriseMessages as $msg): ?>
        tempLogMessages.push({ message: "<?= addslashes($msg) ?>", type: 'primary' });
    <?php endforeach; ?>
    <?php foreach ($addedLeaveMessages as $msg): ?>
        tempLogMessages.push({ message: "<?= addslashes($msg) ?>", type: 'info' });
    <?php endforeach; ?>
</script>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="bi bi-arrow-repeat"></i> <?= htmlspecialchars($pageTitle) ?></h3>
                        <span class="badge bg-light text-dark"><?= date('d/m/Y') ?></span>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php display_flash_messages(); ?>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i> Période de référence des congés: 1er Juillet <?= getCurrentLeaveYear() ?> au 30 Juin <?= getCurrentLeaveYear()+1 ?>.
                        L'ajout des 2.5 jours de congé (si applicable) s'effectuera pour le mois de <strong><?= strftime('%B %Y', mktime(0,0,0,$currentRunMonth,1,$currentRunYear)) ?></strong>.
                    </div>
                    
                    <div id="update-container">
                        <div class="progress mb-3" style="height: 30px; font-size: 1rem;">
                            <div id="update-progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="bi bi-list-check"></i> Journal d'activité</h5>
                                    </div>
                                    <div id="update-log" class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item text-muted">
                                                <i class="bi bi-hourglass"></i> Prêt à démarrer la mise à jour...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="bi bi-people-fill"></i> Employés (Solde Congé +2.5j)</h5>
                                    </div>
                                    <div id="employee-updates" class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item text-muted placeholder-message"> <i class="bi bi-people"></i> En attente de démarrage...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button id="start-update" class="btn btn-primary btn-lg py-3">
                                <i class="bi bi-play-circle-fill"></i> Démarrer la mise à jour Système
                            </button>
                            <a id="cancel-btn" href="<?= APP_LINK ?>/admin/index.php?route=dashboard" class="btn btn-outline-secondary d-none">
                                <i class="bi bi-x-circle-fill"></i> Retour au tableau de bord
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const startBtn = document.getElementById('start-update');
    const progressBar = document.getElementById('update-progress');
    const updateLogContainer = document.getElementById('update-log').querySelector('.list-group');
    const employeeUpdatesContainer = document.getElementById('employee-updates').querySelector('.list-group');
    const cancelBtn = document.getElementById('cancel-btn');
    const dashboardUrl = "<?= APP_LINK ?>/admin/index.php?route=dashboard";

    let eventSource = null;
    let retryCount = 0;
    const MAX_RETRIES = 3;
    let updateProcessStarted = false;

    function addLogMessage(message, type = 'info', container = updateLogContainer) {
        const icons = {
            'info': 'info-circle-fill text-info', 'success': 'check-circle-fill text-success',
            'warning': 'exclamation-triangle-fill text-warning', 'danger': 'exclamation-octagon-fill text-danger',
            'connection': 'plug-fill text-secondary', 'employee': 'person-check-fill text-primary',
            'primary': 'list-ol text-primary'
        };
        const item = document.createElement('div');
        item.className = `list-group-item list-group-item-action list-group-item-light`;
        let iconHtml = `<i class="bi bi-${icons[type] || icons['info']} me-2"></i>`;
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="me-auto" style="word-break: break-word;">${iconHtml}<span class="fw-normal">${message}</span></div>
                <span class="small text-muted ms-2 text-nowrap">${new Date().toLocaleTimeString('fr-FR')}</span>
            </div>`;
        if (updateLogContainer.children.length === 1 && updateLogContainer.firstElementChild.classList.contains('text-muted')) {
            updateLogContainer.innerHTML = '';
        }
        container.prepend(item);
        container.scrollTop = 0;
    }

    if (typeof tempLogMessages !== 'undefined' && Array.isArray(tempLogMessages) && tempLogMessages.length > 0) {
        updateLogContainer.innerHTML = ''; // Clear initial message
        tempLogMessages.forEach(log => addLogMessage(log.message, log.type));
        tempLogMessages = [];
    }
    
    <?php if ($system_update_already_done_for_month): ?>
        startBtn.disabled = true;
        startBtn.innerHTML = '<i class="bi bi-check-all"></i> Ajout mensuel déjà effectué ce mois-ci';
        progressBar.style.width = '100%';
        progressBar.textContent = '100% (Déjà Fait)';
        progressBar.classList.add('bg-success');
        progressBar.classList.remove('progress-bar-animated');
        cancelBtn.classList.remove('d-none');
        if (employeeUpdatesContainer.children.length === 1 && employeeUpdatesContainer.firstElementChild.classList.contains('placeholder-message')) {
            employeeUpdatesContainer.innerHTML = '<div class="list-group-item text-muted"><i class="bi bi-info-circle"></i> L\'opération mensuelle a déjà été traitée.</div>';
        }
    <?php endif; ?>

    function initializeSSEConnection() {
        if (eventSource) { eventSource.close(); }
        updateProcessStarted = true;
        const sseUrl = `index.php?route=update_system&action=stream&t=${Date.now()}`;
        eventSource = new EventSource(sseUrl);

        eventSource.onopen = () => {
            addLogMessage('Connexion au serveur établie.', 'success');
            retryCount = 0;
            startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Mise à jour en cours...';
        };
        eventSource.addEventListener('info', (e) => handleServerMessage(JSON.parse(e.data), 'info'));
        eventSource.addEventListener('progress', (e) => handleServerMessage(JSON.parse(e.data), 'progress'));
        eventSource.addEventListener('employee_update', (e) => handleServerMessage(JSON.parse(e.data), 'employee_update'));
        eventSource.addEventListener('complete', (e) => handleServerMessage(JSON.parse(e.data), 'complete'));
        eventSource.addEventListener('error', (e) => { 
            try { handleServerMessage(JSON.parse(e.data), 'error'); } 
            catch (parseErr) { handleErrorFromServer('Erreur de communication avec le serveur.'); }
        });
        eventSource.onerror = () => { handleConnectionError(); };
    }
    
    function handleConnectionError() {
        if (eventSource) eventSource.close();
        retryCount++;
        if (retryCount < MAX_RETRIES) {
            addLogMessage(`Problème de connexion, nouvelle tentative dans 5s... (${retryCount}/${MAX_RETRIES})`, 'warning');
            setTimeout(initializeSSEConnection, 5000);
        } else {
            handleErrorFromServer(`Échec de la connexion après ${MAX_RETRIES} tentatives.`);
            updateProcessStarted = false;
        }
    }

    function handleServerMessage(data, typeOverride = null) {
        const messageType = typeOverride || data.event;
        switch(messageType) {
            case 'info': addLogMessage(data.message, 'info'); break;
            case 'progress': updateProgress(data.value, data.message); break;
            case 'employee_update':
                addEmployeeUpdate(data.employee);
                if(data.log_message) addLogMessage(data.log_message, 'employee');
                break;
            case 'complete': completeUpdate(data.message); break;
            case 'error': handleErrorFromServer(data.message); break;
            default: addLogMessage(`Message non reconnu: ${JSON.stringify(data)}`, 'warning');
        }
    }

    function addEmployeeUpdate(employee) {
        if (!employee || !employee.nin) return;
        const placeholder = employeeUpdatesContainer.querySelector('.placeholder-message');
        if (placeholder) placeholder.remove();

        const item = document.createElement('div');
        item.className = 'list-group-item';
        item.dataset.nin = employee.nin;
        item.innerHTML = `<div class="d-flex justify-content-between align-items-center"><div><span class="fw-bold">${employee.full_name}</span><div class="small text-muted">NIN: ${employee.nin} | Ancien: ${employee.old_balance}j | Nouveau: ${employee.new_balance}j</div></div><span class="badge bg-success-soft text-success rounded-pill">+2.5 jours</span></div>`;
        employeeUpdatesContainer.prepend(item);
        employeeUpdatesContainer.scrollTop = 0;
    }

    function updateProgress(percent, message = '') {
        progressBar.style.width = `${percent}%`;
        progressBar.setAttribute('aria-valuenow', percent);
        progressBar.textContent = `${percent}%`;
        if (message) addLogMessage(message, 'info');
    }

    function cleanupSSE() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        updateProcessStarted = false;
    }

    function handleErrorFromServer(message) {
        addLogMessage(message, 'danger');
        cleanupSSE();
        startBtn.disabled = false;
        startBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Réessayer la Mise à Jour';
        cancelBtn.classList.remove('d-none');
        progressBar.classList.remove('progress-bar-animated', 'bg-success');
        progressBar.classList.add('bg-danger');
        progressBar.textContent = 'Erreur';
    }

    function completeUpdate(message) {
        addLogMessage(message || 'Mise à jour terminée avec succès!', 'success');
        updateProgress(100);
        progressBar.classList.add('bg-success');
        progressBar.classList.remove('progress-bar-animated');
        cleanupSSE();

        startBtn.classList.add('d-none');
        cancelBtn.classList.remove('d-none');
        cancelBtn.classList.replace('btn-outline-secondary', 'btn-success');
        cancelBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Redirection vers le tableau de bord...';
        cancelBtn.disabled = true;

        // Redirect to dashboard after a 4-second delay
        setTimeout(() => {
            window.location.href = dashboardUrl;
        }, 4000);
    }

    function startUpdateProcess() {
        if (updateProcessStarted) return;
        startBtn.disabled = true;
        startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Initialisation...';
        progressBar.style.width = '0%'; progressBar.textContent = '0%';
        progressBar.classList.remove('bg-danger', 'bg-success');
        progressBar.classList.add('progress-bar-animated');
        updateLogContainer.innerHTML = '<div class="list-group-item text-muted"><i class="bi bi-hourglass"></i> Démarrage du processus...</div>';
        employeeUpdatesContainer.innerHTML = '<div class="list-group-item text-muted placeholder-message"><i class="bi bi-people"></i> En attente des mises à jour des employés...</div>';
        retryCount = 0;
        initializeSSEConnection();
    }
    
    startBtn.addEventListener('click', startUpdateProcess);
});
</script>

<?php include '../includes/footer.php'; ?>