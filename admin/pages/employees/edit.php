<?php
// --- Security Headers: Set before any output ---

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}



/**
 * Helper function to calculate trial duration in months.
 */
function calculate_trial_months($start_date, $end_date) {
    if (empty($start_date) || empty($end_date)) {
        return 0;
    }
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        return ($interval->y * 12) + $interval->m;
    } catch (Exception $e) {
        return 0; // Return 0 if dates are invalid
    }
}


redirectIfNotHR();

if (!isset($_GET['nin'])) {
    $_SESSION['error'] = "NIN de l'employé manquant.";
    header("Location: " . route('employees_list'));
    exit();
}

$nin_to_edit = sanitize($_GET['nin']);

// Fetch the original employee data before any POST modifications
$stmt_employee = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt_employee->execute([$nin_to_edit]);
$employee_original = $stmt_employee->fetch(PDO::FETCH_ASSOC);

if (!$employee_original) {
    $_SESSION['error'] = "Employé non trouvé.";
    header("Location: " . route('employees_list'));
    exit();
}

// --- Data for Forms & Lists ---
$departments = $db->query("SELECT id, nom AS name FROM departements ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$positions = $db->query("
    SELECT p.id, p.nom AS title, p.salaire_base AS base_salary, d.nom AS department_name
    FROM postes p
    LEFT JOIN departements d ON p.departement_id = d.id
    ORDER BY title ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Company Work Settings ---
$company_settings_stmt = $db->query("SELECT work_days_per_week FROM company_settings WHERE id = 1 LIMIT 1");
$company_setting = $company_settings_stmt->fetch(PDO::FETCH_ASSOC);
$company_work_days_per_week = $company_setting ? (int)$company_setting['work_days_per_week'] : 5;
$employee_expected_rest_days = 7 - $company_work_days_per_week;
$default_company_rest_days_array = ($company_work_days_per_week == 5) ? ['0', '6'] : (($company_work_days_per_week == 6) ? ['0'] : []);

// --- POST Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        $requiredFields = [
            'nss', 'first_name', 'last_name', 'gender', 'birth_date', 'birth_place',
            'address', 'city', 'postal_code', 'phone', 'email', 'marital_status',
            'hire_date', 'contract_type', 'position', 'department', 'salary', 'status'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Le champ '" . ucfirst(str_replace('_', ' ', $field)) . "' est obligatoire.");
            }
        }

        // --- Handle Trial Period ---
        $is_trial_period = isset($_POST['is_trial_period']) && $_POST['is_trial_period'] == '1';
        $trial_end_date = null;
        if ($is_trial_period) {
            if (empty($_POST['trial_duration_months']) || !is_numeric($_POST['trial_duration_months']) || $_POST['trial_duration_months'] <= 0) {
                 throw new Exception("La durée de la période d'essai est obligatoire.");
            }
            $trial_duration = (int)$_POST['trial_duration_months'];
            $hire_date_obj = new DateTime(sanitize($_POST['hire_date']));
            $hire_date_obj->add(new DateInterval("P{$trial_duration}M"));
            $trial_end_date = $hire_date_obj->format('Y-m-d');
        }

        // --- Handle Rest Days ---
        $rest_day_option = sanitize($_POST['rest_day_option'] ?? 'company_default');
        $employee_rest_days_str = '';
        if ($rest_day_option === 'company_default') {
            $employee_rest_days_str = implode(',', $default_company_rest_days_array);
        } else {
            $employee_rest_days_input = $_POST['employee_rest_days'] ?? [];
            if (!is_array($employee_rest_days_input) || count($employee_rest_days_input) != $employee_expected_rest_days) {
                throw new Exception("Veuillez sélectionner exactement " . $employee_expected_rest_days . " jour(s) de repos.");
            }
            $employee_rest_days_str = implode(',', array_map('sanitize', $employee_rest_days_input));
        }
        
        if ($_POST['contract_type'] !== 'cdi' && empty($_POST['end_date'])) throw new Exception("La date de fin est obligatoire pour les contrats non-CDI.");
        if (!preg_match('/^\d{10,12}$/', $_POST['nss'])) throw new Exception("Le NSS doit contenir 10 à 12 chiffres.");

        if (sanitize($_POST['nss']) !== $employee_original['nss']) {
            $stmt_check_nss = $db->prepare("SELECT nss FROM employees WHERE nss = ? AND nin != ?");
            $stmt_check_nss->execute([sanitize($_POST['nss']), $nin_to_edit]);
            if ($stmt_check_nss->fetch()) throw new Exception("Un autre employé utilise déjà ce NSS.");
        }
        
        $photoPath = $employee_original['photo_path'];
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            if (!empty($photoPath) && defined('PROJECT_ROOT') && file_exists(PROJECT_ROOT . $photoPath)) unlink(PROJECT_ROOT . $photoPath);
            $photoPath = null;
        }
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['photo']['name'])) {
            if (!empty($photoPath) && defined('PROJECT_ROOT') && file_exists(PROJECT_ROOT . $photoPath)) unlink(PROJECT_ROOT . $photoPath);
            $photoPath = uploadFile($_FILES['photo'], 'photos_employees');
        }

        $sql_update = "UPDATE employees SET 
            nss=?, first_name=?, last_name=?, photo_path=?, gender=?, birth_date=?, birth_place=?,
            address=?, city=?, postal_code=?, phone=?, email=?, marital_status=?, dependents=?,
            hire_date=?, end_date=?, contract_type=?, position=?, department=?, salary=?,
            bank_name=?, bank_account=?, emergency_contact=?, emergency_phone=?, status=?,
            employee_rest_days=?, on_trial=?, trial_end_date=?
            WHERE nin = ?";
        
        $params_update = [
            sanitize($_POST['nss']), sanitize($_POST['first_name']), sanitize($_POST['last_name']), $photoPath,
            sanitize($_POST['gender']), sanitize($_POST['birth_date']), sanitize($_POST['birth_place']),
            sanitize($_POST['address']), sanitize($_POST['city']), sanitize($_POST['postal_code']),
            sanitize($_POST['phone']), sanitize($_POST['email']), sanitize($_POST['marital_status']),
            isset($_POST['dependents']) ? (int)$_POST['dependents'] : 0,
            sanitize($_POST['hire_date']), ($_POST['contract_type'] === 'cdi' || empty($_POST['end_date'])) ? null : sanitize($_POST['end_date']),
            sanitize($_POST['contract_type']), sanitize($_POST['position']), sanitize($_POST['department']),
            isset($_POST['salary']) ? (float)$_POST['salary'] : 0.00,
            sanitize($_POST['bank_name'] ?? null), sanitize($_POST['bank_account'] ?? null),
            sanitize($_POST['emergency_contact'] ?? null), sanitize($_POST['emergency_phone'] ?? null),
            sanitize($_POST['status']), $employee_rest_days_str, $is_trial_period ? 1 : 0, $trial_end_date,
            $nin_to_edit
        ];
        
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->execute($params_update);

        // --- Log History Changes ---
        $effective_date = date('Y-m-d');
        if (sanitize($_POST['position']) !== $employee_original['position'] || sanitize($_POST['department']) !== $employee_original['department']) {
            $stmt_pos_history = $db->prepare("INSERT INTO employee_position_history (employee_nin, position_title, department, start_date, change_reason) VALUES (?, ?, ?, ?, ?)");
            $stmt_pos_history->execute([$nin_to_edit, sanitize($_POST['position']), sanitize($_POST['department']), $effective_date, 'Mise à jour']);
        }
        if ((float)sanitize($_POST['salary']) !== (float)$employee_original['salary']) {
            $stmt_sal_history = $db->prepare("INSERT INTO employee_salary_history (employee_nin, gross_salary, effective_date, change_type) VALUES (?, ?, ?, ?)");
            $stmt_sal_history->execute([$nin_to_edit, sanitize($_POST['salary']), $effective_date, 'Ajustement']);
        }

        $db->commit();
        $_SESSION['success'] = "Employé mis à jour avec succès!";
        header("Location: " . route('employees_view', ['nin' => $nin_to_edit]));
        exit();

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("PDO Error in edit_employee: " . $e->getMessage());
        $_SESSION['error'] = "Erreur de base de données. " . ($e->errorInfo[1] == 1062 ? "Le NSS existe déjà." : "Veuillez contacter l'administrateur.");
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

// --- Prepare Data for Form Display ---
// If form submission failed, use POST data; otherwise, use original DB data.
$display_data = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $employee_original;

// For multi-selects and checkboxes, they need special handling as they might not be in POST if empty
$current_employee_rest_days_array = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uses_company_default_rest_days = (sanitize($_POST['rest_day_option'] ?? 'company_default') === 'company_default');
    if (!$uses_company_default_rest_days) {
        $current_employee_rest_days_array = $_POST['employee_rest_days'] ?? [];
    }
} else {
    $current_employee_rest_days_array = !empty($employee_original['employee_rest_days']) ? explode(',', $employee_original['employee_rest_days']) : [];
    $sorted_current = $current_employee_rest_days_array; sort($sorted_current);
    $sorted_default = $default_company_rest_days_array; sort($sorted_default);
    $uses_company_default_rest_days = ($sorted_current === $sorted_default);
}
// Handle checkbox state
$is_on_trial_checked = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_on_trial_checked = isset($_POST['is_trial_period']);
} else {
    $is_on_trial_checked = !empty($display_data['on_trial']) && $display_data['on_trial'] == 1;
}


$pageTitle = "Modifier l'Employé : " . htmlspecialchars($employee_original['first_name']) . " " . htmlspecialchars($employee_original['last_name']);
include __DIR__.'../../../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Modifier l'Employé <small class="text-muted">(NIN: <?= htmlspecialchars($employee_original['nin']) ?>)</small></h2>
        <a href="<?= route('employees_view', ['nin' => $nin_to_edit]) ?>" class="btn btn-secondary"><i class="bi bi-eye"></i> Voir Profil</a>
    </div>

    

    <form action="<?= route('employees_edit', ['nin' => $nin_to_edit]) ?>" method="post" enctype="multipart/form-data" id="employeeForm" novalidate>
        <?php csrf_input(); // ✅ Correct: Just call the function here ?>
    <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white"><h5><i class="bi bi-person-fill"></i> Informations Personnelles</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Photo</label>
                            <?php if (!empty($employee_original['photo_path']) && defined('PROJECT_ROOT') && file_exists(PROJECT_ROOT . $employee_original['photo_path'])): ?>
                                <div>
                                    <img src="<?= htmlspecialchars('BASE_URL' . $employee_original['photo_path']) ?>?t=<?= time() ?>" alt="Photo" style="max-height: 100px; border-radius: 5px;" class="mb-2">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label" for="remove_photo">Supprimer la photo actuelle</label></div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Aucune photo actuellement.</p>
                            <?php endif; ?>
                            <input type="file" name="photo" class="form-control form-control-sm mt-2" accept="image/jpeg,image/png,image/gif">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">NIN*</label><input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['nin']) ?>" readonly disabled></div>
                            <div class="col-md-6 mb-3"><label class="form-label">NSS*</label><input type="text" name="nss" class="form-control form-control-sm" required pattern="\d{10,12}" maxlength="12" value="<?= htmlspecialchars($display_data['nss']) ?>"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Prénom*</label><input type="text" name="first_name" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['first_name']) ?>" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Nom*</label><input type="text" name="last_name" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['last_name']) ?>" required></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Genre*</label><select name="gender" class="form-select form-select-sm" required><option value="">-- Sélectionner --</option><option value="male" <?= ($display_data['gender'] === 'male') ? 'selected' : '' ?>>Masculin</option><option value="female" <?= ($display_data['gender'] === 'female') ? 'selected' : '' ?>>Féminin</option></select></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Date de Naissance*</label><input type="date" name="birth_date" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['birth_date']) ?>" required></div>
                        </div>

                        <div class="mb-3"><label class="form-label">Lieu de Naissance*</label><input type="text" name="birth_place" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['birth_place']) ?>" required></div>

                        <div class="mb-3"><label class="form-label">Situation Familiale*</label><select name="marital_status" class="form-select form-select-sm" required id="marital_status_select"><option value="">-- Sélectionner --</option><option value="Celibataire" <?= ($display_data['marital_status'] === 'Celibataire') ? 'selected' : '' ?>>Célibataire</option><option value="Marie" <?= ($display_data['marital_status'] === 'Marie') ? 'selected' : '' ?>>Marié(e)</option><option value="Divorce" <?= ($display_data['marital_status'] === 'Divorce') ? 'selected' : '' ?>>Divorcé(e)</option><option value="Veuf" <?= ($display_data['marital_status'] === 'Veuf') ? 'selected' : '' ?>>Veuf/Veuve</option></select></div>
                        <div class="mb-3" id="dependents_field" style="display:none;"><label class="form-label">Personnes à Charge</label><input type="number" name="dependents" class="form-control form-control-sm" min="0" value="<?= htmlspecialchars($display_data['dependents'] ?? '0') ?>"></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                 <div class="card mb-4">
                    <div class="card-header bg-info text-white"><h5><i class="bi bi-briefcase-fill"></i> Informations Professionnelles</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Date d'Embauche*</label><input type="date" name="hire_date" class="form-control form-control-sm" required id="hireDate" value="<?= htmlspecialchars($display_data['hire_date']) ?>"></div>
                        <div class="mb-3"><label class="form-label">Type de Contrat*</label><select name="contract_type" class="form-select form-select-sm" required id="contractType"><option value="">-- Sélectionner --</option><option value="cdi" <?= ($display_data['contract_type'] === 'cdi') ? 'selected' : '' ?>>CDI</option><option value="cdd" <?= ($display_data['contract_type'] === 'cdd') ? 'selected' : '' ?>>CDD</option><option value="stage" <?= ($display_data['contract_type'] === 'stage') ? 'selected' : '' ?>>Stage</option><option value="interim" <?= ($display_data['contract_type'] === 'interim') ? 'selected' : '' ?>>Intérim</option><option value="essai" <?= ($display_data['contract_type'] === 'essai') ? 'selected' : '' ?>>Période d'Essai</option></select></div>
                        <div class="mb-3" id="endDateGroup" style="display: none;"><label class="form-label" for="endDate">Date de Fin de Contrat*</label><input type="date" name="end_date" class="form-control form-control-sm" id="endDate" value="<?= htmlspecialchars($display_data['end_date'] ?? '') ?>"></div>

                        <div class="mb-3 form-check border p-3 rounded bg-light">
                            <input type="checkbox" class="form-check-input" id="is_trial_period" name="is_trial_period" value="1" <?= $is_on_trial_checked ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="is_trial_period">En période d'essai</label>
                            <div class="mt-2" id="trial_duration_group" style="display: none;">
                                <label for="trial_duration_months" class="form-label">Durée Totale de la Période d'Essai (mois)*</label>
                                <select name="trial_duration_months" id="trial_duration_months" class="form-select form-select-sm">
                                    <option value="3" <?= (calculate_trial_months($display_data['hire_date'], $display_data['trial_end_date'] ?? null) == 3) ? 'selected' : '' ?>>3 mois</option>
                                    <option value="6" <?= (calculate_trial_months($display_data['hire_date'], $display_data['trial_end_date'] ?? null) == 6) ? 'selected' : '' ?>>6 mois</option>
                                    <option value="12" <?= (calculate_trial_months($display_data['hire_date'], $display_data['trial_end_date'] ?? null) == 12) ? 'selected' : '' ?>>12 mois</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3"><label class="form-label">Département*</label><select name="department" id="department_select" class="form-select form-select-sm" required><option value="" disabled>-- Sélectionner --</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['name']) ?>" <?= (isset($display_data['department']) && $display_data['department'] === $dept['name']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Poste*</label><select name="position" id="position_select" class="form-select form-select-sm" required><option value="" disabled>-- Choisir un département --</option><?php foreach ($positions as $pos): ?><option value="<?= htmlspecialchars($pos['title']) ?>" data-salary="<?= htmlspecialchars($pos['base_salary']) ?>" data-department-name="<?= htmlspecialchars($pos['department_name']) ?>" <?= (isset($display_data['position']) && $display_data['position'] === $pos['title']) ? 'selected' : '' ?>><?= htmlspecialchars($pos['title']) ?></option><?php endforeach; ?></select></div>

                        <div class="mb-3"><label class="form-label">Jours de Repos*</label><div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="rest_day_option" id="restDayOptionCompany" value="company_default" <?= $uses_company_default_rest_days ? 'checked' : '' ?>><label class="form-check-label" for="restDayOptionCompany">Par défaut</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="rest_day_option" id="restDayOptionCustom" value="custom" <?= !$uses_company_default_rest_days ? 'checked' : '' ?>><label class="form-check-label" for="restDayOptionCustom">Personnalisé</label></div></div></div>
                        <div class="mb-3"><select name="employee_rest_days[]" id="employee_rest_days_select" class="form-select" multiple required><option value="0">Dimanche</option><option value="1">Lundi</option><option value="2">Mardi</option><option value="3">Mercredi</option><option value="4">Jeudi</option><option value="5">Vendredi</option><option value="6">Samedi</option></select><small id="employee_rest_days_hint" class="form-text text-muted"></small></div>

                        <div class="mb-3"><label class="form-label">Salaire Brut*</label><div class="input-group"><input type="number" step="any" name="salary" id="salary_input" class="form-control" value="<?= htmlspecialchars($display_data['salary']) ?>" required min="0"><span class="input-group-text">DZD</span></div></div>
                        <div class="mb-3"><label class="form-label">Statut*</label><select name="status" class="form-select form-select-sm" required><option value="">-- Sélectionner --</option><option value="active" <?= ($display_data['status'] === 'active') ? 'selected' : '' ?>>Actif</option><option value="inactive" <?= ($display_data['status'] === 'inactive') ? 'selected' : '' ?>>Inactif</option><option value="suspended" <?= ($display_data['status'] === 'suspended') ? 'selected' : '' ?>>Suspendu</option><option value="cancelled" <?= ($display_data['status'] === 'cancelled') ? 'selected' : '' ?>>Résilié</option></select></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                 <div class="card mb-4">
                      <div class="card-header bg-secondary text-white"><h5><i class="bi bi-geo-alt-fill"></i> Coordonnées</h5></div>
                      <div class="card-body">
                           <div class="mb-3"><label class="form-label">Adresse Complète*</label><textarea name="address" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($display_data['address']) ?></textarea></div>
                           <div class="row"><div class="col-md-7 mb-3"><label class="form-label">Ville*</label><input type="text" name="city" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['city']) ?>" required></div><div class="col-md-5 mb-3"><label class="form-label">Code Postal*</label><input type="text" name="postal_code" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['postal_code']) ?>" required></div></div>
                           <div class="mb-3"><label class="form-label">Téléphone*</label><input type="tel" name="phone" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['phone']) ?>" required></div>
                           <div class="mb-3"><label class="form-label">Email*</label><input type="email" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['email']) ?>" required></div>
                      </div>
                 </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white"><h5><i class="bi bi-info-circle-fill"></i> Informations Supplémentaires</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Nom de la Banque</label><input type="text" name="bank_name" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['bank_name'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label">Numéro de Compte Bancaire</label><input type="text" name="bank_account" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['bank_account'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label">Contact d'Urgence (Nom)</label><input type="text" name="emergency_contact" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['emergency_contact'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label">Téléphone d'Urgence</label><input type="tel" name="emergency_phone" class="form-control form-control-sm" value="<?= htmlspecialchars($display_data['emergency_phone'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-3 mb-5">
            <button type="submit" class="btn btn-success btn-lg px-5"><i class="bi bi-save-fill"></i> Mettre à Jour</button>
            <a href="<?= route('employees_view', ['nin' => $nin_to_edit]) ?>" class="btn btn-secondary btn-lg px-5"><i class="bi bi-x-circle"></i> Annuler</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_select');
    const positionSelect = document.getElementById('position_select');
    const salaryInput = document.getElementById('salary_input');
    const contractTypeSelect = document.getElementById('contractType');
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
        } else {
            endDateGroup.style.display = 'block';
            endDateInput.required = true;
        }
    }

    function filterPositions() {
        const selectedDepartmentName = departmentSelect.value;
        let hasVisibleOptions = false;
        
        const isUserAction = this.dataset.userAction === 'true';
        if (isUserAction) {
           positionSelect.value = '';
        }

        for (let i = 0; i < positionSelect.options.length; i++) {
            const option = positionSelect.options[i];
            if (option.value === "" || option.dataset.departmentName === selectedDepartmentName) {
                option.style.display = 'block';
                if(option.value !== "") hasVisibleOptions = true;
            } else {
                option.style.display = 'none';
            }
        }
        
        positionSelect.options[0].textContent = hasVisibleOptions ? '-- Sélectionner un poste --' : (selectedDepartmentName ? '-- Aucun poste --' : '-- Choisir un département --');
        this.dataset.userAction = 'true'; // Mark that interaction has occurred
    }
    
    function updateEmployeeRestDaysSelect() {
        const defaultCompanyRestDays = <?= json_encode($default_company_rest_days_array) ?>;
        const companyWorkDays = <?= json_encode($company_work_days_per_week) ?>;
        const expectedRestDays = 7 - companyWorkDays;
        
        const daysToSelect = <?= json_encode($current_employee_rest_days_array) ?>;
        
        Array.from(employeeRestDaysSelect.options).forEach(option => { option.selected = false; });
        
        if (restDayOptionCompany.checked) {
            employeeRestDaysSelect.disabled = true;
            defaultCompanyRestDays.forEach(day => {
                const option = employeeRestDaysSelect.querySelector(`option[value="${day}"]`);
                if (option) option.selected = true;
            });
            employeeRestDaysHint.textContent = `Les jours de repos de l'entreprise sont : ` + defaultCompanyRestDays.map(d => employeeRestDaysSelect.querySelector(`option[value="${d}"]`).textContent).join(', ') + '.';
        } else { // Custom
            employeeRestDaysSelect.disabled = false;
            if(daysToSelect.length > 0) {
                 daysToSelect.forEach(day => {
                     const option = employeeRestDaysSelect.querySelector(`option[value='${day}']`);
                     if(option) option.selected = true;
                 });
            }
            employeeRestDaysHint.textContent = `Sélectionnez exactement ${expectedRestDays} jour(s). (Ctrl/Cmd + clic)`;
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
        dependentsField.style.display = maritalStatusSelect.value === 'Celibataire' || maritalStatusSelect.value === '' ? 'none' : 'block';
    }

    // --- Event Listeners ---
    departmentSelect.addEventListener('change', filterPositions);
    positionSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption && selectedOption.dataset.salary && departmentSelect.dataset.userAction === 'true') {
            salaryInput.value = selectedOption.dataset.salary;
        }
    });
    contractTypeSelect.addEventListener('change', toggleEndDate);
    restDayOptionCompany.addEventListener('change', updateEmployeeRestDaysSelect);
    restDayOptionCustom.addEventListener('change', updateEmployeeRestDaysSelect);
    trialCheckbox.addEventListener('change', toggleTrialDuration);
    maritalStatusSelect.addEventListener('change', toggleDependentsField);
    
    // --- Initial Setup Calls on Page Load ---
    filterPositions.call(departmentSelect);
    toggleEndDate();
    updateEmployeeRestDaysSelect();
    toggleTrialDuration();
    toggleDependentsField();

    departmentSelect.dataset.userAction = 'false';
    departmentSelect.addEventListener('focus', () => { departmentSelect.dataset.userAction = 'true'; }, { once: true });
});
</script>

<?php include __DIR__. '../../../../includes/footer.php'; ?>