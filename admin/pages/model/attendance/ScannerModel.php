<?php
class ScannerModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupère les paramètres de l'entreprise pour le kiosque.
     */
    public function getKioskSettings(): array {
        $stmt = $this->db->prepare("SELECT company_name, logo_path, attendance_method, scan_mode FROM company_settings WHERE id = ?");
        $stmt->execute([1]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'company_name' => $settings['company_name'] ?? 'SN-TECH',
            'logo_path' => $settings['logo_path'] ?? null,
            'attendance_method' => $settings['attendance_method'] ?? 'qrcode',
            'scan_mode' => $settings['scan_mode'] ?? 'keyboard',
        ];
    }

    /**
     * Récupère un employé par son NIN s'il est actif.
     */
    public function getActiveEmployeeByNin(string $nin) {
        $stmt = $this->db->prepare("SELECT first_name, last_name, department FROM employees WHERE nin = ? AND status = 'active'");
        $stmt->execute([$nin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Détermine le type du prochain scan (entrée ou sortie).
     */
    public function getNextScanType(string $nin): string {
        $stmt = $this->db->prepare(
            "SELECT scan_type FROM attendance_scans 
             WHERE employee_nin = ? AND DATE(scan_time) = CURDATE() 
             ORDER BY scan_time DESC LIMIT 1"
        );
        $stmt->execute([$nin]);
        $lastScan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si pas de scan aujourd'hui ou si le dernier était une sortie, le prochain est une entrée.
        return (!$lastScan || $lastScan['scan_type'] === 'out') ? 'in' : 'out';
    }

    /**
     * Enregistre un nouveau scan dans la base de données.
     */
    public function recordScan(string $nin, string $type): bool {
        $stmt = $this->db->prepare("INSERT INTO attendance_scans (employee_nin, scan_type) VALUES (?, ?)");
        return $stmt->execute([$nin, $type]);
    }

    /**
     * Récupère les derniers scans (pour le flux en direct).
     */
    public function getRecentScans(string $type, int $limit = 7): array {
        $stmt = $this->db->prepare(
            "SELECT s.employee_nin, s.scan_type, TIME(s.scan_time) as scan_time, e.first_name, e.last_name, e.department, e.photo_path
             FROM attendance_scans s
             JOIN employees e ON s.employee_nin = e.nin
             WHERE DATE(s.scan_time) = CURDATE() AND s.scan_type = ?
             ORDER BY s.scan_time DESC
             LIMIT ?"
        );
        $stmt->execute([$type, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}