<?php
class PdfExportModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupère les détails complets d'un employé par son NIN.
     */
    public function getEmployeeDetails(string $nin) {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE nin = ?");
        $stmt->execute([$nin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les paramètres de l'entreprise.
     */
    public function getCompanySettings(): array {
        $stmt = $this->db->query("SELECT * FROM company_settings LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Récupère les données de présence pour un employé et une période.
     * (Cette méthode est similaire à celle de AttendanceHistoryModel)
     */
    public function getAttendanceData(string $nin, int $year, int $month): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_attendance 
             WHERE employee_nin = :nin AND YEAR(attendance_date) = :year AND MONTH(attendance_date) = :month
             ORDER BY attendance_date ASC"
        );
        $stmt->execute([':nin' => $nin, ':year' => $year, ':month' => $month]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $attendanceByDate = [];
        foreach ($results as $record) {
            $attendanceByDate[date('Y-m-d', strtotime($record['attendance_date']))] = $record;
        }
        return $attendanceByDate;
    }
}