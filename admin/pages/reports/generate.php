<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}
redirectIfNotHR();

// --- Data Fetching ---
if (!isset($_GET['nin'])) {
    flash('error', 'Aucun employé sélectionné.');
    header("Location: " . route('employees_list'));
    exit();
}
$nin = sanitize($_GET['nin']);

$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    flash('error', "Employé non trouvé.");
    header("Location: " . route('employees_list'));
    exit();
}

$company = $db->query("SELECT * FROM company_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$issuerStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$issuerStmt->execute([$_SESSION['user_id']]);
$issuer = $issuerStmt->fetch(PDO::FETCH_ASSOC);

// Helper function to generate default content for the textarea
function get_certificate_content($type, $employee, $company, $civilite) {
    $fullname = "<b>" . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . "</b>";
    $birthDate = "<b>" . formatDate($employee['birth_date']) . "</b>";
    $birthPlace = "<b>" . htmlspecialchars($employee['birth_place']) . "</b>";
    $hireDate = "<b>" . formatDate($employee['hire_date']) . "</b>";
    $companyName = "<b>" . htmlspecialchars($company['company_name']) . "</b>";
    $poste = "<b>" . htmlspecialchars($employee['position']) . "</b>";
    $closingPhrase = "\nLa présente attestation lui est délivrée à sa demande pour servir et valoir ce que de droit.";
    
    if ($type === 'Attestation') {
        return "Nous soussignés, $companyName, certifions que $civilite $fullname, né(e) le $birthDate à $birthPlace, est employé(e) dans notre société en qualité de $poste depuis le $hireDate à ce jour." . $closingPhrase;
    }
    if ($type === 'Attestation_sold') {
        $salary = "<b>" . number_format($employee['salary'], 2, ',', ' ') . " " . htmlspecialchars($company['currency']) . "</b>";
        return "Nous soussignés, $companyName, certifions que $civilite $fullname, occupe le poste de $poste.\nSa rémunération brute mensuelle est de $salary." . $closingPhrase;
    }
    if ($type === 'Certficate') {
        $endDate = "<b>" . formatDate($employee['end_date']) . "</b>";
        return "Nous soussignés, $companyName, certifions que $civilite $fullname, a été employé(e) du $hireDate au $endDate.\nL'intéressé(e) quitte notre entreprise libre de tout engagement." . $closingPhrase;
    }
    return '';
}

$civilite = (strtolower($employee['gender'] ?? 'male') === 'female') ? 'Mme.' : 'M.';
$pageTitle = "Générer Attestation / Certificat";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Générer un Document</h1>
        <a href="<?= route('reports_history') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour à l'Historique
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Pour : <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
        </div>
        <div class="card-body">
            <form method="post" action="<?= route('reports_generate_handler') ?>" target="_blank">
                <?php csrf_input(); ?>
                <input type="hidden" name="employee_nin" value="<?= htmlspecialchars($nin) ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="type_select">Type de Document*</label>
                        <select name="type" class="form-select" required id="type_select">
                            <option value="Attestation">Attestation de Travail</option>
                            <option value="Attestation_sold">Attestation de Salaire</option>
                            <?php if (strtolower($employee['status'] ?? 'active') !== 'active'): ?>
                            <option value="Certficate">Certificat de Travail</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Généré par</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($issuer['username'] ?? 'Système') ?>" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label" for="content">Contenu du Document*</label>
                    <textarea name="content" class="form-control" rows="12" required id="content"></textarea>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Générer le PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const defaultContents = {
        Attestation: <?= json_encode(get_certificate_content('Attestation', $employee, $company, $civilite)) ?>,
        Attestation_sold: <?= json_encode(get_certificate_content('Attestation_sold', $employee, $company, $civilite)) ?>,
        Certficate: <?= json_encode(get_certificate_content('Certficate', $employee, $company, $civilite)) ?>
    };

    const typeSelect = document.getElementById('type_select');
    const contentTextarea = document.getElementById('content');

    function updateContent() {
        const selectedType = typeSelect.value;
        contentTextarea.value = defaultContents[selectedType] || '';
    }

    typeSelect.addEventListener('change', updateContent);
    updateContent(); // Set initial content on page load
});
</script>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>