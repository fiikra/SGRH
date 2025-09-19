<?php
// /admin/pages/model/EmployeeModel.php

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('No direct access allowed');
}

class EmployeeModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getEmployeeByNin($nin) {
        $stmt = $this->db->prepare("SELECT *, DATEDIFF(trial_end_date, CURDATE()) as trial_days_left FROM employees WHERE nin = ?");
        $stmt->execute([$nin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNavigationData($nin) {
        $sql = "
            WITH RankedEmployees AS (
                SELECT
                    nin,
                    first_name,
                    last_name,
                    LAG(nin) OVER (ORDER BY last_name, first_name) as prev_nin,
                    LAG(CONCAT(first_name, ' ', last_name)) OVER (ORDER BY last_name, first_name) as prev_name,
                    LEAD(nin) OVER (ORDER BY last_name, first_name) as next_nin,
                    LEAD(CONCAT(first_name, ' ', last_name)) OVER (ORDER BY last_name, first_name) as next_name
                FROM
                    employees
            )
            SELECT prev_nin, prev_name, next_nin, next_name
            FROM RankedEmployees
            WHERE nin = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$nin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getRecuperationBalance($nin) {
        $stmt = $this->db->prepare("SELECT SUM(nb_jours) as current_recup_balance FROM employee_recup_days WHERE employee_nin = ? AND status = 'not_taked'");
        $stmt->execute([$nin]);
        return $stmt->fetchColumn() ?? 0;
    }

    public function getEmployeeDocuments($nin) {
        $stmt = $this->db->prepare("SELECT * FROM employee_documents WHERE employee_nin = ? ORDER BY upload_date DESC");
        $stmt->execute([$nin]);
        return $stmt;
    }

    public function getLeaveRequests($nin, $leave_types) {
        $placeholders = implode(',', array_fill(0, count($leave_types), '?'));
        $sql = "SELECT * FROM leave_requests WHERE employee_nin = ? AND leave_type IN ($placeholders) ORDER BY start_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$nin], $leave_types));
        return $stmt;
    }

    public function getSickLeaveRequests($nin) {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE employee_nin = ? AND leave_type IN ('sick', 'maladie') ORDER BY start_date DESC");
        $stmt->execute([$nin]);
        return $stmt;
    }

    public function getMaternityLeaveRequests($nin) {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE employee_nin = ? AND leave_type = 'maternity' ORDER BY start_date DESC");
        $stmt->execute([$nin]);
        return $stmt;
    }

    public function getSanctions($nin) {
        $stmt = $this->db->prepare("SELECT s.*, q.reference_number as questionnaire_ref FROM employee_sanctions s LEFT JOIN employee_questionnaires q ON s.questionnaire_id = q.id WHERE s.employee_nin = ? ORDER BY s.sanction_date DESC");
        $stmt->execute([$nin]);
        return $stmt;
    }

    public function getQuestionnaires($nin) {
        $stmt = $this->db->prepare("SELECT * FROM employee_questionnaires WHERE employee_nin = ? ORDER BY issue_date DESC");
        $stmt->execute([$nin]);
        return $stmt;
    }
    
    public function getAvailableQuestionnairesForSanction($nin) {
        $sql = "SELECT q.id, q.reference_number, q.issue_date, q.questionnaire_type
                FROM employee_questionnaires q
                LEFT JOIN employee_sanctions s ON q.id = s.questionnaire_id
                WHERE q.employee_nin = ? AND s.id IS NULL AND q.status = 'closed' AND q.questionnaire_type = 'Entretien préalable à une sanction'
                ORDER BY q.issue_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$nin]);
        return $stmt;
    }

    public function getFormationsHistory($nin) {
        $sql = "SELECT f.id, f.title, f.trainer_name, f.start_date, f.end_date, f.status AS formation_status, fp.status AS participant_status
                FROM formation_participants fp
                JOIN formations f ON fp.formation_id = f.id
                WHERE fp.employee_nin = ? ORDER BY f.start_date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$nin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getRecentCertificates($nin, $limit = 5) {
        $stmt = $this->db->prepare("SELECT c.*, u.username as prepared_by FROM certificates c
                                     LEFT JOIN users u ON c.prepared_by = u.id
                                     WHERE c.employee_nin = ? AND c.certificate_type IN ('Attestation', 'Attestation_sold','Certficate')
                                     ORDER BY c.issue_date DESC LIMIT ?");
        $stmt->bindValue(1, $nin);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function getAttendanceRecords($nin, $month_str) {
        $sql = "SELECT attendance_date, status, notes, leave_type_if_absent, is_weekend_work, is_holiday_work,
                       check_in_time, check_out_time, effective_work_hours, overtime_hours_recorded
                FROM employee_attendance
                WHERE employee_nin = :nin AND DATE_FORMAT(attendance_date, '%Y-%m') = :month
                ORDER BY attendance_date ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nin' => $nin, ':month' => $month_str]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAttendanceMonthRange($nin) {
         $sql = "SELECT MIN(DATE_FORMAT(attendance_date, '%Y-%m')) AS earliest_month,
                        MAX(DATE_FORMAT(attendance_date, '%Y-%m')) AS latest_month
                   FROM employee_attendance
                   WHERE employee_nin = :nin";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nin' => $nin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMonthlyFinancialSummary($nin, $year, $month) {
        $stmt = $this->db->prepare(
            "SELECT total_hs_hours, total_retenu_hours FROM employee_monthly_financial_summary 
             WHERE employee_nin = :nin AND period_year = :year AND period_month = :month"
        );
        $stmt->execute([':nin' => $nin, ':year' => $year, ':month' => $month]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCareerDecisions($nin) {
        $stmt = $this->db->prepare("SELECT * FROM promotion_decisions WHERE employee_nin = ? ORDER BY effective_date DESC");
        $stmt->execute([$nin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPositionHistory($nin) {
        $stmt = $this->db->prepare("SELECT * FROM employee_position_history WHERE employee_nin = ? ORDER BY start_date DESC");
        $stmt->execute([$nin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalaryHistory($nin) {
        $stmt = $this->db->prepare("SELECT * FROM employee_salary_history WHERE employee_nin = ? ORDER BY effective_date DESC");
        $stmt->execute([$nin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTrialNotifications($nin) {
        $stmt = $this->db->prepare("SELECT * FROM trial_notifications WHERE employee_nin = ? ORDER BY issue_date DESC");
        $stmt->execute([$nin]);
        return $stmt;
    }

   
    
     public function getPositionsList() {
        $sql = "SELECT 
                    p.nom AS title, 
                    p.salaire_base AS base_salary, 
                    d.nom AS department_name
                FROM 
                    postes p
                LEFT JOIN 
                    departements d ON p.departement_id = d.id
                ORDER BY 
                    title ASC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

      public function getDepartmentsList() {
        $stmt = $this->db->query("SELECT nom AS name FROM departements ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getContractTypes() {
        return $this->db->query("SELECT types_contrat FROM personnel_settings LIMIT 1")->fetchColumn();
    }


   

    /**
     * Fetches company-wide settings like work days.
     */
    public function getCompanyWorkSettings() {
        return $this->db->query("SELECT work_days_per_week FROM company_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Checks if a NIN already exists in the database.
     */
    public function checkNinExists($nin) {
        $stmt = $this->db->prepare("SELECT nin FROM employees WHERE nin = ?");
        $stmt->execute([$nin]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Checks if a NSS already exists for a different employee.
     */
    public function checkNssExists($nss, $excludeNin = null) {
        $sql = "SELECT nss FROM employees WHERE nss = ?";
        $params = [$nss];
        if ($excludeNin) {
            $sql .= " AND nin != ?";
            $params[] = $excludeNin;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Creates a new employee and their initial history records in a transaction.
     */
    public function createEmployee($data) {
       // $this->db->beginTransaction();
        
        $sql = "INSERT INTO employees
            (nin, nss, first_name, last_name, photo_path, gender, birth_date, birth_place,
             address, city, postal_code, phone, email, marital_status, dependents,
             hire_date, end_date, contract_type, position, department, salary,
             bank_name, bank_account, emergency_contact, emergency_phone, status, employee_rest_days,
             on_trial, trial_end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data['employee_data']);

        // Log initial position and salary
        $this->logPositionHistory($data['nin'], $data['position'], $data['department'], $data['hire_date'], 'Embauche initiale');
        $this->logSalaryHistory($data['nin'], $data['salary'], $data['hire_date'], 'Salaire initial');
        
       // $this->db->commit();
        return true;
    }

    /**
     * Updates an existing employee and logs changes to history tables in a transaction.
     */
    public function updateEmployee($nin, $data, $originalData) {
        //$this->db->beginTransaction();

        $sql_update = "UPDATE employees SET 
            nss=?, first_name=?, last_name=?, photo_path=?, gender=?, birth_date=?, birth_place=?,
            address=?, city=?, postal_code=?, phone=?, email=?, marital_status=?, dependents=?,
            hire_date=?, end_date=?, contract_type=?, position=?, department=?, salary=?,
            bank_name=?, bank_account=?, emergency_contact=?, emergency_phone=?, status=?,
            employee_rest_days=?, on_trial=?, trial_end_date=?
            WHERE nin = ?";
        
        $params = array_merge($data, [$nin]);
        $stmt_update = $this->db->prepare($sql_update);
        $stmt_update->execute($params);

        $effective_date = date('Y-m-d');
        // Log changes if they occurred
        if ($data[17] !== $originalData['position'] || $data[18] !== $originalData['department']) {
            $this->logPositionHistory($nin, $data[17], $data[18], $effective_date, 'Mise à jour');
        }
        if ((float)$data[19] !== (float)$originalData['salary']) {
            $this->logSalaryHistory($nin, $data[19], $effective_date, 'Ajustement');
        }

        //$this->db->commit();
        return true;
    }

    /**
     * Deletes an employee and all their related data in a transaction.
     */
    public function deleteEmployee($nin) {
        $this->db->beginTransaction();

        // You might want to get file paths here to delete files, or handle that in the controller
        $this->db->prepare("DELETE FROM employee_documents WHERE employee_nin = ?")->execute([$nin]);
        $this->db->prepare("DELETE FROM leave_requests WHERE employee_nin = ?")->execute([$nin]);
        $this->db->prepare("DELETE FROM certificates WHERE employee_nin = ?")->execute([$nin]);
        $this->db->prepare("DELETE FROM employee_position_history WHERE employee_nin = ?")->execute([$nin]);
        $this->db->prepare("DELETE FROM employee_salary_history WHERE employee_nin = ?")->execute([$nin]);
        // Add other related tables here...

        // Finally, delete the employee
        $this->db->prepare("DELETE FROM employees WHERE nin = ?")->execute([$nin]);

        $this->db->commit();
        return true;
    }
    
    /**
     * Gets a filtered and paginated list of employees.
     */
    public function getEmployeesFiltered($filters, $perPage, $offset) {
        $query = "SELECT * FROM employees WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $searchTerm = "%{$filters['search']}%";
            $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR nin LIKE ? OR nss LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        if (!empty($filters['status'])) { $query .= " AND status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['department'])) { $query .= " AND department = ?"; $params[] = $filters['department']; }
        // ... Add all other filters from list.php here ...
        
        // Get total count with filters for pagination
        $totalQuery = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
        $totalStmt = $this->db->prepare($totalQuery);
        $totalStmt->execute($params);
        $total = $totalStmt->fetchColumn();

        // Get paginated results
        $query .= " ORDER BY last_name ASC, first_name ASC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($query);
        // We must bind LIMIT/OFFSET params as integers
        foreach($params as $key => &$val) {
            if(is_int($val) || is_bool($val)) {
                $stmt->bindParam($key + 1, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindParam($key + 1, $val, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['employees' => $employees, 'total' => $total];
    }

    /**
     * Gets a list of distinct values for a given column (for filter dropdowns).
     */
    public function getDistinct($column) {
        $stmt = $this->db->query("SELECT DISTINCT $column FROM employees WHERE $column IS NOT NULL AND $column != '' ORDER BY $column ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Manages an employee's departure.
     */
    public function processEmployeeDeparture($nin, $departure_date, $reason_to_store) {
        $sql = "UPDATE employees SET status = 'inactive', departure_date = ?, departure_reason = ? WHERE nin = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$departure_date, $reason_to_store, $nin]);
    }
    
    /**
     * Adds a document for an employee.
     */
    public function addDocument($data) {
        $sql = "INSERT INTO employee_documents (employee_nin, document_type, title, file_path, issue_date, expiry_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Deletes a specific document.
     */
    public function deleteDocument($id, $nin) {
        $stmt = $this->db->prepare("DELETE FROM employee_documents WHERE id = ? AND employee_nin = ?");
        return $stmt->execute([$id, $nin]);
    }

    /**
     * Fetches a single document's path for file deletion.
     */
    public function getDocumentFilePath($id) {
        $stmt = $this->db->prepare("SELECT file_path FROM employee_documents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }
    
    // --- PRIVATE HELPER METHODS ---
    private function logPositionHistory($nin, $position, $department, $date, $reason) {
        $stmt = $this->db->prepare("INSERT INTO employee_position_history (employee_nin, position_title, department, start_date, change_reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nin, $position, $department, $date, $reason]);
    }

    private function logSalaryHistory($nin, $salary, $date, $reason) {
        $stmt = $this->db->prepare("INSERT INTO employee_salary_history (employee_nin, gross_salary, effective_date, change_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nin, $salary, $date, $reason]);
    }


    public function renewContract($nin, $new_end_date, $current_position, $current_department) {
        // L'opération entière doit être transactionnelle pour garantir l'intégrité des données
        $this->db->beginTransaction();
        try {
            // Mettre à jour la date de fin dans la table principale des employés
            $sql = "UPDATE employees SET end_date = ? WHERE nin = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$new_end_date, $nin]);

            // Enregistrer un enregistrement dans l'historique pour la traçabilité
            $this->logPositionHistory(
                $nin, 
                $current_position, 
                $current_department, 
                date('Y-m-d'), // La date du changement est aujourd'hui
                'Renouvellement de contrat jusqu\'au ' . formatDate($new_end_date)
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            // Propager l'exception pour que le contrôleur puisse la gérer
            throw $e;
        }
    }

    /**
     * Fait passer un contrat d'employé de CDD à CDI.
     * Met à jour le type de contrat, annule la date de fin et enregistre l'événement.
     */
    public function changeContractToCdi($nin, $effective_date, $position, $department) {
        $this->db->beginTransaction();
        try {
            $sql = "UPDATE employees SET contract_type = 'cdi', end_date = NULL WHERE nin = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nin]);

            $this->logPositionHistory($nin, $position, $department, $effective_date, 'Passage en CDI');
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Réintègre un employé inactif.
     * Met à jour le statut, la date d'embauche et efface les informations de départ.
     */
    public function reintegrateEmployee($nin, $new_hire_date, $position, $department) {
        $this->db->beginTransaction();
        try {
            $sql = "UPDATE employees SET 
                        status = 'active', 
                        hire_date = ?,
                        departure_date = NULL, 
                        departure_reason = NULL 
                    WHERE nin = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$new_hire_date, $nin]);

            $this->logPositionHistory($nin, $position, $department, $new_hire_date, 'Réintégration');
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


      public function getCompanySettings() {
        // [FIX] Added address, phone, and email to the query
        $sql = "SELECT company_name, logo_path, address, phone, email FROM company_settings LIMIT 1";
        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

}
