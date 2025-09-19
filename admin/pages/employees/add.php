<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

// Récupérer le régime de travail de l'entreprise depuis company_settings
$company_settings_stmt = $db->query("SELECT work_days_per_week FROM company_settings WHERE id = 1 LIMIT 1");
$company_setting = $company_settings_stmt ? $company_settings_stmt->fetch(PDO::FETCH_ASSOC) : null;
$company_work_days_per_week = $company_setting ? (int)$company_setting['work_days_per_week'] : 5;
$employee_expected_rest_days = 7 - $company_work_days_per_week;

// Définir les jours de repos par défaut de l'entreprise basés sur work_days_per_week
$default_company_rest_days_array = [];
if ($company_work_days_per_week == 5) {
    $default_company_rest_days_array = ['0', '6']; // Dimanche, Samedi
} elseif ($company_work_days_per_week == 6) {
    $default_company_rest_days_array = ['0']; // Dimanche
}

// Récupérer les données pour les formulaires et listes
$departments = $db->query("SELECT id, nom AS name FROM departements ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$positions = $db->query("
    SELECT
        p.id,
        p.nom AS title,
        p.salaire_base AS base_salary,
        d.nom AS department_name
    FROM
        postes p
    LEFT JOIN
        departements d ON p.departement_id = d.id
    ORDER BY
        title ASC
")->fetchAll(PDO::FETCH_ASSOC);
$employees = $db->query("SELECT nin, first_name, last_name FROM employees WHERE status = 'active' ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //$db->beginTransaction();
    try {
        // Valider les champs obligatoires
        $requiredFields = [
            'nin', 'nss', 'first_name', 'last_name', 'gender', 'birth_date',
            'birth_place', 'address', 'city', 'postal_code', 'phone', 'email',
            'marital_status', 'hire_date', 'contract_type', 'position',
            'department', 'salary'
        ];

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ '" . ucfirst(str_replace('_', ' ', $field)) . "' est obligatoire.");
            }
        }
        
        // --- Gérer les données de la période d'essai ---
        $is_trial_period = isset($_POST['is_trial_period']) && $_POST['is_trial_period'] == '1';
        $trial_duration = 0;
        $trial_end_date = null;
        if ($is_trial_period) {
            if (empty($_POST['trial_duration_months']) || !is_numeric($_POST['trial_duration_months']) || $_POST['trial_duration_months'] <= 0) {
                throw new Exception("La durée de la période d'essai est obligatoire et doit être un nombre positif.");
            }
            $trial_duration = (int)$_POST['trial_duration_months'];
            $hire_date_obj = new DateTime(sanitize($_POST['hire_date']));
            $hire_date_obj->add(new DateInterval("P{$trial_duration}M"));
            $trial_end_date = $hire_date_obj->format('Y-m-d');
        }
        
        // --- Gérer les jours de repos ---
        $rest_day_option = sanitize($_POST['rest_day_option'] ?? 'company_default');
        $employee_rest_days_str = '';
        if ($rest_day_option === 'company_default') {
            $employee_rest_days_str = implode(',', $default_company_rest_days_array);
        } else {
            $employee_rest_days_input = $_POST['employee_rest_days'] ?? [];
            if (!is_array($employee_rest_days_input) || count($employee_rest_days_input) === 0) { throw new Exception("Veuillez sélectionner les jours de repos."); }
            if (count($employee_rest_days_input) != $employee_expected_rest_days) { throw new Exception("Veuillez sélectionner exactement " . $employee_expected_rest_days . " jour(s) de repos."); }
            $employee_rest_days_str = implode(',', array_map('sanitize', $employee_rest_days_input));
        }
        
        if ($_POST['contract_type'] !== 'cdi' && empty($_POST['end_date'])) {
            throw new Exception("La date de fin est obligatoire pour les contrats temporaires (non CDI).");
        }

        if (!preg_match('/^\d{18}$/', $_POST['nin'])) { throw new Exception("Le NIN (Numéro d'Identification National) doit contenir 18 chiffres."); }
        if (!preg_match('/^\d{10,12}$/', $_POST['nss'])) { throw new Exception("Le NSS (Numéro de Sécurité Sociale) doit contenir 10 à 12 chiffres."); }

        $nin = sanitize($_POST['nin']);
        $stmt_check_nin = $db->prepare("SELECT nin FROM employees WHERE nin = ?");
        $stmt_check_nin->execute([$nin]);
        if ($stmt_check_nin->fetch()) { throw new Exception("Un employé avec ce NIN existe déjà."); }

        $stmt_check_nss = $db->prepare("SELECT nss FROM employees WHERE nss = ?");
        $stmt_check_nss->execute([sanitize($_POST['nss'])]);
        if ($stmt_check_nss->fetch()) { throw new Exception("Un employé avec ce NSS existe déjà."); }

        $sql = "INSERT INTO employees
            (nin, nss, first_name, last_name, photo_path, gender, birth_date, birth_place,
             address, city, postal_code, phone, email, marital_status, dependents,
             hire_date, end_date, contract_type, position, department, salary,
             bank_name, bank_account, emergency_contact, emergency_phone, status, employee_rest_days,
             on_trial, trial_end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)";
        $stmt_insert = $db->prepare($sql);

        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['photo']['name'])) {
            $photoPath = uploadFile($_FILES['photo'], 'photos_employees');
        }
        
        $hire_date = sanitize($_POST['hire_date']);
        
        $params_insert = [
            $nin, sanitize($_POST['nss']),
            sanitize($_POST['first_name']), sanitize($_POST['last_name']),
            $photoPath, sanitize($_POST['gender']), sanitize($_POST['birth_date']), sanitize($_POST['birth_place']),
            sanitize($_POST['address']), sanitize($_POST['city']), sanitize($_POST['postal_code']), sanitize($_POST['phone']),
            sanitize($_POST['email']), sanitize($_POST['marital_status']), isset($_POST['dependents']) ? (int)$_POST['dependents'] : 0,
            $hire_date, ($_POST['contract_type'] === 'cdi' || empty($_POST['end_date'])) ? null : sanitize($_POST['end_date']),
            sanitize($_POST['contract_type']), sanitize($_POST['position']), sanitize($_POST['department']),
            isset($_POST['salary']) ? (float)$_POST['salary'] : 0.00,
            sanitize($_POST['bank_name'] ?? null), sanitize($_POST['bank_account'] ?? null),
            sanitize($_POST['emergency_contact'] ?? null), sanitize($_POST['emergency_phone'] ?? null),
            $employee_rest_days_str,
            $is_trial_period ? 1 : 0,
            $trial_end_date
        ];
        
        $stmt_insert->execute($params_insert);

        if ($is_trial_period) {
            $stmt_trial = $db->prepare(
                "INSERT INTO trial_periods (employee_nin, start_date, end_date, duration_months, status) VALUES (?, ?, ?, ?, 'active')"
            );
            $stmt_trial->execute([$nin, $hire_date, $trial_end_date, $trial_duration]);
        }
        
        $stmt_pos_history = $db->prepare("INSERT INTO employee_position_history (employee_nin, position_title, department, start_date, change_reason) VALUES (?, ?, ?, ?, ?)");
        $stmt_pos_history->execute([$nin, sanitize($_POST['position']), sanitize($_POST['department']), $hire_date, 'Embauche initiale']);

        $stmt_sal_history = $db->prepare("INSERT INTO employee_salary_history (employee_nin, gross_salary, effective_date, change_type) VALUES (?, ?, ?, ?)");
        $stmt_sal_history->execute([$nin, sanitize($_POST['salary']), $hire_date, 'Salaire initial']);
        
       // $db->commit();
        $_SESSION['success'] = "Employé créé avec succès!";
        header("Location: /admin/index.php?route=employees_view&nin=" . urlencode($nin));
        exit();

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("PDO Error in add_employee: " . $e->getMessage());
        $_SESSION['error'] = $e->errorInfo[1] == 1062 ? "Erreur : Un employé avec ce NIN ou NSS existe déjà." : "Erreur de base de données. Veuillez contacter l'administrateur.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

$pageTitle = "Ajouter un Nouvel Employé";
include __DIR__.'../../../../includes/header.php';
?>


<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Ajouter un Nouvel Employé</h2>
        <a href="<?= APP_LINK ?>/admin/index.php?route=employees_list" class="btn btn-secondary"><i class="bi bi-list-ul"></i> Liste des Employés</a>
    </div>

    <?php //include __DIR__.'../../includes/flash_messages.php'; ?>

    <form method="post" enctype="multipart/form-data" id="employeeForm" novalidate>
        <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="row">
            <div class="col-md-6">
                 <div class="card mb-4">
                     <div class="card-header bg-primary text-white"><h5><i class="bi bi-person-fill"></i> Informations Personnelles</h5></div>
                     <div class="card-body">
                         <div class="mb-3"><label class="form-label">Photo (Optionnel)</label><input type="file" name="photo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/gif"></div>
                         <div class="row">
                             <div class="col-md-6 mb-3">
                                 <label class="form-label">NIN (Numéro d'Identification National)*</label>
                                 <input type="text" name="nin" class="form-control form-control-sm" required pattern="\d{18}" title="Doit contenir 18 chiffres" maxlength="18" value="<?= htmlspecialchars($_POST['nin'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                 <small class="text-muted">Format: 18 chiffres.</small>
                             </div>
                             <div class="col-md-6 mb-3">
                                 <label class="form-label">NSS (Numéro Sécurité Sociale)*</label>
                                 <input type="text" name="nss" class="form-control form-control-sm" required pattern="\d{10,12}" title="Doit contenir 10 à 12 chiffres" maxlength="12" value="<?= htmlspecialchars($_POST['nss'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                  <small class="text-muted">Format: 10 ou 12 chiffres.</small>
                             </div>
                         </div>
                         <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Prénom*</label><input type="text" name="first_name" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required></div><div class="col-md-6 mb-3"><label class="form-label">Nom*</label><input type="text" name="last_name" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required></div></div>
                         <div class="row">
                             <div class="col-md-6 mb-3">
                                 <label class="form-label">Genre*</label>
                                 <select name="gender" class="form-select form-select-sm" required>
                                     <option value="">-- Sélectionner --</option>
                                     <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Masculin</option>
                                     <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Féminin</option>
                                 </select>
                             </div>
                             <div class="col-md-6 mb-3">
                                 <label class="form-label">Date de Naissance*</label>
                                 <input type="date" name="birth_date" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required max="<?= date('Y-m-d', strtotime('-16 years')) ?>">
                                 <small class="text-muted">Âge minimum: 16 ans.</small>
                             </div>
                         </div>
                         <div class="mb-3"><label class="form-label">Lieu de Naissance*</label><input type="text" name="birth_place" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['birth_place'] ?? '') ?>" required></div>
                         <div class="mb-3">
                             <label class="form-label">Situation Familiale*</label>
                             <select name="marital_status" class="form-select form-select-sm" required id="marital_status_select">
                                 <option value="">-- Sélectionner --</option>
                                 <option value="Celibataire" <?= (($_POST['marital_status'] ?? '') === 'Celibataire') ? 'selected' : '' ?>>Célibataire</option>
                                 <option value="Marie" <?= (($_POST['marital_status'] ?? '') === 'Marie') ? 'selected' : '' ?>>Marié(e)</option>
                                 <option value="Divorce" <?= (($_POST['marital_status'] ?? '') === 'Divorce') ? 'selected' : '' ?>>Divorcé(e)</option>
                                 <option value="Veuf" <?= (($_POST['marital_status'] ?? '') === 'Veuf') ? 'selected' : '' ?>>Veuf/Veuve</option>
                             </select>
                         </div>
                         <div class="mb-3" id="dependents_field" style="display:none;"><label class="form-label">Personnes à Charge</label><input type="number" name="dependents" class="form-control form-control-sm" min="0" value="<?= htmlspecialchars($_POST['dependents'] ?? '0') ?>"></div>
                     </div>
                 </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white"> <h5><i class="bi bi-briefcase-fill"></i> Informations Professionnelles</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Date d'Embauche*</label><input type="date" name="hire_date" class="form-control form-control-sm" required id="hireDate" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['hire_date'] ?? '') ?>"></div>
                        <div class="mb-3">
                            <label class="form-label">Type de Contrat*</label>
                            <select name="contract_type" class="form-select form-select-sm" required id="contractType">
                                <option value="">-- Sélectionner --</option>
                                <option value="cdi" <?= (($_POST['contract_type'] ?? '') === 'cdi') ? 'selected' : '' ?>>CDI (Contrat à Durée Indéterminée)</option>
                                <option value="cdd" <?= (($_POST['contract_type'] ?? '') === 'cdd') ? 'selected' : '' ?>>CDD (Contrat à Durée Déterminée)</option>
                                <option value="stage" <?= (($_POST['contract_type'] ?? '') === 'stage') ? 'selected' : '' ?>>Contrat de Stage</option>
                                <option value="interim" <?= (($_POST['contract_type'] ?? '') === 'interim') ? 'selected' : '' ?>>Contrat Intérimaire</option>
                                <option value="essai" <?= (($_POST['contract_type'] ?? '') === 'essai') ? 'selected' : '' ?>>Période d'Essai</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="endDateGroup" style="display: none;">
                            <label class="form-label" for="endDate">Date de Fin de Contrat*</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" id="endDate" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                        </div>

                        <div class="mb-3 form-check border p-3 rounded bg-light">
                            <input type="checkbox" class="form-check-input" id="is_trial_period" name="is_trial_period" value="1" <?php if (isset($_POST['is_trial_period'])) echo 'checked'; ?>>
                            <label class="form-check-label fw-bold" for="is_trial_period">Inclure une période d'essai</label>
                            
                            <div class="mt-2" id="trial_duration_group" style="display: none;">
                                <label for="trial_duration_months" class="form-label">Durée de la Période d'Essai (mois)*</label>
                                <select name="trial_duration_months" id="trial_duration_months" class="form-select form-select-sm">
                                    <option value="3" <?php if (isset($_POST['trial_duration_months']) && $_POST['trial_duration_months'] == 3) echo 'selected'; ?>>3 mois (Employés & Agents de maîtrise)</option>
                                    <option value="6" <?php if (isset($_POST['trial_duration_months']) && $_POST['trial_duration_months'] == 6) echo 'selected'; ?>>6 mois (Cadres)</option>
                                    <option value="12" <?php if (isset($_POST['trial_duration_months']) && $_POST['trial_duration_months'] == 12) echo 'selected'; ?>>12 mois (Cadres supérieurs)</option>
                                </select>
                                <small class="form-text text-muted">Conformément au code du travail algérien.</small>
                            </div>
                        </div>

                        <div class="mb-3"><label class="form-label">Département*</label><select name="department" id="department_select" class="form-select form-select-sm" required><option value="" disabled selected>-- Sélectionner --</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['name']) ?>" <?= (($_POST['department'] ?? '') === $dept['name']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Poste*</label><select name="position" id="position_select" class="form-select form-select-sm" required><option value="" disabled selected>-- D'abord, choisir un département --</option><?php foreach ($positions as $pos): ?><option value="<?= htmlspecialchars($pos['title']) ?>" data-salary="<?= htmlspecialchars($pos['base_salary']) ?>" data-department-name="<?= htmlspecialchars($pos['department_name']) ?>"><?= htmlspecialchars($pos['title']) ?></option><?php endforeach; ?></select></div>
                        
                        <div class="mb-3">
                            <label class="form-label">Jours de Repos*</label>
                            <div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="rest_day_option" id="restDayOptionCompany" value="company_default" checked><label class="form-check-label" for="restDayOptionCompany">Par défaut</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="rest_day_option" id="restDayOptionCustom" value="custom" <?= (($_POST['rest_day_option'] ?? '') === 'custom') ? 'checked' : '' ?>><label class="form-check-label" for="restDayOptionCustom">Personnalisé</label></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jours de Repos de l'Employé*</label>
                            <select name="employee_rest_days[]" id="employee_rest_days_select" class="form-select" multiple required disabled><option value="0">Dimanche</option><option value="1">Lundi</option><option value="2">Mardi</option><option value="3">Mercredi</option><option value="4">Jeudi</option><option value="5">Vendredi</option><option value="6">Samedi</option></select>
                            <small id="employee_rest_days_hint" class="form-text text-muted"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Salaire Brut*</label>
                            <div class="input-group">
                                <input type="number" step="any" name="salary" id="salary_input" class="form-control" value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>" required min="0">
                                <span class="input-group-text">DZD</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                 <div class="card mb-4">
                     <div class="card-header bg-secondary text-white"><h5><i class="bi bi-geo-alt-fill"></i> Coordonnées</h5></div>
                     <div class="card-body">
                         <div class="mb-3"><label class="form-label">Adresse Complète*</label><textarea name="address" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea></div>
                         <div class="row"><div class="col-md-7 mb-3"><label class="form-label">Ville*</label><input type="text" name="city" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required></div><div class="col-md-5 mb-3"><label class="form-label">Code Postal*</label><input type="text" name="postal_code" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>" required></div></div>
                         <div class="mb-3"><label class="form-label">Téléphone Principal*</label><input type="tel" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required></div>
                         <div class="mb-3"><label class="form-label">Email Principal*</label><input type="email" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></div>
                     </div>
                 </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white"><h5><i class="bi bi-info-circle-fill"></i> Informations Supplémentaires (Optionnel)</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Nom de la Banque</label><input type="text" name="bank_name" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label">Numéro de Compte Bancaire</label><input type="text" name="bank_account" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label">Contact d'Urgence (Nom)</label><input type="text" name="emergency_contact" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label">Téléphone d'Urgence</label><input type="tel" name="emergency_phone" class="form-control form-control-sm" value="<?= htmlspecialchars($_POST['emergency_phone'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-3 mb-5">
            <button type="submit" class="btn btn-primary btn-lg px-5"><i class="bi bi-person-plus-fill"></i> Enregistrer</button>
            <a href="<?= APP_LINK ?>/admin/index.php?route=employees_list" class="btn btn-secondary btn-lg px-5"><i class="bi bi-x-circle"></i> Annuler</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const departmentSelect = document.getElementById('department_select');
    const positionSelect = document.getElementById('position_select');
    const salaryInput = document.getElementById('salary_input');
    const contractTypeSelect = document.getElementById('contractType');
    const hireDateInput = document.getElementById('hireDate');
    const endDateGroup = document.getElementById('endDateGroup');
    const endDateInput = document.getElementById('endDate');
    const maritalStatusSelect = document.getElementById('marital_status_select');
    const dependentsField = document.getElementById('dependents_field');
    const restDayOptionCompany = document.getElementById('restDayOptionCompany');
    const restDayOptionCustom = document.getElementById('restDayOptionCustom');
    const employeeRestDaysSelect = document.getElementById('employee_rest_days_select');
    const employeeRestDaysHint = document.getElementById('employee_rest_days_hint');
    const trialCheckbox = document.getElementById('is_trial_period');
    const trialGroup = document.getElementById('trial_duration_group');
    const trialDurationSelect = document.getElementById('trial_duration_months');

    function toggleEndDate() {
        if (contractTypeSelect.value === 'cdi' || contractTypeSelect.value === '') {
            endDateGroup.style.display = 'none';
            endDateInput.required = false;
            endDateInput.value = '';
        } else {
            endDateGroup.style.display = 'block';
            endDateInput.required = true;
        }
    }
    
    function calculateEndDate() {
        if (!hireDateInput.value || contractTypeSelect.value === 'cdi' || contractTypeSelect.value === '') {
            return;
        }

        try {
            let hireDate = new Date(hireDateInput.value);
            if (isNaN(hireDate.getTime())) return;

            let endDate = new Date(hireDate);
            let durationText = "";

            switch(contractTypeSelect.value) {
                case 'cdd':
                    endDate.setFullYear(endDate.getFullYear() + 1);
                    durationText = " (Suggestion: 1 an)";
                    break;
                case 'stage':
                    endDate.setMonth(endDate.getMonth() + 6);
                    durationText = " (Suggestion: 6 mois)";
                    break;
                case 'interim':
                    endDate.setMonth(endDate.getMonth() + 3);
                    durationText = " (Suggestion: 3 mois)";
                    break;
                default:
                    return;
            }

            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');

            if (!endDateInput.value) { // Only set if not already filled
                endDateInput.value = `${year}-${month}-${day}`;
            }
        } catch (e) {
            console.error("Error in calculateEndDate: ", e);
        }
    }
    
    function updateEmployeeRestDaysSelect() {
        const companyWorkDays = <?= json_encode($company_work_days_per_week) ?>;
        const defaultCompanyRestDays = <?= json_encode($default_company_rest_days_array) ?>;
        const expectedRestDays = 7 - companyWorkDays;
        
        // Unselect all options before proceeding
        Array.from(employeeRestDaysSelect.options).forEach(option => { option.selected = false; });
        
        if (restDayOptionCompany.checked) {
            employeeRestDaysSelect.disabled = true;
            defaultCompanyRestDays.forEach(day => {
                const option = employeeRestDaysSelect.querySelector(`option[value="${day}"]`);
                if (option) option.selected = true;
            });
            const selectedDayNames = defaultCompanyRestDays.map(d => {
                const opt = employeeRestDaysSelect.querySelector(`option[value="${d}"]`);
                return opt ? opt.textContent : '';
            });
            employeeRestDaysHint.textContent = `Les jours de repos de l'entreprise sont : ${selectedDayNames.join(', ')}.`;
        } else { // Custom is checked
            employeeRestDaysSelect.disabled = false;
            employeeRestDaysHint.textContent = `Sélectionnez exactement ${expectedRestDays} jour(s) de repos.`;
            <?php
            // If form was submitted with custom days, re-select them
            if (isset($_POST['rest_day_option']) && $_POST['rest_day_option'] === 'custom' && isset($_POST['employee_rest_days'])) {
                echo "var postRestDays = " . json_encode($_POST['employee_rest_days']) . ";\n";
                echo "postRestDays.forEach(day => { const option = employeeRestDaysSelect.querySelector(`option[value='`+day+`']`); if (option) option.selected = true; });\n";
            }
            ?>
        }
    }

    function filterPositions() {
        const selectedDepartmentName = departmentSelect.value;
        let hasVisibleOptions = false;

        positionSelect.value = '';
        salaryInput.value = '';

        for (let i = 1; i < positionSelect.options.length; i++) { // Start at 1 to skip the placeholder
            const option = positionSelect.options[i];
            if (option.dataset.departmentName === selectedDepartmentName) {
                option.style.display = 'block';
                hasVisibleOptions = true;
            } else {
                option.style.display = 'none';
            }
        }
        
        const placeholder = positionSelect.options[0];
        if (hasVisibleOptions) {
            placeholder.textContent = '-- Sélectionner un poste --';
        } else if (selectedDepartmentName) {
             placeholder.textContent = '-- Aucun poste pour ce département --';
        } else {
             placeholder.textContent = '-- D\'abord, choisir un département --';
        }
    }

    function toggleTrialDuration() {
        if (trialCheckbox.checked) {
            trialGroup.style.display = 'block';
            trialDurationSelect.required = true;
        } else {
            trialGroup.style.display = 'none';
            trialDurationSelect.required = false;
        }
    }
    
    function toggleDependentsField() {
        dependentsField.style.display = maritalStatusSelect.value === 'Celibataire' ? 'none' : 'block';
    }

    // --- Event Listeners ---
    departmentSelect.addEventListener('change', filterPositions);
    
    positionSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.salary) {
            salaryInput.value = selectedOption.dataset.salary;
        }
    });
    
    contractTypeSelect.addEventListener('change', () => {
        toggleEndDate();
        calculateEndDate();
    });
    
    hireDateInput.addEventListener('change', calculateEndDate);
    
    restDayOptionCompany.addEventListener('change', updateEmployeeRestDaysSelect);
    restDayOptionCustom.addEventListener('change', updateEmployeeRestDaysSelect);
    
    trialCheckbox.addEventListener('change', toggleTrialDuration);
    
    maritalStatusSelect.addEventListener('change', toggleDependentsField);
    
    // --- Initial Setup Calls on Page Load ---
    toggleEndDate();
    updateEmployeeRestDaysSelect();
    filterPositions();
    toggleTrialDuration();
    toggleDependentsField();
    
    // --- Restore Form State After Server-Side Validation Fail ---
    <?php
    if (isset($_POST['department'])) {
        echo "departmentSelect.value = " . json_encode($_POST['department']) . ";";
        echo "filterPositions();"; // Re-filter positions after setting department
    }
    if (isset($_POST['position'])) {
        // Need a slight delay for the options to become visible after filtering
        echo "setTimeout(() => { positionSelect.value = " . json_encode($_POST['position']) . ";";
        // Dispatch change event to trigger salary auto-fill
        echo "positionSelect.dispatchEvent(new Event('change')); }, 100);";
    }
    ?>
});
</script>

<?php include __DIR__. '../../../../includes/footer.php'; ?>