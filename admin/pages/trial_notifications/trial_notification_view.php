<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Input Validation ---
$ref = sanitize($_GET['ref'] ?? '');
if (empty($ref)) {
    flash('error', 'Référence de notification non valide.');
    header("Location: " . route('trial_notifications_index'));
    exit;
}

// --- Data Fetching ---
try {
    $stmt = $db->prepare("
        SELECT n.*, e.first_name, e.last_name, e.gender, e.position
        FROM trial_notifications n 
        JOIN employees e ON n.employee_nin = e.nin 
        WHERE n.reference_number = ?
    ");
    $stmt->execute([$ref]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception('Notification non trouvée.');
    }
} catch (Exception $e) {
    flash('error', $e->getMessage());
    header("Location: " . route('trial_notifications_index'));
    exit;
}

// --- Helper function for display text ---
function getDecisionDetails($decision) {
    switch ($decision) {
        case 'confirm':
            return [
                'objet' => "Confirmation au poste",
                'summary' => "La période d'essai de l'employé(e) a été confirmée avec succès."
            ];
        case 'renew':
            return [
                'objet' => "Renouvellement de la période d’essai",
                'summary' => "La période d'essai de l'employé(e) a été renouvelée."
            ];
        case 'terminate':
            return [
                'objet' => "Fin de contrat (Période d’essai non concluante)",
                'summary' => "Le contrat de l'employé(e) a été terminé à l'issue de la période d'essai."
            ];
        default:
            return ['objet' => 'Notification', 'summary' => 'Notification relative à la période d\'essai.'];
    }
}

$details = getDecisionDetails($data['decision']);
$gender = (strtolower($data['gender']) === 'female') ? 'Madame' : 'Monsieur';
$pageTitle = "Détail Notification: " . htmlspecialchars($ref);
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Détails de la Notification</h1>
        <a href="<?= route('employees_view', ['nin' => $data['employee_nin']]) ?>#notifications" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour au Profil
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Référence : <?= htmlspecialchars($ref) ?></h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Date d'émission</dt>
                <dd class="col-sm-9"><?= formatDate($data['issue_date'], 'd/m/Y') ?></dd>

                <dt class="col-sm-3">Employé(e)</dt>
                <dd class="col-sm-9"><?= $gender ?> <?= htmlspecialchars($data['first_name'] . ' ' . $data['last_name']) ?></dd>

                <dt class="col-sm-3">Poste</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($data['position']) ?></dd>

                <dt class="col-sm-3">Objet</dt>
                <dd class="col-sm-9"><strong><?= htmlspecialchars($details['objet']) ?></strong></dd>
            </dl>
            <hr>
            <p class="lead">Résumé de la décision :</p>
            <div class="p-3 bg-light rounded border">
                <p class="mb-0"><?= htmlspecialchars($details['summary']) ?></p>
                <?php if ($data['decision'] === 'renew' && !empty($data['renew_period'])): ?>
                    <p class="mb-0 mt-2"><strong>Durée du renouvellement :</strong> <?= htmlspecialchars($data['renew_period']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="<?= route('trial_notifications_generate_notification_pdf', ['ref' => $ref]) ?>" class="btn btn-danger" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill"></i> Voir le PDF 
            </a>
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>