<?php

// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');

redirectIfNotHR();

require_once __DIR__ . '../../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

// Helper function to calculate trial duration for display
if (!function_exists('calculate_trial_months')) {
    function calculate_trial_months($start_date, $end_date) {
        if (empty($start_date) || empty($end_date)) return 0;
        try {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            return ($interval->y * 12) + $interval->m;
        } catch (Exception $e) { return 0; }
    }
}

// --- INITIAL DATA FETCH ---
if (!isset($_GET['nin'])) {
    $_SESSION['error'] = "Employee NIN is missing.";
    header("Location: " . route('employees_list'));
    exit();
}
$nin_to_edit = sanitize($_GET['nin']);
$employee_original = $employeeModel->getEmployeeByNin($nin_to_edit);
if (!$employee_original) {
    $_SESSION['error'] = "Employee not found.";
    header("Location: " . route('employees_list'));
    exit();
}

// --- POST REQUEST HANDLING   update Employees ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        // --- Validation ---
        $requiredFields = ['nss', 'first_name', 'last_name', 'gender', 'birth_date', 'birth_place', 'address', 'city', 'postal_code', 'phone', 'email', 'marital_status', 'hire_date', 'contract_type', 'position', 'department', 'salary', 'status'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Le champ '" . ucfirst(str_replace('_', ' ', $field)) . "' est obligatoire.");
            }
        }
        if (sanitize($_POST['nss']) !== $employee_original['nss']) {
            if ($employeeModel->checkNssExists(sanitize($_POST['nss']), $nin_to_edit)) {
                throw new Exception("Un autre employé utilise déjà ce NSS.");
            }
        }
        // ... (other validation from original file) ...
        
        // --- Prepare Data ---
        $is_trial_period = isset($_POST['is_trial_period']) && $_POST['is_trial_period'] == '1';
        $trial_end_date = null;
        if ($is_trial_period) {
            $trial_duration = (int)$_POST['trial_duration_months'];
            $hire_date_obj = new DateTime(sanitize($_POST['hire_date']));
            $hire_date_obj->add(new DateInterval("P{$trial_duration}M"));
            $trial_end_date = $hire_date_obj->format('Y-m-d');
        }

        $company_setting = $employeeModel->getCompanyWorkSettings();
        $company_work_days_per_week = $company_setting ? (int)$company_setting['work_days_per_week'] : 5;
        $employee_expected_rest_days = 7 - $company_work_days_per_week;
        $rest_day_option = sanitize($_POST['rest_day_option'] ?? 'company_default');
        if ($rest_day_option === 'company_default') {
            $default_days = ($company_work_days_per_week == 5) ? ['0', '6'] : ['0'];
            $employee_rest_days_str = implode(',', $default_days);
        } else {
            $employee_rest_days_input = $_POST['employee_rest_days'] ?? [];
            if (count($employee_rest_days_input) != $employee_expected_rest_days) {
                throw new Exception("Veuillez sélectionner exactement " . $employee_expected_rest_days . " jour(s) de repos.");
            }
            $employee_rest_days_str = implode(',', array_map('sanitize', $employee_rest_days_input));
        }
        
        $photoPath = $employee_original['photo_path'];
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
            if (!empty($photoPath) && file_exists(PROJECT_ROOT . '/' . $photoPath)) unlink(PROJECT_ROOT . '/' . $photoPath);
            $photoPath = null;
        }
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['photo']['name'])) {
            if (!empty($photoPath) && file_exists(PROJECT_ROOT . '/' . $photoPath)) unlink(PROJECT_ROOT . '/' . $photoPath);
            $photoPath = uploadFile($_FILES['photo'], 'photos_employees');
        }

        $params_update = [
            sanitize($_POST['nss']), sanitize($_POST['first_name']), sanitize($_POST['last_name']), $photoPath,
            sanitize($_POST['gender']), sanitize($_POST['birth_date']), sanitize($_POST['birth_place']),
            sanitize($_POST['address']), sanitize($_POST['city']), sanitize($_POST['postal_code']),
            sanitize($_POST['phone']), sanitize($_POST['email']), sanitize($_POST['marital_status']),
            isset($_POST['dependents']) ? (int)$_POST['dependents'] : 0, sanitize($_POST['hire_date']),
            ($_POST['contract_type'] === 'cdi' || empty($_POST['end_date'])) ? null : sanitize($_POST['end_date']),
            sanitize($_POST['contract_type']), sanitize($_POST['position']), sanitize($_POST['department']),
            (float)$_POST['salary'], sanitize($_POST['bank_name'] ?? null), sanitize($_POST['bank_account'] ?? null),
            sanitize($_POST['emergency_contact'] ?? null), sanitize($_POST['emergency_phone'] ?? null),
            sanitize($_POST['status']), $employee_rest_days_str, $is_trial_period ? 1 : 0, $trial_end_date
        ];
        
        // --- Call Model to Update ---
        $employeeModel->updateEmployee($nin_to_edit, $params_update, $employee_original);

        $db->commit();
        $_SESSION['success'] = "Employé mis à jour avec succès!";
        header("Location: " . route('employees_view', ['nin' => $nin_to_edit]));
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}


// --- POST REQUEST HANDLING    bascule de contrat CDD to CDI /  reintegration poste ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'general_update';

        if ($action === 'change_to_cdi') {
            // --- Logique pour le passage en CDI ---
            $effective_date = sanitize($_POST['effective_date']);
            if (empty($effective_date)) {
                throw new Exception("La date d'effet est obligatoire.");
            }
            $employeeModel->changeContractToCdi($nin_to_edit, $effective_date, $employee_original['position'], $employee_original['department']);
            $_SESSION['success'] = "Le contrat de l'employé a été changé en CDI avec succès.";

        } elseif ($action === 'reintegrate') {
            // --- Logique pour la réintégration ---
            $new_hire_date = sanitize($_POST['new_hire_date']);
            if (empty($new_hire_date)) {
                throw new Exception("La nouvelle date d'embauche est obligatoire.");
            }
            $employeeModel->reintegrateEmployee($nin_to_edit, $new_hire_date, $employee_original['position'], $employee_original['department']);
            $_SESSION['success'] = "L'employé a été réintégré avec succès.";

        } else {
            // --- Logique de mise à jour générale (votre code existant) ---
            // On encapsule l'ancienne logique dans ce "else"
            $db->beginTransaction(); // La transaction est maintenant ici
            
            // ... (Toute votre validation et préparation de données pour la mise à jour générale)
            // $requiredFields = [...];
            // $params_update = [...];
            
            $employeeModel->updateEmployee($nin_to_edit, $params_update, $employee_original);

            $db->commit();
            $_SESSION['success'] = "Employé mis à jour avec succès!";
        }

        // Redirection en cas de succès pour n'importe quelle action
        header("Location: " . route('employees_view', ['nin' => $nin_to_edit]));
        exit();

    } catch (Exception $e) {
        // La gestion d'erreur reste la même, mais on vérifie si une transaction a été ouverte
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

// --- DATA PREPARATION FOR THE VIEW ---
$pageTitle = "Modifier l'Employé : " . htmlspecialchars($employee_original['first_name']) . " " . htmlspecialchars($employee_original['last_name']);
$display_data = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $employee_original;
$departments = $employeeModel->getDepartmentsList();
$positions = $employeeModel->getPositionsList();

$company_setting = $employeeModel->getCompanyWorkSettings();
$company_work_days_per_week = $company_setting ? (int)$company_setting['work_days_per_week'] : 5;
$employee_expected_rest_days = 7 - $company_work_days_per_week;
$default_company_rest_days_array = ($company_work_days_per_week == 5) ? ['0', '6'] : ['0'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uses_company_default_rest_days = (sanitize($_POST['rest_day_option'] ?? 'company_default') === 'company_default');
    $current_employee_rest_days_array = $uses_company_default_rest_days ? [] : ($_POST['employee_rest_days'] ?? []);
    $is_on_trial_checked = isset($_POST['is_trial_period']);
} else {
    $current_employee_rest_days_array = !empty($employee_original['employee_rest_days']) ? explode(',', $employee_original['employee_rest_days']) : [];
    $sorted_current = $current_employee_rest_days_array; sort($sorted_current);
    $sorted_default = $default_company_rest_days_array; sort($sorted_default);
    $uses_company_default_rest_days = ($sorted_current === $sorted_default);
    $is_on_trial_checked = !empty($display_data['on_trial']);
}

// --- Load The View ---
include __DIR__ . '/../../views/employees/edit.php';