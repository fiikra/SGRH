<?php
class ExcelTemplateModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupère les paramètres généraux de l'entreprise.
     */
    public function getCompanySettings(): array {
        $stmt = $this->db->query("SELECT weekend_days, jours_feries FROM company_settings WHERE id = 1 LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère tous les employés actifs avec leurs jours de repos spécifiques.
     */
    public function getActiveEmployeesWithRestDays(): array {
        $stmt = $this->db->query("SELECT nin, first_name, last_name, employee_rest_days FROM employees WHERE status = 'active' ORDER BY last_name, first_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les congés approuvés pour une période donnée.
     */
    public function getApprovedLeavesForPeriod(string $startDate, string $endDate): array {
        $sql = "SELECT employee_nin, start_date, end_date, leave_type FROM leave_requests 
                WHERE status = 'approved' 
                AND start_date <= :end_date
                AND end_date >= :start_date";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}