<?php
// /app/models/LogModel.php

class LogModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Récupère les logs pour un utilisateur spécifique.
     * @param int $utilisateur_id L'ID de l'utilisateur.
     * @param int $limit Le nombre de logs à récupérer.
     * @return array La liste des logs.
     */
    public function listerLogsParUtilisateur($utilisateur_id, $limit = 50) {
        $sql = "SELECT * FROM systeme_logs WHERE utilisateur_id = :uid ORDER BY date_log DESC LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':uid', $utilisateur_id, PDO::PARAM_INT);
        $stmt->bindParam(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }


     /**
     * NOUVEAU: Récupère tous les logs du système.
     */
    public function listerTousLesLogs($limit = 200) {
        $sql = "SELECT * FROM systeme_logs ORDER BY date_log DESC LIMIT :lim";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}