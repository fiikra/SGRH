<?php
// --- Security Headers: Set before any output ---

redirectIfNotLoggedIn();



// Vérification de la structure de la base de données
$requiredColumns = [
    'leave_requests' => ['leave_year', 'use_remaining'],
    'employees' => ['remaining_leave_balance']
];

foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        $check = $db->query("SHOW COLUMNS FROM $table LIKE '$column'")->fetch();
        if (!$check) {
            die("Erreur de configuration: La colonne $column manque dans la table $table");
        }
    }
}




// Récupérer les informations complètes de l'employé
$stmt = $db->prepare("SELECT e.*, u.* FROM employees e
                     JOIN users u ON e.user_id = u.id
                     WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Profil employé non trouvé";
    header("Location: /auth/logout.php");
    exit();
}

// Vérifier l'ancienneté (au moins 6 mois)
$hireDate = new DateTime($employee['hire_date']);
$now = new DateTime();
$seniority = $hireDate->diff($now);
$hasLeaveRight = ($seniority->y >= 0) || ($seniority->m >= 1 && $seniority->d >= 15);

// Récupérer les soldes
$remainingAnnualLeave = $employee['annual_leave_balance'];
$remainingFromPrevious = $employee['remaining_leave_balance'];
$totalAvailable = $remainingAnnualLeave + $remainingFromPrevious;

// Envoyer une demande de congé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $startDate = sanitize($_POST['start_date']);
        $endDate = sanitize($_POST['end_date']);
        $leaveType = sanitize($_POST['leave_type']);
        $leaveYear = sanitize($_POST['leave_year']); // Nouveau champ pour spécifier l'année de congé

        // Validation de base
        if (empty($startDate)) throw new Exception("La date de début est requise");
        if (empty($endDate)) throw new Exception("La date de fin est requise");
        if (strtotime($endDate) < strtotime($startDate)) throw new Exception("Date de fin invalide");

        // Calcul des jours demandés
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        $daysRequested = $interval->days + 1;

        // Vérification ancienneté
        if (!$hasLeaveRight) {
            throw new Exception("Vous devez avoir au moins 6 mois d'ancienneté pour demander des congés");
        }

        // Traitement différent selon le type de congé
        if ($leaveType === 'annuel') {
            // Vérifier si la demande utilise des reliquats ou des congés courants
            $useRemaining = ($leaveYear < getCurrentLeaveYear());
            
            if ($useRemaining) {
                // Utilisation des reliquats
                if ($daysRequested > $remainingFromPrevious) {
                    throw new Exception("Solde de reliquats insuffisant ($remainingFromPrevious jours disponibles)");
                }
            } else {
                // Utilisation des congés courants
                if ($daysRequested > $remainingAnnualLeave) {
                    throw new Exception("Solde annuel insuffisant ($remainingAnnualLeave jours disponibles)");
                }
            }

            // Vérifier les chevauchements
            $checkOverlap = $db->prepare("SELECT COUNT(*) FROM leave_requests
                                         WHERE employee_nin = ? 
                                         AND leave_type = 'annuel'
                                         AND status IN ('pending', 'approved')
                                         AND (
                                             (start_date <= ? AND end_date >= ?) OR
                                             (start_date <= ? AND end_date >= ?) OR
                                             (start_date >= ? AND end_date <= ?)
                                         )");
            $checkOverlap->execute([
                $employee['nin'],
                $startDate, $startDate,
                $endDate, $endDate,
                $startDate, $endDate
            ]);

            if ($checkOverlap->fetchColumn() > 0) {
                throw new Exception("Vous avez déjà une demande qui chevauche cette période");
            }
        }

        // Validation du motif
        if (empty($_POST['reason'])) {
            throw new Exception("Le motif est requis");
        }

        // Enregistrement de la demande
        // Modifiez la partie d'insertion dans votre code (vers la ligne 143) :
$stmt = $db->prepare("INSERT INTO leave_requests
                     (employee_nin, leave_type, start_date, end_date, days_requested, 
                      reason, status, leave_year, use_remaining)
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)");

$currentLeaveYear = getCurrentLeaveYear();
$useRemaining = ($leaveType === 'annuel' && isset($_POST['leave_year']) && $_POST['leave_year'] < $currentLeaveYear) ? 1 : 0;
$selectedYear = $useRemaining ? $_POST['leave_year'] : $currentLeaveYear;

$stmt->execute([
    $employee['nin'],
    $leaveType,
    $startDate,
    $endDate,
    $daysRequested,
    sanitize($_POST['reason']),
    $selectedYear,
    $useRemaining
]);

        $_SESSION['success'] = "Demande envoyée avec succès";
        header("Location: leave.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les demandes existantes
$leavesStmt = $db->prepare("SELECT *, 
                           CASE 
                               WHEN leave_year < ? THEN CONCAT('Reliquat ', leave_year)
                               ELSE CONCAT('Exercice ', leave_year)
                           END as leave_period
                           FROM leave_requests
                           WHERE employee_nin = ?
                           ORDER BY created_at DESC");
$leavesStmt->execute([getCurrentLeaveYear(), $employee['nin']]);

$pageTitle = "Demande de Congé";
include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Demande de Congé</h1>

    <?php if (!$hasLeaveRight): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            Vous devez avoir au moins 6 mois d'ancienneté pour demander des congés annuels.
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Nouvelle Demande</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                    <div class="mb-3">
                            <label class="form-label">Type de Congé*</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="annuel">Congé Annuel</option>
                                <option value="maladie">Maladie</option>
                                <option value="maternite">Maternité</option>
                                <option value="unpaid">Sans solde</option>
                            </select>
                        </div>

                        <div id="annual_leave_fields">
                            <div class="mb-3">
                                <label class="form-label">Période de congé*</label>
                                <select name="leave_year" class="form-select">
                                    <option value="<?= getCurrentLeaveYear() ?>">
                                        Exercice <?= getCurrentLeaveYear() ?> (<?= $remainingAnnualLeave ?> jours)
                                    </option>
                                    <?php if ($remainingFromPrevious > 0): ?>
                                        <option value="<?= getCurrentLeaveYear() - 1 ?>">
                                            Reliquat <?= getCurrentLeaveYear() - 1 ?> (<?= $remainingFromPrevious ?> jours)
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="alert alert-info">
                                Solde total disponible: <?= $totalAvailable ?> jours
                                (<?= $remainingAnnualLeave ?> jours exercice + <?= $remainingFromPrevious ?> jours reliquats)
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date Début*</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date Fin*</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motif*</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" <?= !$hasLeaveRight ? 'disabled' : '' ?>>
                            <i class="bi bi-send"></i> Envoyer la demande
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Historique des Demandes</h5>
                </div>
                <div class="card-body">
                    <?php if ($leavesStmt->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Période</th>
                                        <th>Dates</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($leave = $leavesStmt->fetch()): ?>
                                        <tr>
                                            <td><?= ucfirst($leave['leave_type']) ?></td>
                                            <td><?= $leave['leave_period'] ?></td>
                                            <td>
                                                <?= formatDate($leave['start_date']) ?> - 
                                                <?= formatDate($leave['end_date']) ?>
                                                (<?= $leave['days_requested'] ?>j)
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $leave['status'] === 'approved' ? 'success' :
                                                    ($leave['status'] === 'rejected' ? 'danger' : 'warning')
                                                ?>">
                                                    <?= ucfirst($leave['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Aucune demande de congé trouvée.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Définir la date de début par défaut (aujourd'hui)
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="start_date"]').value = today;
    document.querySelector('input[name="end_date"]').value = today;

    // Masquer/afficher les champs spécifiques aux congés annuels
    const leaveTypeSelect = document.querySelector('select[name="leave_type"]');
    const annualFields = document.getElementById('annual_leave_fields');
    
    function toggleAnnualFields() {
        annualFields.style.display = leaveTypeSelect.value === 'annuel' ? 'block' : 'none';
    }
    
    leaveTypeSelect.addEventListener('change', toggleAnnualFields);
    toggleAnnualFields(); // Initialisation
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>