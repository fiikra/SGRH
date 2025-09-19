<?php
// /app/models/ServiceModel.php

class ServiceModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function creerBonReception($data) {
        $sql = "INSERT INTO reparations (numero_bon, nom_client, telephone_client, email_client, type_appareil, panne_declaree, technicien_id)
                VALUES (:numero_bon, :nom_client, :telephone_client, :email_client, :type_appareil, :panne_declaree, :technicien_id)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
}