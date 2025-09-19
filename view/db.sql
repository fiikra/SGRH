-- Base de données : `mon_erp_dz`
-- Encodage : utf8mb4_general_ci pour supporter l'Arabe

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--
CREATE TABLE `utilisateurs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom_complet` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `mot_de_passe` VARCHAR(255) NOT NULL, -- Hachage bcrypt
  `role_id` INT NOT NULL,
  `est_actif` BOOLEAN DEFAULT true,
  `cree_le` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

--
-- Structure de la table `roles`
--
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom_role` VARCHAR(50) NOT NULL -- Ex: 'Admin', 'Manager', 'Caissier'
) ENGINE=InnoDB;

--
-- Insérer les rôles de base
--
INSERT INTO `roles` (`id`, `nom_role`) VALUES
(1, 'Admin'),
(2, 'Manager'),
(3, 'Caissier'),
(4, 'Magasinier'),
(5, 'Technicien');

--
-- Structure de la table `articles`
--
CREATE TABLE `articles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(100) UNIQUE NOT NULL,
  `designation` VARCHAR(255) NOT NULL,
  `code_barre` VARCHAR(100) NULL,
  `prix_achat_ht` DECIMAL(10, 2) NOT NULL,
  `prix_vente_ht` DECIMAL(10, 2) NOT NULL,
  `tva_taux` DECIMAL(4, 2) DEFAULT 0.19, -- Taux de TVA (ex: 0.19 pour 19%)
  `stock_actuel` INT DEFAULT 0,
  `stock_alerte` INT DEFAULT 10,
  `image_url` VARCHAR(255) NULL,
  `notes` TEXT NULL
) ENGINE=InnoDB;

--
-- Structure de la table `tiers` (pour Clients et Fournisseurs)
--
CREATE TABLE `tiers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('Client', 'Fournisseur') NOT NULL,
  `nom_raison_sociale` VARCHAR(255) NOT NULL,
  `adresse` VARCHAR(255) NULL,
  `telephone` VARCHAR(20) NULL,
  `email` VARCHAR(100) NULL,
  `nif` VARCHAR(20) NULL, -- Numéro d'Identification Fiscale
  `rc` VARCHAR(20) NULL,  -- Registre de Commerce
  `nis` VARCHAR(20) NULL,  -- Numéro d'Identification Statistique
  `art` VARCHAR(20) NULL,  -- Article d'Imposition
  `solde_credit` DECIMAL(10, 2) DEFAULT 0.00,
  `cree_le` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `mouvements_stock` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `type_mouvement` ENUM('entree', 'sortie_vente', 'perte', 'ajustement_inv', 'retour_client', 'retour_fournisseur') NOT NULL,
  `quantite` INT NOT NULL COMMENT 'Toujours une valeur positive',
  `prix_unitaire_ht` DECIMAL(10, 2) NULL COMMENT 'Prix au moment du mouvement',
  `id_document_associe` INT NULL COMMENT 'ID de facture, bon de livraison, etc.',
  `notes` VARCHAR(255) NULL,
  `date_mouvement` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=In


--
-- Structure de la table `factures`
--
CREATE TABLE `factures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `numero_facture` VARCHAR(50) UNIQUE NOT NULL,
  `client_id` INT NULL, -- Peut être un client au comptant (NULL) ou un client enregistré
  `utilisateur_id` INT NOT NULL,
  `montant_ht` DECIMAL(10, 2) NOT NULL,
  `montant_tva` DECIMAL(10, 2) NOT NULL,
  `montant_ttc` DECIMAL(10, 2) NOT NULL,
  `montant_timbre` DECIMAL(10, 2) DEFAULT 0.00,
  `methode_paiement` VARCHAR(50) DEFAULT 'Especes',
  `notes` TEXT NULL,
  `date_facturation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `tiers`(`id`),
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;

--
-- Structure de la table `facture_lignes` (détail de chaque facture)
--
CREATE TABLE `facture_lignes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `facture_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `designation` VARCHAR(255) NOT NULL, -- On copie la désignation pour l'historique
  `quantite` INT NOT NULL,
  `prix_unitaire_ht` DECIMAL(10, 2) NOT NULL,
  `taux_tva` DECIMAL(4, 2) NOT NULL,
  FOREIGN KEY (`facture_id`) REFERENCES `factures`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`)
) ENGINE=InnoDB;


-- Table principale pour les commandes fournisseurs
CREATE TABLE `commandes_fournisseurs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `numero_commande` VARCHAR(50) UNIQUE NOT NULL,
  `fournisseur_id` INT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `montant_ht` DECIMAL(10, 2) NOT NULL,
  `statut` ENUM('Brouillon', 'Commandé', 'Reçu Partiellement', 'Reçu', 'Annulé') DEFAULT 'Brouillon',
  `notes` TEXT NULL,
  `date_commande` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_livraison_prevue` DATE NULL,
  FOREIGN KEY (`fournisseur_id`) REFERENCES `tiers`(`id`),
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;

-- Table pour les lignes de chaque commande
CREATE TABLE `commande_lignes_fournisseurs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `designation` VARCHAR(255) NOT NULL,
  `quantite_commandee` INT NOT NULL,
  `quantite_recue` INT DEFAULT 0,
  `prix_achat_unitaire_ht` DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (`commande_id`) REFERENCES `commandes_fournisseurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES `articles`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `systeme_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utilisateur_id` INT NULL,
  `utilisateur_nom` VARCHAR(100) NULL,
  `niveau` ENUM('INFO', 'WARNING', 'CRITICAL') DEFAULT 'INFO',
  `action` VARCHAR(255) NOT NULL,
  `date_log` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;





-- Table pour lister toutes les permissions possibles
CREATE TABLE `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) UNIQUE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `module` VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Table de liaison (pivot) entre les rôles et les permissions
CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insertion de TOUTES les permissions pour notre application
INSERT INTO `permissions` (`slug`, `description`, `module`) VALUES
('dashboard_voir', 'Voir le tableau de bord', 'Dashboard'),

('pos_utiliser', 'Accéder et utiliser le Point de Vente', 'Ventes'),

('factures_voir_liste', 'Voir la liste des factures', 'Factures'),
('factures_voir_details', 'Voir les détails de facture', 'Factures'),
('factures_creer', 'Créer une nouvelle facture standard', 'Factures'),
('factures_imprimer', 'Imprimer une facture', 'Factures'),

('achats_voir_liste', 'Voir la liste des commandes fournisseurs', 'Achats'),
('achats_voir_details', 'Voir les détails de commande fournisseur', 'Achats'),
('achats_creer', 'Créer une nouvelle commande fournisseur', 'Achats'),
('achats_recevoir', 'Réceptionner la marchandise de commande', 'Achats'),

('articles_voir_liste', 'Voir la liste des articles', 'Articles'),
('articles_voir_details', 'Voir les détails  article', 'Articles'),
('articles_gerer', 'Créer et modifier des articles', 'Articles'),
('articles_supprimer', 'Supprimer un article', 'Articles'),

('stock_ajuster', 'Ajuster le stock manuellement (entrées/sorties)', 'Stock'),

('tiers_voir_liste', 'Voir la liste des clients et fournisseurs', 'Tiers'),
('tiers_gerer', 'Créer et modifier des clients et fournisseurs', 'Tiers'),
('tiers_supprimer', 'Supprimer un client ou fournisseur', 'Tiers'),

('utilisateurs_voir_liste', 'Voir la liste des utilisateurs', 'Utilisateurs'),
('utilisateurs_voir_details', 'Voir les détails  utilisateur', 'Utilisateurs'),
('utilisateurs_gerer', 'Créer et modifier des utilisateurs', 'Utilisateurs'),
('utilisateurs_supprimer', 'Supprimer un utilisateur', 'Utilisateurs'),

('logs_voir', 'Consulter le journal des opérations système', 'Logs'),
('permissions_gerer', 'Gérer les permissions des rôles', 'Permissions');

ALTER TABLE `facture_lignes` 
ADD `cout_unitaire_ht` DECIMAL(10, 2) NOT NULL AFTER `prix_unitaire_ht`;

INSERT INTO `permissions` (`slug`, `description`, `module`) VALUES
('rapports_voir', 'Consulter les rapports financiers et de ventes', 'Rapports');

CREATE TABLE `reparations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `numero_bon` VARCHAR(50) UNIQUE NOT NULL,
  `nom_client` VARCHAR(255) NOT NULL,
  `telephone_client` VARCHAR(20) NOT NULL,
  `email_client` VARCHAR(100) NULL,
  `type_appareil` VARCHAR(255) NOT NULL,
  `panne_declaree` TEXT NOT NULL,
  `notes` TEXT NULL,
  `statut` ENUM('Devis en attente', 'En attente accord', 'En cours', 'Terminé', 'Restitué') DEFAULT 'Devis en attente',
  `technicien_id` INT NULL,
  `date_reception` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_restitution` DATETIME NULL,
   FOREIGN KEY (`technicien_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `parametres` (
  `cle` VARCHAR(50) PRIMARY KEY,
  `valeur` VARCHAR(255) NOT NULL,
  `description` TEXT NULL
) ENGINE=InnoDB;

-- On insère le paramètre pour notre module de services
INSERT INTO `parametres` (`cle`, `valeur`, `description`) VALUES
('module_services_active', '0', 'Activer le module de gestion des services/réparations. 1 = Oui, 0 = Non.');

CREATE TABLE `reparations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `numero_bon` VARCHAR(50) UNIQUE NOT NULL,
  `nom_client` VARCHAR(255) NOT NULL,
  `telephone_client` VARCHAR(20) NOT NULL,
  `email_client` VARCHAR(100) NULL,
  `type_appareil` VARCHAR(255) NOT NULL,
  `panne_declaree` TEXT NOT NULL,
  `notes_technicien` TEXT NULL,
  `statut` ENUM('Devis en attente', 'En attente accord', 'En cours', 'Terminé', 'Restitué') DEFAULT 'Devis en attente',
  `technicien_id` INT NULL,
  `date_reception` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_restitution` DATETIME NULL,
   FOREIGN KEY (`technicien_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;


INSERT INTO `permissions` (`slug`, `description`, `module`) VALUES
('services_gerer', 'Gérer les bons de réparation et services', 'Services');

ALTER TABLE `factures` ADD `reparation_id` INT NULL UNIQUE AFTER `client_id`;

ALTER TABLE `articles` 
ADD `est_stocke` BOOLEAN NOT NULL DEFAULT TRUE AFTER `tva_taux`;

ALTER TABLE `factures` 
ADD `type_document` ENUM('Facture', 'Ticket de Caisse') NOT NULL DEFAULT 'Facture' AFTER `numero_facture`;

ALTER TABLE `factures` 
ADD `reference_paiement` VARCHAR(255) NULL COMMENT 'N° de transaction TPE, N° de chèque, etc.' AFTER `methode_paiement`,
ADD `statut_paiement` ENUM('Payé', 'En attente', 'Annulé') NOT NULL DEFAULT 'Payé' AFTER `reference_paiement`,
ADD `date_encaissement` DATE NULL COMMENT 'Date où le chèque/virement est encaissé' AFTER `statut_paiement`;

INSERT INTO `parametres` (`cle`, `valeur`, `description`) VALUES
('pos_mode_par_defaut', 'Ticket', 'Mode de vente par défaut pour le POS. Mettre "Ticket" ou "Facture".');

-- Ajoute un statut de livraison à la facture
ALTER TABLE `factures` 
ADD `statut_livraison` ENUM('En attente', 'Livré', 'Partiellement livré') NOT NULL DEFAULT 'En attente' AFTER `statut_paiement`;

-- Nouvelle table pour les bons de livraison
CREATE TABLE `bons_livraison` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `facture_id` INT NOT NULL,
  `numero_bl` VARCHAR(50) UNIQUE NOT NULL,
  `date_livraison` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `magasinier_id` INT NOT NULL,
  `notes` TEXT NULL,
  FOREIGN KEY (`facture_id`) REFERENCES `factures`(`id`),
  FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;

-- Nouvelle table pour les lignes des bons de livraison
CREATE TABLE `bon_livraison_lignes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bon_livraison_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `quantite_livree` INT NOT NULL,
  FOREIGN KEY (`bon_livraison_id`) REFERENCES `bons_livraison`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ajout des paramètres pour les plafonds de crédit
INSERT INTO `parametres` (`cle`, `valeur`, `description`) VALUES
('plafond_credit_global', '1000000', 'Montant total de crédit autorisé pour tous les clients confondus.'),
('plafond_credit_client_defaut', '50000', 'Plafond de crédit par défaut pour un nouveau client.');

-- Ajout d'un champ pour le plafond personnalisé par client
ALTER TABLE `tiers` 
ADD `plafond_credit` DECIMAL(10, 2) NULL DEFAULT NULL AFTER `solde_credit`;



-- 1. Améliorer la table des factures
ALTER TABLE `factures`
  ADD `type_livraison` ENUM('Sur Place', 'A Domicile') NOT NULL DEFAULT 'Sur Place' AFTER `reparation_id`,
  ADD `adresse_livraison` TEXT NULL AFTER `type_livraison`,
  ADD `cout_livraison` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER `montant_timbre`,
  ADD `montant_paye` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER `montant_ttc`,
  MODIFY COLUMN `statut_livraison` ENUM('En attente de préparation','Partiellement payé', 'Prêt pour livraison', 'En cours de livraison', 'Livré', 'Annulé') NOT NULL DEFAULT 'En attente de préparation';

-- 2. Améliorer la table des bons de livraison
CREATE TABLE `bons_livraison` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `facture_id` INT NOT NULL,
  `numero_bl` VARCHAR(50) UNIQUE NOT NULL,
  `date_livraison` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `statut` ENUM('En préparation', 'Prêt à expédier', 'En transit', 'Livré', 'Annulé') NOT NULL DEFAULT 'En préparation',
  `adresse_livraison` TEXT NULL,
  `cout_livraison` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `magasinier_id` INT NOT NULL,
  `notes` TEXT NULL,
  FOREIGN KEY (`facture_id`) REFERENCES `factures`(`id`),
  FOREIGN KEY (`magasinier_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;

-- 3. Créer les tables pour les Avoirs (retours)
CREATE TABLE `factures_avoir` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `facture_originale_id` INT NOT NULL,
  `numero_avoir` VARCHAR(50) UNIQUE NOT NULL,
  `client_id` INT NULL,
  `date_avoir` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `montant_ht` DECIMAL(10, 2) NOT NULL,
  `montant_tva` DECIMAL(10, 2) NOT NULL,
  `montant_ttc` DECIMAL(10, 2) NOT NULL,
  `notes` TEXT NULL,
  `utilisateur_id` INT NOT NULL,
  FOREIGN KEY (`facture_originale_id`) REFERENCES `factures`(`id`),
  FOREIGN KEY (`client_id`) REFERENCES `tiers`(`id`),
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `facture_avoir_lignes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `avoir_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `designation` VARCHAR(255) NOT NULL,
  `quantite_retournee` INT NOT NULL,
  `prix_unitaire_ht` DECIMAL(10, 2) NOT NULL,
  `remis_en_stock` BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY (`avoir_id`) REFERENCES `factures_avoir`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Ajouter les nouvelles permissions
INSERT INTO `permissions` (`slug`, `description`, `module`) VALUES
('livraisons_voir_liste', 'Voir la liste des livraisons', 'Livraisons'),
('livraisons_gerer', 'Gérer les livraisons (préparer, valider, changer statut)', 'Livraisons'),
('avoirs_creer', 'Créer des factures d\'avoir (retours client)', 'Avoirs'),
('avoirs_voir_liste', 'Voir la liste des factures d\'avoir', 'Avoirs');
-- L'ENUM est modifié pour inclure la nouvelle option
ALTER TABLE `factures` 
MODIFY COLUMN `methode_paiement` ENUM('Especes', 'TPE', 'Virement', 'Credit', 'Paiement a la livraison') NOT NULL;

INSERT INTO `permissions` (`slug`, `description`, `module`) VALUES
('paiements_encaisser', 'Gérer et enregistrer les encaissements', 'Paiements');


CREATE TABLE `paiements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `facture_id` INT NOT NULL,
  `montant` DECIMAL(10, 2) NOT NULL,
  `methode_paiement` VARCHAR(50) NOT NULL,
  `reference_paiement` VARCHAR(255) NULL,
  `date_paiement` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `utilisateur_id` INT NOT NULL,
  FOREIGN KEY (`facture_id`) REFERENCES `factures`(`id`),
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`)
) ENGINE=InnoDB;



CREATE TABLE `livreurs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom_complet` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(20) NULL,
  `est_actif` BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- On ajoute la colonne pour lier le BL à un livreur
ALTER TABLE `bons_livraison` ADD `livreur_id` INT NULL AFTER `magasinier_id`;