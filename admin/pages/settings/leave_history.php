<?php
/**
 * Page: Company Settings
 *
 * Manages all global settings for the company, including legal information,
 * HR policies, SMTP, and organizational structure.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotAdminOrHR();

$pageTitle = "Historique Correction Soldes des congés";
include '../../includes/header.php';

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? null;
$nin = $_GET['nin'] ?? null;
?>

<div class="container-fluid py-4">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="bi bi-clock-history"></i> Historique correction Soldes des congés</h3>
                <div>
                    <button class="btn btn-light btn-sm" onclick="printReport()">
                        <i class="bi bi-printer"></i> Imprimer
                    </button>
                    <button class="btn btn-light btn-sm ms-2" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Excel
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Filter Form -->
            <form method="get" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Année</label>
                        <select name="year" class="form-select">
                            <?php for ($y = date('Y') - 1; $y <= date('Y') + 5; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mois</label>
                        <select name="month" class="form-select">
                            <option value="">Tous les mois</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                                    <?= DateTime::createFromFormat('!m', $m)->format('F') ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employé (NIN)</label>
                        <input type="text" name="nin" class="form-control" placeholder="NIN de l'employé" value="<?= htmlspecialchars($nin) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Filtrer
                        </button>
                    </div>
                </div>
            </form>

            <!-- History Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="historyTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Employé</th>
                            <th>Type</th>
                            <th>Période</th>
                            <th>Jours</th>
                            <th>Ancien solde</th>
                            <th>Nouveau solde</th>
                            <th>Effectué par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT h.*, e.first_name, e.last_name 
                                 FROM leave_balance_history h
                                 JOIN employees e ON h.employee_nin = e.nin
                                 WHERE h.leave_year = ?";
                        $params = [$year];
                        
                        if ($month) {
                            $query .= " AND h.month = ?";
                            $params[] = $month;
                        }
                        
                        if ($nin) {
                            $query .= " AND h.employee_nin = ?";
                            $params[] = $nin;
                        }
                        
                        $query .= " ORDER BY h.operation_date DESC";
                        
                        $stmt = $db->prepare($query);
                        $stmt->execute($params);
                        $history = $stmt->fetchAll();
                        
                        foreach ($history as $record):
                        ?>
                        <tr>
                            <td><?= formatDate($record['operation_date']) ?></td>
                            <td>
                                <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                <div class="small text-muted">NIN: <?= $record['employee_nin'] ?></div>
                            </td>
                            <td>
                                <?php 
                                $badgeClass = [
                                    'system' => 'bg-primary',
                                    'manual' => 'bg-success',
                                    'correction' => 'bg-warning'
                                ][$record['operation_type']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= ucfirst($record['operation_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?= DateTime::createFromFormat('!m', $record['month'])->format('F') ?> <?= $record['leave_year'] ?>
                            </td>
                            <td class="fw-bold <?= $record['days_added'] > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $record['days_added'] > 0 ? '+' : '' ?><?= $record['days_added'] ?>
                            </td>
                            <td><?= $record['previous_balance'] ?></td>
                            <td><?= $record['new_balance'] ?></td>
                            <td><?= htmlspecialchars($record['performed_by']) ?></td>
                            <td>
                                <?php if (isAdmin() && $record['operation_type'] !== 'system'): ?>
                                   <a href="/admin/leave/adjust_leave.php?nin=<?= urlencode($record['employee_nin']) ?>"
   class="btn btn-sm btn-outline-secondary"
   title="Ajuster le congé">
    <i class="bi bi-pencil"></i> Modifier
</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Adjustment Modal -->
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'ajout de congés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adjustmentForm" method="post" action="../../api/adjust_leave.php">
               <?php csrf_input(); // ✅ Correct: Just call the function here ?>
            <div class="modal-body">
                    <input type="hidden" name="history_id" id="historyId">
                    <input type="hidden" name="employee_nin" id="employeeNin">
                    
                    <div class="mb-3">
                        <label class="form-label">Employé</label>
                        <input type="text" class="form-control" id="employeeName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jours à ajuster</label>
                        <input type="number" step="0.5" min="-30" max="30" 
                               class="form-control" name="days_change" id="daysChange" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Raison de l'ajustement</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show adjustment form
function showAdjustmentForm(nin, name, historyId, currentDays, notes) {
    document.getElementById('employeeNin').value = nin;
    document.getElementById('employeeName').value = name;
    document.getElementById('historyId').value = historyId;
    document.getElementById('daysChange').value = currentDays;
    
    const modal = new bootstrap.Modal(document.getElementById('adjustmentModal'));
    modal.show();
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('historyTable');
    const html = table.outerHTML;
    
    // Create download link
    const blob = new Blob([html], {type: 'application/vnd.ms-excel'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `historique_conges_${new Date().toISOString().slice(0,10)}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Print report
function printReport() {
    window.print();
}

// Handle form submission
document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    fetch(this.action, {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Ajustement enregistré avec succès');
            window.location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erreur de connexion');
    });
});
</script>

<?php include '../../includes/footer.php'; ?>