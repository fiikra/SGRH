<?php

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}
redirectIfNotHR();

// Fetch employees who are active and DO NOT have a contract record yet
$employees = $db->query("
    SELECT e.nin, e.first_name, e.last_name
    FROM employees e
    LEFT JOIN contrats c ON e.nin = c.employe_nin
    WHERE e.status = 'active' AND c.id IS NULL
    ORDER BY e.last_name ASC, e.first_name ASC
")->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['employe_nin'])) {
        $_SESSION['error'] = "Veuillez sélectionner un employé.";
        header("Location: " . route('contracts_add'));
        exit();
    }
    
    $nin = sanitize($_POST['employe_nin']);
    
    $db->beginTransaction();
    try {
        $stmt_emp = $db->prepare("SELECT * FROM employees WHERE nin = ? LIMIT 1");
        $stmt_emp->execute([$nin]);
        $employee_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);

        if (!$employee_data) throw new Exception("Employé non trouvé.");
        
        $periode_essai_jours = 0;
        if ($employee_data['on_trial'] == 1 && !empty($employee_data['trial_end_date'])) {
            $interval = (new DateTime($employee_data['hire_date']))->diff(new DateTime($employee_data['trial_end_date']));
            $periode_essai_jours = $interval->days;
        }

        $params = [
            'employe_nin'         => $employee_data['nin'],
            'type_contrat'        => $employee_data['contract_type'],
            'date_debut'          => $employee_data['hire_date'],
            'date_fin'            => $employee_data['end_date'],
            'periode_essai_jours' => $periode_essai_jours > 0 ? $periode_essai_jours : null,
            'salaire_brut'        => $employee_data['salary'],
            'poste'               => $employee_data['position']
        ];
        
        $sql = "INSERT INTO contrats (employe_nin, type_contrat, date_debut, date_fin, periode_essai_jours, salaire_brut, poste) 
                VALUES (:employe_nin, :type_contrat, :date_debut, :date_fin, :periode_essai_jours, :salaire_brut, :poste)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $db->commit();
        $_SESSION['success'] = "Le contrat pour " . htmlspecialchars($employee_data['first_name']) . " a été généré avec succès !";
        header("Location: " . route('contracts_index'));
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

$pageTitle = "Générer un Contrat";
include __DIR__. '../../../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Générer un Contrat Automatiquement</h2>
        <a href="<?= route('contracts_index') ?>" class="btn btn-secondary"><i class="bi bi-list-ul"></i> Voir les Contrats</a>
    </div>

   

    <div class="card">
        <div class="card-header bg-primary text-white"><h5><i class="bi bi-person-check-fill me-2"></i>Sélectionner un Employé</h5></div>
        <div class="card-body">
            <p>Choisissez un employé. Le système créera son contrat en utilisant ses informations existantes (poste, salaire, dates, etc.).</p>
            
            <?php if (empty($employees)): ?>
                <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Tous les employés actifs ont déjà un contrat.</div>
            <?php else: ?>
                <form method="post" action="<?= route('contracts_add') ?>">
                   <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                <div class="mb-3">
                        <label for="employe_nin" class="form-label">Employés sans contrat*</label>
                        <select class="form-select form-select-lg" id="employe_nin" name="employe_nin" required>
                            <option value="" disabled selected>-- Sélectionner un employé --</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= htmlspecialchars($employee['nin']) ?>">
                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i> Le contrat sera créé immédiatement avec les données actuelles de l'employé.</div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-file-earmark-check-fill me-2"></i>Générer et Enregistrer</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__. '../../../../includes/footer.php'; ?>