<?php
if (!defined('APP_SECURE_INCLUDE')) { 
    http_response_code(403); 
    exit('No direct access allowed');
}
redirectIfNotHR();
require_once __DIR__ . '../../../../includes/attendance_functions.php';

// --- Date & Filter Handling ---
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$employeeId = isset($_GET['nin']) ? sanitize($_GET['nin']) : null;
$filterStatus = isset($_GET['filter_status']) ? sanitize($_GET['filter_status']) : 'all';

// --- Navigation Links ---
$prevMonth = ($currentMonth == 1) ? 12 : $currentMonth - 1;
$prevYear = ($currentMonth == 1) ? $currentYear - 1 : $currentYear;
$nextMonth = ($currentMonth == 12) ? 1 : $currentMonth + 1;
$nextYear = ($currentMonth == 12) ? $currentYear + 1 : $currentYear;

// --- Employee List Pagination & Filtering ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$employees_query = "SELECT nin, first_name, last_name FROM employees WHERE status = 'active' ORDER BY last_name, first_name";
$all_employees = $db->query($employees_query)->fetchAll(PDO::FETCH_ASSOC);

$employees = $all_employees;
if ($filterStatus === 'unvalidated') {
    $employees = array_filter($employees, function($emp) use ($db, $currentYear, $currentMonth) {
        return hasUnvalidatedEntries($db, $emp['nin'], $currentYear, $currentMonth);
    });
}
$totalFilteredEmployees = count($employees);
$employees = array_slice($employees, $offset, $perPage);

// --- Data for Selected Employee ---
if ($employeeId) {
    $employee_stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
    $employee_stmt->execute([$employeeId]);
    $employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

    $attendance = fetchAttendanceData($db, $employeeId, $currentYear, $currentMonth);
    $summary = calculateAttendanceSummary($db, $employeeId, $currentYear, $currentMonth);
}

include __DIR__.'../../../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-calendar-check"></i> Gestion des Présences</h1>
        <div class="btn-group">
             <a href="<?= route('attendance_log', ['year' => $prevYear, 'month' => $prevMonth, 'nin' => $employeeId, 'page' => $page, 'filter_status' => $filterStatus]) ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> Mois Préc.</a>
            <span class="btn btn-light disabled fs-5"><?= monthName($currentMonth) . " " . $currentYear ?></span>
            <a href="<?= route('attendance_log', ['year' => $nextYear, 'month' => $nextMonth, 'nin' => $employeeId, 'page' => $page, 'filter_status' => $filterStatus]) ?>" class="btn btn-outline-secondary">Mois Suiv. <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="row gx-4">
        <div class="col-lg-3">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Employés Actifs</h5>
                    <span class="badge bg-primary rounded-pill"><?= $totalFilteredEmployees ?></span>
                </div>
                 <div class="card-body p-2">
                    <form class="d-flex gap-2 p-2 border-bottom mb-2" method="GET" action="<?= route('attendance_log') ?>">
                        <input type="hidden" name="route" value="attendance_log">
                        <input type="hidden" name="year" value="<?= $currentYear ?>">
                        <input type="hidden" name="month" value="<?= $currentMonth ?>">
                        <input type="hidden" name="nin" value="<?= $employeeId ?>">
                        <select name="filter_status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?= $filterStatus == 'all' ? 'selected' : ''?>>Tous les employés</option>
                            <option value="unvalidated" <?= $filterStatus == 'unvalidated' ? 'selected' : ''?>>Avec Anomalies</option>
                        </select>
                    </form>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($employees)): ?>
                            <li class="list-group-item text-muted">Aucun employé trouvé.</li>
                        <?php else: foreach ($employees as $emp): ?>
                        <li class="list-group-item list-group-item-action p-2<?= $employeeId == $emp['nin'] ? ' active' : '' ?>">
                            <a href="<?= route('attendance_log', ['year' => $currentYear, 'month' => $currentMonth, 'nin' => $emp['nin'], 'page' => $page, 'filter_status' => $filterStatus]) ?>" class="text-decoration-none d-flex justify-content-between align-items-center w-100 stretched-link">
                                <span class="text-truncate"><?= htmlspecialchars($emp['last_name'].' '.$emp['first_name']) ?></span>
                                <?php if (hasUnvalidatedEntries($db, $emp['nin'], $currentYear, $currentMonth)): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning" title="Entrées non validées ou manquantes"></i>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
                 <?php 
                 $totalPages = ceil($totalFilteredEmployees / $perPage);
                 renderPagination('attendance_log', $page, $totalPages, ['year' => $currentYear, 'month' => $currentMonth, 'nin' => $employeeId, 'filter_status' => $filterStatus]);
                 ?>
            </div>
        </div>

        <div class="col-lg-9">
             <?php display_flash_messages(); ?>
            <?php if ($employeeId && $employee): ?>
            <div class="card shadow-sm">
                 <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">
                        <img src="/<?= htmlspecialchars($employee['photo_path'] ?? 'assets/images/default-avatar.png') ?>" class="rounded-circle me-2" width="40" height="40" alt="photo" onerror="this.src='/assets/images/default-avatar.png';">
                        <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?>
                        <small class="text-muted">(<?= $employee['nin'] ?>)</small>
                    </h5>
                    <div>
                        <a href="<?= route('attendance_export_pdf', ['nin' => $employeeId, 'year' => $currentYear, 'month' => $currentMonth]) ?>" class="btn btn-sm btn-danger"><i class="bi bi-file-earmark-pdf"></i> Exporter PDF</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
                        <div class="col"><div class="card text-center h-100"><div class="card-body"><h4 class="card-title"><?= $summary['worked_days'] ?></h4><p class="card-text small">J. Travaillés</p></div></div></div>
                        <div class="col"><div class="card text-center text-white bg-warning h-100"><div class="card-body"><h4 class="card-title"><?= $summary['absent_justified_paid'] + $summary['absent_justified_unpaid'] ?></h4><p class="card-text small">Abs. Justifiées</p></div></div></div>
                        <div class="col"><div class="card text-center text-white bg-danger h-100"><div class="card-body"><h4 class="card-title"><?= $summary['absent_unjustified'] ?></h4><p class="card-text small">Abs. Non Just.</p></div></div></div>
                        <div class="col"><div class="card text-center h-100"><div class="card-body"><h4 class="card-title"><?= $summary['annual_leave'] ?></h4><p class="card-text small">Congés</p></div></div></div>
                        <div class="col"><div class="card text-center h-100"><div class="card-body"><h4 class="card-title"><?= $summary['sick_leave'] ?></h4><p class="card-text small">Maladie</p></div></div></div>
                    </div>

                    <div class="table-responsive">
                         <table class="table table-bordered table-hover table-sm">
                            <thead class="table-light text-center">
                                <tr><th>Date</th><th>Jour</th><th>Statut</th><th>Entrée</th><th>Sortie</th><th>Notes</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                                for ($day = 1; $day <= $daysInMonth; $day++):
                                    $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                                    $record = $attendance[$currentDate] ?? null;
                                    $dayInfo = getDayInfo($db, $currentDate, $employee, $record);
                                ?>
                                <tr class="<?= $dayInfo['row_class'] ?>">
                                    <td class="text-center"><?= $day ?>/<?= $currentMonth ?></td>
                                    <td class="text-center"><?= $dayInfo['day_name'] ?></td>
                                    <td class="text-center"><span class="badge <?= $dayInfo['badge_class'] ?>"><?= $dayInfo['status_text'] ?></span></td>
                                    <td class="text-center"><?= $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '--:--' ?></td>
                                    <td class="text-center"><?= $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '--:--' ?></td>
                                    <td><small class="text-truncate d-block"><?= htmlspecialchars($record['notes'] ?? '') ?></small></td>
                                    <td class="text-center"><button class="btn btn-sm btn-outline-primary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editModal" data-date="<?= $currentDate ?>" data-record='<?= json_encode($record) ?>'><i class="bi bi-pencil-fill"></i></button></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center" style="padding: 5rem 2rem;">
                    <i class="bi bi-people-fill display-1 text-primary"></i>
                    <h4 class="mt-3">Bienvenue sur le module de gestion des présences</h4>
                    <p class="text-muted">Veuillez sélectionner un employé dans la liste de gauche pour afficher et gérer son journal de présence pour le mois de <?= monthName($currentMonth) ?> <?= $currentYear ?>.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($employeeId): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= route('attendance_log_update') // Assurez-vous que cette route existe pour gérer la mise à jour ?>">
                <?php csrf_input(); ?>
                <input type="hidden" name="nin" value="<?= $employeeId ?>">
                <input type="hidden" name="date" id="editDate">
                <input type="hidden" name="year" value="<?= $currentYear ?>">
                <input type="hidden" name="month" value="<?= $currentMonth ?>">
                <input type="hidden" name="page" value="<?= $page ?>">
                <div class="modal-header"><h5 class="modal-title">Modifier Présence</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Statut</label><select class="form-select" name="status" id="editStatus" required><option value="present">Présent</option><option value="absent_authorized_paid">Absence Justifiée (Payée)</option><option value="absent_authorized_unpaid">Absence Justifiée (Non Payée)</option><option value="absent_unjustified">Absence Non Justifiée</option><option value="sick_leave">Arrêt maladie</option><option value="annual_leave">Congé annuel</option><option value="present_weekend">Travail Weekend</option><option value="present_offday">Travail Jour Férié</option></select></div>
                    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Heure d'arrivée</label><input type="time" class="form-control" name="check_in_time" id="editCheckIn"></div><div class="col-md-6 mb-3"><label class="form-label">Heure de départ</label><input type="time" class="form-control" name="check_out_time" id="editCheckOut"></div></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="editNotes" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const date = button.getAttribute('data-date');
            const record = JSON.parse(button.getAttribute('data-record') || '{}');

            const modal = this;
            modal.querySelector('#editDate').value = date;
            modal.querySelector('.modal-title').textContent = 'Modifier Présence du ' + new Date(date + 'T00:00:00').toLocaleDateString('fr-FR');
            modal.querySelector('#editStatus').value = record.status || 'present';
            modal.querySelector('#editCheckIn').value = record.check_in_time ? record.check_in_time.substring(0, 5) : '';
            modal.querySelector('#editCheckOut').value = record.check_out_time ? record.check_out_time.substring(0, 5) : '';
            modal.querySelector('#editNotes').value = record.notes || '';
        });
    }
});
</script>

<?php include __DIR__.'../../../../includes/footer.php'; ?>