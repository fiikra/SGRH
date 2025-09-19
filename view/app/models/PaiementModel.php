<?php
// /app/models/PaiementModel.php
class PaiementModel {
    private $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function enregistrerVersement($data) {
        try {
            $this->db->beginTransaction();

            // 1. Insérer le nouveau paiement
            $sql_paiement = "INSERT INTO paiements (facture_id, montant, methode_paiement, reference_paiement, utilisateur_id) VALUES (:fid, :montant, :methode, :ref, :uid)";
            $stmt = $this->db->prepare($sql_paiement);
            $stmt->execute([
                ':fid' => $data['facture_id'],
                ':montant' => $data['montant'],
                ':methode' => $data['methode_paiement'],
                ':ref' => $data['reference_paiement'],
                ':uid' => $data['utilisateur_id']
            ]);

            // 2. Mettre à jour la facture (montant payé et statut)
            $factureModel = new FactureModel();
            $facture = $factureModel->trouverFactureParId($data['facture_id']);
            $nouveau_montant_paye = $facture['montant_paye'] + $data['montant'];
            $nouveau_statut = ($nouveau_montant_paye >= $facture['montant_ttc']) ? 'Payé' : 'Partiellement payé';

            $sql_update = "UPDATE factures SET montant_paye = :montant, statut_paiement = :statut WHERE id = :id";
            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->execute([':montant' => $nouveau_montant_paye, ':statut' => $nouveau_statut, ':id' => $data['facture_id']]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }
}