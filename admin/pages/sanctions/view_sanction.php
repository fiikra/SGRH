<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Input Validation ---
$sanction_id = ($_GET['id'] ?? 0);
if ($sanction_id <= 0) {
    flash('error', 'ID de sanction manquant ou invalide.');
    header("Location: " . route('dashboard')); // Redirect to a safe default page
    exit;
}

// --- Data Fetching ---
try {
    $stmt = $db->prepare("
        SELECT 
            s.*, 
            e.first_name, e.last_name, e.position,
            q.status as questionnaire_status,
            q.reference_number as questionnaire_ref
        FROM employee_sanctions s
        JOIN employees e ON s.employee_nin = e.nin
        LEFT JOIN employee_questionnaires q ON s.questionnaire_id = q.id
        WHERE s.reference_number = ?
    ");
    $stmt->execute([$sanction_id]);
    $sanction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sanction) {
        throw new Exception('Sanction non trouvée.');
    }
} catch (Exception $e) {
    flash('error', $e->getMessage());
    header("Location: " . route('employees_list')); // Redirect if sanction not found
    exit;
}

// --- Helper Functions for Display ---
function getSanctionDetails($type) {
    $labels = [
        'avertissement_verbal' => ['label' => 'Avertissement Verbal', 'badge' => 'secondary'],
        'avertissement_ecrit' => ['label' => 'Avertissement Écrit (1er degré)', 'badge' => 'warning text-dark'],
        'mise_a_pied_1' => ['label' => 'Mise à pied 1 jour (2e degré)', 'badge' => 'danger'],
        'mise_a_pied_2' => ['label' => 'Mise à pied 2 jours (2e degré)', 'badge' => 'danger'],
        'mise_a_pied_3' => ['label' => 'Mise à pied 3 jours (2e degré)', 'badge' => 'danger'],
        'licenciement' => ['label' => 'Licenciement (3e degré)', 'badge' => 'dark']
    ];
    return $labels[$type] ?? ['label' => ucfirst($type), 'badge' => 'light'];
}

function getQuestionnaireStatusBadge($status) {
    $badges = ['pending_response' => 'warning', 'responded' => 'info', 'decision_made' => 'primary', 'closed' => 'success'];
    $labels = ['pending_response' => 'En attente', 'responded' => 'Répondu', 'decision_made' => 'Décision prise', 'closed' => 'Clôturé'];
    return "<span class='badge bg-".($badges[$status] ?? 'secondary')."'>".($labels[$status] ?? 'Inconnu')."</span>";
}

$sanctionDetails = getSanctionDetails($sanction['sanction_type']);
$pageTitle = "Détails de la Sanction: " . htmlspecialchars($sanction['reference_number']);
include __DIR__ . '/../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0"><?= $pageTitle ?></h1>
        <a href="<?= route('employees_view', ['nin' => $sanction['employee_nin']]) ?>#sanctions" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour au Profil
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Détails Complets</h5>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Employé</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($sanction['first_name'] . ' ' . $sanction['last_name']) ?></dd>

                <dt class="col-sm-3">Poste</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($sanction['position']) ?></dd>
                
                <hr class="my-3">

                <dt class="col-sm-3">Type de Sanction</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-<?= $sanctionDetails['badge'] ?> fs-6"><?= $sanctionDetails['label'] ?></span>
                </dd>

                <dt class="col-sm-3">Date de la Sanction</dt>
                <dd class="col-sm-9"><?= formatDate($sanction['sanction_date']) ?></dd>
                
                <hr class="my-3">

                <dt class="col-sm-3">Motif Détaillé</dt>
                <dd class="col-sm-9">
                    <div class="p-3 bg-light border rounded">
                        <?= nl2br(htmlspecialchars($sanction['reason'])) ?>
                    </div>
                </dd>

                <?php if ($sanction['questionnaire_id']): ?>
                    <hr class="my-3">
                    <dt class="col-sm-3">Entretien Préalable</dt>
                    <dd class="col-sm-9">
                        <p class="mb-1">
                            Lié au questionnaire : 
                            <a href="<?= route('questionnaires_view_questionnaire', ['id' => $sanction['questionnaire_id']]) ?>">
                                <strong><?= htmlspecialchars($sanction['questionnaire_ref'] ?? 'Voir Questionnaire') ?></strong>
                            </a>
                        </p>
                        <p class="mb-0">
                            Statut : <?= getQuestionnaireStatusBadge($sanction['questionnaire_status']) ?>
                        </p>
                    </dd>
                <?php endif; ?>
            </dl>
        </div>
        <div class="card-footer text-end">
            <a href="<?= route('sanctions_generate_notification_pdf', ['ref' => $sanction['reference_number']]) ?>" class="btn btn-danger" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill"></i> Générer la Notification PDF
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>