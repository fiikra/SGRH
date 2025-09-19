<?php
class AttendanceHistoryModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupère les enregistrements de présence pour un mois et un employé donnés.
     *
     * @param int $year L'année du filtre.
     * @param int $month Le mois du filtre.
     * @param string $employeeNin Le NIN de l'employé (optionnel).
     * @return array La liste des enregistrements de présence.
     */
    public function getAttendanceHistory(int $year, int $month, string $employeeNin = ''): array {
        $params = [':month' => sprintf('%04d-%02d', $year, $month)];
        $conditions = ["DATE_FORMAT(a.attendance_date, '%Y-%m') = :month"];

        if (!empty($employeeNin)) {
            $conditions[] = "a.employee_nin = :nin";
            $params[':nin'] = $employeeNin;
        }

        $sql = "SELECT a.*, e.first_name, e.last_name 
                FROM employee_attendance a 
                JOIN employees e ON a.employee_nin = e.nin
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY a.attendance_date ASC, e.last_name ASC, e.first_name ASC 
                LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère la liste de tous les employés actifs pour le menu déroulant.
     *
     * @return array La liste des employés.
     */
    public function getActiveEmployees(): array {
        $stmt = $this->db->query("SELECT nin, first_name, last_name FROM employees WHERE status='active' ORDER BY last_name, first_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}