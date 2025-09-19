<?php
class ManageAttendanceModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupère les enregistrements de présence filtrés pour un mois donné.
     */
    public function getFilteredAttendanceRecords(int $year, int $month, string $employeeNin = ''): array {
        $monthForSql = sprintf('%04d-%02d', $year, $month);
        
        $sql = "SELECT a.*, e.first_name, e.last_name FROM employee_attendance a JOIN employees e ON a.employee_nin = e.nin";
        $conditions = ["DATE_FORMAT(a.attendance_date, '%Y-%m') = :month"];
        $params = [':month' => $monthForSql];

        if (!empty($employeeNin)) {
            $conditions[] = "a.employee_nin = :nin";
            $params[':nin'] = $employeeNin;
        }

        $sql .= " WHERE " . implode(" AND ", $conditions);
        $sql .= " ORDER BY a.attendance_date ASC, e.last_name ASC, e.first_name ASC LIMIT 500";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les totaux mensuels (Heures Supplémentaires / Retenues).
     */
    public function getMonthlyFinancialSummaries(int $year, int $month): array {
        $sql = "SELECT employee_nin, total_hs_hours, total_retenu_hours 
                FROM employee_monthly_financial_summary 
                WHERE period_year = :year AND period_month = :month";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':year' => $year, ':month' => $month]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $summariesByNin = [];
        foreach ($results as $row) {
            $summariesByNin[$row['employee_nin']] = $row;
        }
        return $summariesByNin;
    }

    /**
     * Récupère tous les employés actifs.
     */
    public function getActiveEmployees(): array {
        $stmt = $this->db->query("SELECT nin, first_name, last_name FROM employees WHERE status='active' ORDER BY last_name, first_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}