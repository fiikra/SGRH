<?php
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

redirectIfNotHR();

require_once __DIR__ . '../../../model/employees/EmployeeModel.php';
$employeeModel = new EmployeeModel($db);

// --- POST REQUEST HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //$db->beginTransaction();
    try {
        // --- Field Validation ---
        $requiredFields = ['nin', 'nss', 'first_name', 'last_name', 'gender', 'birth_date', 'birth_place', 'address', 'city', 'postal_code', 'phone', 'email', 'marital_status', 'hire_date', 'contract_type', 'position', 'department', 'salary'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ '" . ucfirst(str_replace('_', ' ', $field)) . "' est obligatoire.");
            }
        }

        // --- Business Logic Validation ---
        if ($employeeModel->checkNinExists(sanitize($_POST['nin']))) {
            throw new Exception("Un employé avec ce NIN existe déjà.");
        }
        if ($employeeModel->checkNssExists(sanitize($_POST['nss']))) {
            throw new Exception("Un employé avec ce NSS existe déjà.");
        }
        if (!preg_match('/^\d{18}$/', $_POST['nin'])) {
            throw new Exception("Le NIN (Numéro d'Identification National) doit contenir 18 chiffres.");
        }
        if (!preg_match('/^\d{10,12}$/', $_POST['nss'])) {
            throw new Exception("Le NSS (Numéro de Sécurité Sociale) doit contenir 10 à 12 chiffres.");
        }
        if ($_POST['contract_type'] !== 'cdi' && empty($_POST['end_date'])) {
            throw new Exception("La date de fin est obligatoire pour les contrats temporaires (non CDI).");
        }
        
        // --- Prepare Data for Model ---
        $company_setting = $employeeModel->getCompanyWorkSettings();
        $company_work_days_per_week = $company_setting ? (int)$company_setting['work_days_per_week'] : 5;
        $employee_expected_rest_days = 7 - $company_work_days_per_week;

        $is_trial_period = isset($_POST['is_trial_period']) && $_POST['is_trial_period'] == '1';
        $trial_end_date = null;
        if ($is_trial_period) {
            if (empty($_POST['trial_duration_months']) || !is_numeric($_POST['trial_duration_months'])) {
                throw new Exception("La durée de la période d'essai est obligatoire.");
            }
            $trial_duration = (int)$_POST['trial_duration_months'];
            $hire_date_obj = new DateTime(sanitize($_POST['hire_date']));
            $hire_date_obj->add(new DateInterval("P{$trial_duration}M"));
            $trial_end_date = $hire_date_obj->format('Y-m-d');
        }

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

        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['photo']['name'])) {
            $photoPath = uploadFile($_FILES['photo'], 'photos_employees');
        }
        
        $employee_data_for_insert = [
            sanitize($_POST['nin']), sanitize($_POST['nss']), sanitize($_POST['first_name']), sanitize($_POST['last_name']),
            $photoPath, sanitize($_POST['gender']), sanitize($_POST['birth_date']), sanitize($_POST['birth_place']),
            sanitize($_POST['address']), sanitize($_POST['city']), sanitize($_POST['postal_code']), sanitize($_POST['phone']),
            sanitize($_POST['email']), sanitize($_POST['marital_status']), isset($_POST['dependents']) ? (int)$_POST['dependents'] : 0,
            sanitize($_POST['hire_date']), ($_POST['contract_type'] === 'cdi' || empty($_POST['end_date'])) ? null : sanitize($_POST['end_date']),
            sanitize($_POST['contract_type']), sanitize($_POST['position']), sanitize($_POST['department']),
            (float)$_POST['salary'], sanitize($_POST['bank_name'] ?? null), sanitize($_POST['bank_account'] ?? null),
            sanitize($_POST['emergency_contact'] ?? null), sanitize($_POST['emergency_phone'] ?? null),
            $employee_rest_days_str, $is_trial_period ? 1 : 0, $trial_end_date
        ];

        $full_data = [
            'nin' => sanitize($_POST['nin']),
            'position' => sanitize($_POST['position']),
            'department' => sanitize($_POST['department']),
            'salary' => (float)$_POST['salary'],
            'hire_date' => sanitize($_POST['hire_date']),
            'employee_data' => $employee_data_for_insert
        ];

        // --- Call Model to Create Employee ---
        $employeeModel->createEmployee($full_data);
        
        //$db->commit();
        $_SESSION['success'] = "Employé créé avec succès!";
        header("Location: " . route('employees_view', ['nin' => $full_data['nin']]));
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
}

// --- DATA PREPARATION FOR THE VIEW (GET Request or Failed POST) ---
$pageTitle = "Ajouter un Nouvel Employé";
$departments = $employeeModel->getDepartmentsList();
$positions = $employeeModel->getPositionsList();
$company_setting = $employeeModel->getCompanyWorkSettings();
$company_work_days_per_week = $company_setting ? (int)$company_setting['work_days_per_week'] : 5;
$employee_expected_rest_days = 7 - $company_work_days_per_week;
$default_company_rest_days_array = ($company_work_days_per_week == 5) ? ['0', '6'] : ['0'];

// --- Load The View ---
include __DIR__ . '/../../views/employees/add.php';