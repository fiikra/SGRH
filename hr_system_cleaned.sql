-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 19 sep. 2025 à 11:23
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `hr_system`
--

-- --------------------------------------------------------

--
-- Structure de la table `attendance_scans`
--

CREATE TABLE `attendance_scans` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `scan_time` datetime NOT NULL DEFAULT current_timestamp(),
  `scan_type` enum('in','out') NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `selector` varchar(255) NOT NULL,
  `hashed_validator` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `auth_tokens`
--

INSERT INTO `auth_tokens` (`id`, `selector`, `hashed_validator`, `user_id`, `expires`) VALUES
(1, '8b776d24ce7b1755e6dc9187e606bcc0', '$2y$10$O/TPCr0vR.0mUoz40DkqwOR9zuonhAPqH1Gcr0Q7TsMfTCGoKmQKG', 2, '2025-08-12 13:26:06'),
(3, '8234da2d352ea18dacfb53977a6324f4', '$2y$10$cDvxaHOaCCwOiUojec7CqOXFYeMBRLdo8Ttx5L5x877dUL2OHnkM.', 2, '2025-08-13 19:13:31'),
(25, '73195cdd36557d56661ff5bf0bf1e289', '$2y$10$6bum9RWsI7PNZ6qIA01nuOWkMSd1OCa7PPLJQbu6j4xyaiBtBhWE2', 2, '2025-09-12 22:05:14');

-- --------------------------------------------------------

--
-- Structure de la table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `certificate_type` enum('Attestation','Certficate','Attestation_sold','Attestation Conge') NOT NULL,
  `issue_date` date NOT NULL,
  `prepared_by` int(11) NOT NULL,
  `content` text NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `generated_filename` varchar(255) DEFAULT NULL,
  `external_ref_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `certificates`
--


-- --------------------------------------------------------

--
-- Structure de la table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `legal_form` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `trade_register` varchar(50) DEFAULT NULL,
  `article_imposition` varchar(30) DEFAULT NULL,
  `cnas_code` varchar(30) DEFAULT NULL,
  `casnos_code` varchar(30) DEFAULT NULL,
  `secteur_activite` varchar(100) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `leave_policy` text DEFAULT NULL,
  `work_hours_per_week` int(11) DEFAULT NULL,
  `maternite_leave_days` int(11) DEFAULT 150,
  `work_days_per_week` int(11) DEFAULT 5 COMMENT 'Nombre standard de jours de travail par semaine (ex: 5 ou 6)',
  `min_salary` decimal(10,2) DEFAULT NULL,
  `exercice_start` date DEFAULT NULL,
  `langue` varchar(20) DEFAULT NULL,
  `devise` varchar(10) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `doc_signatory` varchar(100) DEFAULT NULL,
  `doc_reference_format` varchar(100) DEFAULT NULL,
  `jours_feries` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `weekend_days` varchar(32) DEFAULT NULL,
  `paie_mode` int(11) DEFAULT 22,
  `lateness_grace_period` int(11) DEFAULT 15 COMMENT 'Tolerated lateness margin in minutes',
  `attendance_method` enum('qrcode','biometric') DEFAULT 'qrcode' COMMENT 'Primary method for attendance tracking',
  `scan_mode` enum('keyboard','camera') DEFAULT 'keyboard' COMMENT 'Type of QR code scanner to use'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `company_settings`
--


-- --------------------------------------------------------

--
-- Structure de la table `contrats`
--

CREATE TABLE `contrats` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `pv_reference_number` varchar(50) DEFAULT NULL,
  `employe_nin` varchar(20) NOT NULL,
  `type_contrat` varchar(255) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `periode_essai_jours` int(11) DEFAULT NULL,
  `salaire_brut` decimal(10,2) DEFAULT NULL,
  `poste` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `contrats`
--


-- --------------------------------------------------------

--
-- Structure de la table `departements`
--

CREATE TABLE `departements` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `departements`
--


-- --------------------------------------------------------

--
-- Structure de la table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_nin` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `employees`
--

CREATE TABLE `employees` (
  `nin` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nss` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `gender` enum('male','female') NOT NULL,
  `birth_date` date NOT NULL,
  `birth_place` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `marital_status` enum('Celibataire','marrie','divorce','veuf/e') NOT NULL,
  `dependents` int(11) DEFAULT 0,
  `hire_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `contract_type` enum('cdi','cdd','stage','interim','essai') NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `salary` decimal(12,2) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspendu','Licencement','demission') DEFAULT 'active',
  `departure_date` date DEFAULT NULL COMMENT 'Date de sortie effective',
  `departure_reason` varchar(255) DEFAULT NULL COMMENT 'Motif de la sortie',
  `notes` text DEFAULT NULL,
  `rib` varchar(50) DEFAULT NULL,
  `employee_rest_days` varchar(20) DEFAULT NULL COMMENT 'Jours de repos spécifiques à l''employé, ex: "0,6" pour Dimanche,Samedi (0=Dim, 1=Lun,...6=Sam)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `annual_leave_balance` decimal(5,1) DEFAULT 0.0,
  `last_leave_balance_update` datetime DEFAULT NULL,
  `last_leave_days_added` date DEFAULT NULL,
  `last_leave_accrual` date DEFAULT NULL,
  `next_accrual_date` date DEFAULT NULL,
  `remaining_leave_balance` decimal(5,2) DEFAULT 0.00,
  `recup_balance` int(11) DEFAULT 0,
  `position_history_id` int(11) DEFAULT NULL,
  `salary_history_id` int(11) DEFAULT NULL,
  `on_trial` tinyint(1) NOT NULL DEFAULT 0,
  `trial_end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employees`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_attendance`
--

CREATE TABLE `employee_attendance` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(255) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('present','present_offday','weekend','holiday','absent_unjustified','absent_authorized_paid','absent_authorized_unpaid','sick_leave','maternity_leave','training','mission','other_leave','annual_leave','present_weekend') NOT NULL DEFAULT 'present',
  `leave_type_if_absent` varchar(100) DEFAULT NULL,
  `effective_work_hours` decimal(4,2) DEFAULT NULL,
  `overtime_hours_recorded` decimal(4,2) DEFAULT 0.00,
  `is_weekend_work` tinyint(1) DEFAULT 0,
  `is_holiday_work` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_validated` tinyint(1) DEFAULT 0,
  `validated_by` int(11) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `late_minutes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_attendance`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_documents`
--

CREATE TABLE `employee_documents` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `document_type` enum('cv','diplome','contrat','Carte ID','Residance','extrait naissance','analyse medicale','autre') NOT NULL,
  `title` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_documents`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_monthly_financial_summary`
--

CREATE TABLE `employee_monthly_financial_summary` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `period_year` int(4) NOT NULL,
  `period_month` int(2) NOT NULL,
  `total_hs_hours` decimal(5,2) DEFAULT 0.00,
  `total_retenu_hours` decimal(5,2) DEFAULT 0.00,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_monthly_financial_summary`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_position_history`
--

CREATE TABLE `employee_position_history` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(255) NOT NULL,
  `position_title` varchar(255) NOT NULL,
  `old_position` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_position_history`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_questionnaires`
--

CREATE TABLE `employee_questionnaires` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `employee_nin` varchar(20) NOT NULL,
  `sanction_id` int(11) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `subject` varchar(255) NOT NULL,
  `response_due_date` date DEFAULT NULL,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`questions`)),
  `questionnaire_type` varchar(100) NOT NULL,
  `status` enum('pending_response','responded','decision_made','closed') NOT NULL DEFAULT 'pending_response',
  `pdf_path` varchar(255) DEFAULT NULL,
  `response_date` date DEFAULT NULL,
  `response_summary` text DEFAULT NULL,
  `response_pdf_path` varchar(255) DEFAULT NULL COMMENT 'Chemin vers le PDF des réponses',
  `decision` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_questionnaires`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_recup_days`
--

CREATE TABLE `employee_recup_days` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(32) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `nb_jours` int(11) NOT NULL DEFAULT 0,
  `status` enum('not_taked','taked') DEFAULT 'not_taked'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_recup_days`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_recup_days_details`
--

CREATE TABLE `employee_recup_days_details` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(20) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `recup_day` int(11) NOT NULL,
  `recup_type` enum('TF','TW') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_recup_days_details`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_salary_history`
--

CREATE TABLE `employee_salary_history` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(255) NOT NULL,
  `gross_salary` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `change_type` varchar(255) NOT NULL COMMENT 'Ex: Promotion, Augmentation annuelle, etc.',
  `notes` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_salary_history`
--


-- --------------------------------------------------------

--
-- Structure de la table `employee_sanctions`
--

CREATE TABLE `employee_sanctions` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `employee_nin` varchar(20) NOT NULL,
  `questionnaire_id` int(11) DEFAULT NULL,
  `fault_date` date NOT NULL,
  `sanction_type` enum('avertissement_verbal','avertissement_ecrit','mise_a_pied_1','mise_a_pied_2','mise_a_pied_3','licenciement') NOT NULL COMMENT '1er, 2e, 3e degré',
  `reason` text NOT NULL,
  `sanction_date` date NOT NULL,
  `decision_ref` varchar(100) DEFAULT NULL,
  `notification_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_sanctions`
--


-- --------------------------------------------------------

--
-- Structure de la table `formations`
--

CREATE TABLE `formations` (
  `id` int(11) NOT NULL,
  `reference_number` varchar(20) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subject` text DEFAULT NULL,
  `trainer_name` varchar(255) DEFAULT NULL COMMENT 'Name of the trainer or school',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Planifiée','Terminée') NOT NULL DEFAULT 'Planifiée',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `formations`
--


-- --------------------------------------------------------

--
-- Structure de la table `formation_participants`
--

CREATE TABLE `formation_participants` (
  `id` int(11) NOT NULL,
  `formation_id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `status` enum('Inscrit','Complété','Annulé') NOT NULL DEFAULT 'Inscrit',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `formation_participants`
--


-- --------------------------------------------------------

--
-- Structure de la table `leave_adjustments`
--

CREATE TABLE `leave_adjustments` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(20) NOT NULL,
  `request_date` datetime NOT NULL,
  `requested_by` varchar(50) NOT NULL,
  `approved_by` varchar(50) DEFAULT NULL,
  `adjustment_date` datetime NOT NULL,
  `days_change` decimal(5,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `leave_balance_history`
--

CREATE TABLE `leave_balance_history` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `update_date` datetime NOT NULL,
  `days_added` decimal(5,2) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `operation_type` enum('acquisition','reliquat','consumption') NOT NULL,
  `operation_date` datetime NOT NULL,
  `leave_year` int(11) NOT NULL,
  `previous_balance` decimal(5,2) NOT NULL,
  `new_balance` decimal(5,2) NOT NULL,
  `performed_by` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `leave_balance_history`
--


-- --------------------------------------------------------

--
-- Structure de la table `leave_balance_updates`
--

CREATE TABLE `leave_balance_updates` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `update_date` date NOT NULL,
  `days_added` decimal(3,1) NOT NULL,
  `leave_year` int(11) DEFAULT NULL COMMENT 'Starting year of the leave period (e.g., 2024 for July 2024 - June 2025)',
  `month` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Month of the update (1-12)',
  `operation_type` varchar(50) DEFAULT NULL COMMENT 'Type of leave balance operation (e.g., acquisition, reliquat, manual_adjustment)',
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `leave_balance_updates`
--


-- --------------------------------------------------------

--
-- Structure de la table `leave_pauses`
--

CREATE TABLE `leave_pauses` (
  `id` int(11) NOT NULL,
  `leave_request_id` int(11) NOT NULL,
  `pause_start_date` date NOT NULL,
  `pause_end_date` date NOT NULL,
  `reason` text NOT NULL,
  `attachment_filename` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `leave_pauses`
--


-- --------------------------------------------------------

--
-- Structure de la table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `leave_type` enum('annuel','maladie','maternite','unpaid','reliquat','recup','anticipe','special_mariage','special_naissance','special_deces','special_mariage_enf','special_circoncision') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` int(11) NOT NULL,
  `reason` text NOT NULL,
  `justification_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected','paused','prise','cancelled') DEFAULT 'pending',
  `certificate_reference_number` varchar(50) DEFAULT NULL,
  `leave_year` int(11) NOT NULL,
  `reliquat_year_used` date NOT NULL,
  `use_remaining` tinyint(1) DEFAULT 0,
  `use_recup` float DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `comment` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `use_annuel` float DEFAULT 0,
  `use_reliquat` float DEFAULT 0,
  `use_anticipe` float DEFAULT 0,
  `use_unpaid` float DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `leave_requests`
--


-- --------------------------------------------------------

--
-- Structure de la table `mission_orders`
--

CREATE TABLE `mission_orders` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `destination` text NOT NULL,
  `departure_date` datetime NOT NULL,
  `return_date` datetime NOT NULL,
  `objective` text NOT NULL,
  `vehicle_registration` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `approved_by_user_id` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `mission_orders`
--


-- --------------------------------------------------------

--
-- Structure de la table `monthly_leave_accruals_log`
--

CREATE TABLE `monthly_leave_accruals_log` (
  `id` int(11) NOT NULL,
  `accrual_year` int(4) NOT NULL,
  `accrual_month` int(2) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `executed_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `monthly_leave_accruals_log`
--


-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `persistent_logins`
--

CREATE TABLE `persistent_logins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` char(24) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `persistent_logins`
--


-- --------------------------------------------------------

--
-- Structure de la table `personnel_settings`
--

CREATE TABLE `personnel_settings` (
  `id` int(11) NOT NULL,
  `postes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`postes`)),
  `departements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`departements`)),
  `types_contrat` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`types_contrat`)),
  `work_hours_per_week` int(11) DEFAULT NULL,
  `weekend_days` varchar(30) DEFAULT NULL,
  `hs_policy` text DEFAULT NULL,
  `min_salaire_cat` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`min_salaire_cat`)),
  `matricule_pattern` varchar(100) DEFAULT NULL,
  `exercice_start` date DEFAULT NULL,
  `anciennete_depart` date DEFAULT NULL,
  `docs_embauche` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`docs_embauche`)),
  `public_holidays` text DEFAULT NULL COMMENT 'JSON array of public holidays, e.g., [{"date": "YYYY-MM-DD", "description": "Holiday Name"}]',
  `defined_absence_types` text DEFAULT NULL COMMENT 'JSON array of absence types for forms/templates',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `personnel_settings`
--


-- --------------------------------------------------------

--
-- Structure de la table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `base_salary` decimal(10,2) DEFAULT NULL,
  `job_description` text DEFAULT NULL COMMENT 'Fiche de poste',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `postes`
--

CREATE TABLE `postes` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `missions` text DEFAULT NULL,
  `competences` text DEFAULT NULL,
  `departement_id` int(11) DEFAULT NULL,
  `hierarchie` varchar(100) DEFAULT NULL,
  `code_poste` varchar(20) DEFAULT NULL,
  `salaire_base` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `postes`
--


-- --------------------------------------------------------

--
-- Structure de la table `promotion_decisions`
--

CREATE TABLE `promotion_decisions` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `reference_number` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `effective_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `decision_type` varchar(20) NOT NULL,
  `new_position` varchar(100) DEFAULT NULL,
  `old_position` varchar(100) DEFAULT NULL,
  `new_salary` decimal(12,2) DEFAULT NULL,
  `old_salary` decimal(12,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `generated_pdf_path` varchar(255) DEFAULT NULL,
  `reference_increment` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `promotion_decisions`
--


-- --------------------------------------------------------

--
-- Structure de la table `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `secure` enum('tls','ssl') NOT NULL DEFAULT 'tls',
  `from_email` varchar(255) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `method` enum('phpmail','smtp') NOT NULL DEFAULT 'smtp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `smtp_settings`
--


-- --------------------------------------------------------

--
-- Structure de la table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `log_level` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `context` varchar(255) DEFAULT 'GENERAL',
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `system_logs`
--


-- --------------------------------------------------------

--
-- Structure de la table `trial_notifications`
--

CREATE TABLE `trial_notifications` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(30) NOT NULL,
  `reference_number` varchar(40) NOT NULL,
  `issue_date` datetime NOT NULL,
  `reference_increment` int(11) GENERATED ALWAYS AS (cast(substr(`reference_number`,3,4) as unsigned)) STORED,
  `decision` enum('confirm','renew','terminate') NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `generated_pdf_path` varchar(255) DEFAULT NULL,
  `renew_period` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `trial_notifications`
--


-- --------------------------------------------------------

--
-- Structure de la table `trial_periods`
--

CREATE TABLE `trial_periods` (
  `id` int(11) NOT NULL,
  `employee_nin` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_months` int(11) NOT NULL,
  `status` enum('active','completed','renewed','terminated') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `trial_periods`
--


-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','hr','employee') NOT NULL DEFAULT 'employee',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL,
  `otp_code` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_email_otp_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = Email OTP Disabled, 1 = Enabled',
  `otp_secret` varchar(255) DEFAULT NULL COMMENT 'Encrypted secret for authenticator app',
  `is_app_otp_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = App OTP Disabled, 1 = Enabled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `is_active`, `created_at`, `last_login`, `failed_attempts`, `account_locked_until`, `otp_code`, `otp_expires_at`, `is_email_otp_enabled`, `otp_secret`, `is_app_otp_enabled`) VALUES
(1, 'admin', '$2y$10$oMntb72U6TbHDBIhTFsMGOjvWoNYOaIAlmtoO6j8CNkexKxGi60AC', 'test@gmail.com', 'admin', 1, '2025-05-13 17:51:50', '2025-07-14 17:52:59', 0, NULL, NULL, NULL, 0, NULL, 0),
(3, 'TEST', '$2y$10$OaztpexTm.T2AEWZynwosep6KP0rnIrRx4fkkcd48DBAc4BpA5SEq', 'TEST@email.com', 'employee', 1, '2025-05-14 08:07:47', '2025-07-19 19:35:44', 0, NULL, NULL, NULL, 0, NULL, 0),
(4, 'USER-Talp800', '$2y$10$FqgoUgXWvnWBpwgRCOb4WuJ0HSFfxi24dQ7Mcra16fVEm/nD1wzXG', 'talpha@entreprise.com', 'employee', 1, '2025-05-18 14:45:39', '2025-06-01 19:04:21', 0, NULL, NULL, NULL, 0, NULL, 0),
(5, 'USER-Eexe389', '$2y$10$hUEKE3pCG1pAj6bk9nmew.KI6bjtl1.g75FuLt2pl0Qq2aii5cA4a', 'eexemple002@entreprise.com', 'employee', 1, '2025-05-19 10:28:33', '2025-05-19 10:29:09', 0, NULL, NULL, NULL, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `user_logins`
--

CREATE TABLE `user_logins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `user_logins`
--


--
-- Index pour les tables déchargées
--

--
-- Index pour la table `attendance_scans`
--
ALTER TABLE `attendance_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`),
  ADD KEY `scan_time` (`scan_time`);

--
-- Index pour la table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_selector` (`selector`),
  ADD KEY `fk_user_id` (`user_id`);

--
-- Index pour la table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`),
  ADD KEY `prepared_by` (`prepared_by`),
  ADD KEY `idx_external_ref_id` (`external_ref_id`);

--
-- Index pour la table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `contrats`
--
ALTER TABLE `contrats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employe_nin` (`employe_nin`);

--
-- Index pour la table `departements`
--
ALTER TABLE `departements`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`nin`),
  ADD UNIQUE KEY `nss` (`nss`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`employee_nin`,`attendance_date`),
  ADD KEY `recorded_by_user_id` (`recorded_by_user_id`),
  ADD KEY `validated_by` (`validated_by`);

--
-- Index pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `employee_monthly_financial_summary`
--
ALTER TABLE `employee_monthly_financial_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_employee_period` (`employee_nin`,`period_year`,`period_month`),
  ADD KEY `fk_monthly_financials_user_id` (`recorded_by_user_id`);

--
-- Index pour la table `employee_position_history`
--
ALTER TABLE `employee_position_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `employee_questionnaires`
--
ALTER TABLE `employee_questionnaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`),
  ADD KEY `sanction_id` (`sanction_id`);

--
-- Index pour la table `employee_recup_days`
--
ALTER TABLE `employee_recup_days`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_nin` (`employee_nin`,`year`,`month`);

--
-- Index pour la table `employee_recup_days_details`
--
ALTER TABLE `employee_recup_days_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_recup` (`employee_nin`,`year`,`month`,`recup_day`);

--
-- Index pour la table `employee_salary_history`
--
ALTER TABLE `employee_salary_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `employee_sanctions`
--
ALTER TABLE `employee_sanctions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `questionnaire_id` (`questionnaire_id`),
  ADD KEY `employee_nin` (`employee_nin`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `formations`
--
ALTER TABLE `formations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`);

--
-- Index pour la table `formation_participants`
--
ALTER TABLE `formation_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`formation_id`,`employee_nin`),
  ADD KEY `fk_formation_id` (`formation_id`),
  ADD KEY `fk_employee_nin` (`employee_nin`);

--
-- Index pour la table `leave_adjustments`
--
ALTER TABLE `leave_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `leave_balance_history`
--
ALTER TABLE `leave_balance_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `leave_balance_updates`
--
ALTER TABLE `leave_balance_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `leave_pauses`
--
ALTER TABLE `leave_pauses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_request_id` (`leave_request_id`);

--
-- Index pour la table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Index pour la table `mission_orders`
--
ALTER TABLE `mission_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_reference_number` (`reference_number`),
  ADD KEY `idx_employee_nin` (`employee_nin`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_mission_created_by` (`created_by_user_id`),
  ADD KEY `fk_mission_approved_by` (`approved_by_user_id`);

--
-- Index pour la table `monthly_leave_accruals_log`
--
ALTER TABLE `monthly_leave_accruals_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_year_month_accrual` (`accrual_year`,`accrual_month`),
  ADD KEY `fk_accrual_user` (`executed_by_user_id`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `persistent_logins`
--
ALTER TABLE `persistent_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `personnel_settings`
--
ALTER TABLE `personnel_settings`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`),
  ADD KEY `department_id` (`department_id`);

--
-- Index pour la table `postes`
--
ALTER TABLE `postes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_poste` (`code_poste`),
  ADD KEY `departement_id` (`departement_id`);

--
-- Index pour la table `promotion_decisions`
--
ALTER TABLE `promotion_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `trial_notifications`
--
ALTER TABLE `trial_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_number` (`reference_number`),
  ADD KEY `employee_nin` (`employee_nin`),
  ADD KEY `issue_date` (`issue_date`);

--
-- Index pour la table `trial_periods`
--
ALTER TABLE `trial_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_nin` (`employee_nin`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_logins`
--
ALTER TABLE `user_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `attendance_scans`
--
ALTER TABLE `attendance_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT pour la table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `contrats`
--
ALTER TABLE `contrats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `departements`
--
ALTER TABLE `departements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2835;

--
-- AUTO_INCREMENT pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `employee_monthly_financial_summary`
--
ALTER TABLE `employee_monthly_financial_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `employee_position_history`
--
ALTER TABLE `employee_position_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `employee_questionnaires`
--
ALTER TABLE `employee_questionnaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `employee_recup_days`
--
ALTER TABLE `employee_recup_days`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT pour la table `employee_recup_days_details`
--
ALTER TABLE `employee_recup_days_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `employee_salary_history`
--
ALTER TABLE `employee_salary_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `employee_sanctions`
--
ALTER TABLE `employee_sanctions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `formations`
--
ALTER TABLE `formations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `formation_participants`
--
ALTER TABLE `formation_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `leave_adjustments`
--
ALTER TABLE `leave_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `leave_balance_history`
--
ALTER TABLE `leave_balance_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `leave_balance_updates`
--
ALTER TABLE `leave_balance_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `leave_pauses`
--
ALTER TABLE `leave_pauses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT pour la table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT pour la table `mission_orders`
--
ALTER TABLE `mission_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `monthly_leave_accruals_log`
--
ALTER TABLE `monthly_leave_accruals_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `persistent_logins`
--
ALTER TABLE `persistent_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `postes`
--
ALTER TABLE `postes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `promotion_decisions`
--
ALTER TABLE `promotion_decisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT pour la table `trial_notifications`
--
ALTER TABLE `trial_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT pour la table `trial_periods`
--
ALTER TABLE `trial_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `user_logins`
--
ALTER TABLE `user_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `attendance_scans`
--
ALTER TABLE `attendance_scans`
  ADD CONSTRAINT `fk_scan_employee` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE;

--
-- Contraintes pour la table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `fk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `contrats`
--
ALTER TABLE `contrats`
  ADD CONSTRAINT `contrats_ibfk_1` FOREIGN KEY (`employe_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_nin`) REFERENCES `employees` (`nin`) ON DELETE SET NULL;

--
-- Contraintes pour la table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `employee_attendance`
--
ALTER TABLE `employee_attendance`
  ADD CONSTRAINT `employee_attendance_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_attendance_ibfk_2` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employee_attendance_ibfk_3` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employee_attendance_ibfk_4` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `employee_documents`
--
ALTER TABLE `employee_documents`
  ADD CONSTRAINT `employee_documents_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE;

--
-- Contraintes pour la table `employee_monthly_financial_summary`
--
ALTER TABLE `employee_monthly_financial_summary`
  ADD CONSTRAINT `fk_monthly_financials_employee_nin` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monthly_financials_user_id` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `employee_position_history`
--
ALTER TABLE `employee_position_history`
  ADD CONSTRAINT `employee_position_history_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE;

--
-- Contraintes pour la table `employee_questionnaires`
--
ALTER TABLE `employee_questionnaires`
  ADD CONSTRAINT `employee_questionnaires_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `employee_questionnaires_ibfk_2` FOREIGN KEY (`sanction_id`) REFERENCES `employee_sanctions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `employee_salary_history`
--
ALTER TABLE `employee_salary_history`
  ADD CONSTRAINT `employee_salary_history_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE;

--
-- Contraintes pour la table `employee_sanctions`
--
ALTER TABLE `employee_sanctions`
  ADD CONSTRAINT `employee_sanctions_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sanction_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `employee_questionnaires` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `formation_participants`
--
ALTER TABLE `formation_participants`
  ADD CONSTRAINT `fk_formation_participants_employee` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_formation_participants_formation` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `leave_adjustments`
--
ALTER TABLE `leave_adjustments`
  ADD CONSTRAINT `leave_adjustments_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`);

--
-- Contraintes pour la table `leave_balance_history`
--
ALTER TABLE `leave_balance_history`
  ADD CONSTRAINT `leave_balance_history_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`);

--
-- Contraintes pour la table `leave_balance_updates`
--
ALTER TABLE `leave_balance_updates`
  ADD CONSTRAINT `leave_balance_updates_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`);

--
-- Contraintes pour la table `leave_pauses`
--
ALTER TABLE `leave_pauses`
  ADD CONSTRAINT `leave_pauses_ibfk_1` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`);

--
-- Contraintes pour la table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `mission_orders`
--
ALTER TABLE `mission_orders`
  ADD CONSTRAINT `fk_mission_approved_by` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mission_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mission_employee_nin` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE;

--
-- Contraintes pour la table `monthly_leave_accruals_log`
--
ALTER TABLE `monthly_leave_accruals_log`
  ADD CONSTRAINT `fk_accrual_user` FOREIGN KEY (`executed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `persistent_logins`
--
ALTER TABLE `persistent_logins`
  ADD CONSTRAINT `persistent_logins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `postes`
--
ALTER TABLE `postes`
  ADD CONSTRAINT `postes_ibfk_1` FOREIGN KEY (`departement_id`) REFERENCES `departements` (`id`);

--
-- Contraintes pour la table `trial_periods`
--
ALTER TABLE `trial_periods`
  ADD CONSTRAINT `fk_trial_employee_nin` FOREIGN KEY (`employee_nin`) REFERENCES `employees` (`nin`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_logins`
--
ALTER TABLE `user_logins`
  ADD CONSTRAINT `fk_user_logins` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;