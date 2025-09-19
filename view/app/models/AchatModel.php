<?php
// /app/models/AchatModel.php

class AchatModel {
    private $db;
    private $stockModel;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
             $this->stockModel = new StockModel(); // On aura besoin du StockModel
   
    }

    /**
     * Crée une commande fournisseur complète (commande + lignes)
     */
    public function creerCommande($data_commande, $lignes_commande) {
        try {
            $this->db->beginTransaction();

            // 1. Créer la commande principale
            $sql_commande = "INSERT INTO commandes_fournisseurs (numero_commande, fournisseur_id, utilisateur_id, montant_ht, statut)
                            VALUES (:numero_commande, :fournisseur_id, :utilisateur_id, :montant_ht, :statut)";
            $stmt_commande = $this->db->prepare($sql_commande);
            $stmt_commande->execute($data_commande);
            $commande_id = $this->db->lastInsertId();

            // 2. Insérer chaque ligne de la commande
            $sql_ligne = "INSERT INTO commande_lignes_fournisseurs (commande_id, article_id, designation, quantite_commandee, prix_achat_unitaire_ht)
                          VALUES (:commande_id, :article_id, :designation, :quantite, :prix)";
            $stmt_ligne = $this->db->prepare($sql_ligne);

            foreach ($lignes_commande as $ligne) {
                $stmt_ligne->execute([
                    ':commande_id' => $commande_id,
                    ':article_id' => $ligne['article_id'],
                    ':designation' => $ligne['designation'],
                    ':quantite' => $ligne['quantite'],
                    ':prix' => $ligne['prix_achat']
                ]);
            }

            $this->db->commit();
            return $commande_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage()); // Pour le débogage
            return false;
        }
    }

    /**
     * Récupère toutes les commandes fournisseurs
     */
    public function listerToutesLesCommandes() {
        $sql = "SELECT c.*, t.nom_raison_sociale AS fournisseur_nom
                FROM commandes_fournisseurs c
                JOIN tiers t ON c.fournisseur_id = t.id
                ORDER BY c.date_commande DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

      /**
     * NOUVEAU: Trouve une commande par son ID.
     */
    public function trouverCommandeParId($id) {
        $sql = "SELECT c.*, t.nom_raison_sociale AS fournisseur_nom FROM commandes_fournisseurs c JOIN tiers t ON c.fournisseur_id = t.id WHERE c.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

     /**
     * NOUVEAU: Récupère les lignes d'une commande.
     */
    public function listerLignesParCommandeId($commande_id) {
        $sql = "SELECT * FROM commande_lignes_fournisseurs WHERE commande_id = :commande_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':commande_id' => $commande_id]);
        return $stmt->fetchAll();
    }
/**
     * NOUVEAU: Traite la réception des articles et met à jour le stock.
     * C'est une opération critique utilisant une transaction.
     */
    public function receptionnerLignes($commande_id, $lignes_recues, $utilisateur_id) {
        try {
            $this->db->beginTransaction();
            $totalQuantiteCommandee = 0;
            $totalQuantiteDejaRecue = 0;

            // 1. Mettre à jour les lignes de commande et le stock
            foreach ($lignes_recues as $ligne_id => $qte_recue) {
                if ($qte_recue > 0) {
                    // Mettre à jour la quantité reçue sur la ligne de commande
                    $sql_update_ligne = "UPDATE commande_lignes_fournisseurs SET quantite_recue = quantite_recue + :qte_recue WHERE id = :ligne_id";
                    $stmt = $this->db->prepare($sql_update_ligne);
                    $stmt->execute([':qte_recue' => $qte_recue, ':ligne_id' => $ligne_id]);
                    
                    // Récupérer l'ID de l'article pour la sortie de stock
                    $stmt_get_article = $this->db->prepare("SELECT article_id FROM commande_lignes_fournisseurs WHERE id = :ligne_id");
                    $stmt_get_article->execute([':ligne_id' => $ligne_id]);
                    $article_id = $stmt_get_article->fetchColumn();

                    // Enregistrer le mouvement de stock
                    $this->stockModel->enregistrerMouvement([
                        'article_id' => $article_id,
                        'type_mouvement' => 'entree',
                        'quantite' => $qte_recue,
                        'utilisateur_id' => $utilisateur_id,
                        'notes' => 'Réception commande fournisseur ID: ' . $commande_id
                    ]);
                }
            }
            // 2. Mettre à jour le statut de la commande principale
            $lignes = $this->listerLignesParCommandeId($commande_id);
            $totalQuantiteCommandee = 0;
            $totalQuantiteMaintenantRecue = 0;

            foreach($lignes as $ligne){
                $totalQuantiteCommandee += $ligne['quantite_commandee'];
                $totalQuantiteMaintenantRecue += $ligne['quantite_recue'];
            }
            
            $nouveau_statut = ($totalQuantiteMaintenantRecue >= $totalQuantiteCommandee) ? 'Reçu' : 'Reçu Partiellement';
            
            $sql_update_commande = "UPDATE commandes_fournisseurs SET statut = :statut WHERE id = :commande_id";
            $stmt_update_commande = $this->db->prepare($sql_update_commande);
            $stmt_update_commande->execute([':statut' => $nouveau_statut, ':commande_id' => $commande_id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

        /**
     * NOUVEAU: Affiche les détails d'une commande spécifique.
     */
    public function voir($commande_id) {
        $commande = $this->achatModel->trouverCommandeParId($commande_id);
        if (!$commande) {
            rediriger('achats'); // Si la commande n'existe pas, retour à la liste
        }

        $lignes = $this->achatModel->listerLignesParCommandeId($commande_id);

        $data = [
            'titre' => 'Détails de la Commande ' . $commande['numero_commande'],
            'commande' => $commande,
            'lignes' => $lignes
        ];
        
        // On utilise la vue normale avec header/footer
        $this->view('achats/voir', $data);
    }
}