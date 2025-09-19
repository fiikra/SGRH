<?php
// --- Production Error Handling ---
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// Hide E_DEPRECATED notices, but show all other errors
error_reporting(E_ALL & ~E_DEPRECATED);
//security headers
require_once __DIR__ . '../../includes/sheader.php'; 
// --- Core Application Files ---
require_once __DIR__ . '../../init.php';
require_once __DIR__ . '../../config/config.php';
require_once __DIR__ . '../../includes/auth.php';
require_once __DIR__ . '../../includes/functions.php';
require_once __DIR__ . '../../lib/tcpdf/tcpdf.php';
require_once __DIR__ . '../../includes/flash.php';
require_once __DIR__ . '../../includes/flash_messages.php';
require_once __DIR__ . '../../includes/leave_types.php';

// --- Route Definitions ---
$routes = [
    // Your full list of routes here...
    'dashboard' => 'dashboard.php',
    'map' => 'map.php',
    'leave_approval_log' => 'leave_approval_log.txt',
    'leave_system_updates_log' => 'leave_system_updates_log.txt',
    'leave_accrual_log_details' => 'leave/leave_accrual_log_details.php',
    'leave_accrual_period_details' => 'leave/leave_accrual_period_details.php',
    'update_system' => 'update_system.php',

      // Attendance (UPDATED)
    // --- Routes de Présence (Structure MVC) ---

    // Affiche le journal de présence détaillé (anciennement attendance_log.php)
    'attendance_log' => 'controller/attendance_log/view.php',
    
    // Gère la soumission du formulaire de mise à jour (NOUVELLE ROUTE)
    'attendance_log_update' => 'controller/attendance_log/update.php',

    // Affiche la page de gestion et d'importation (anciennement manage_attendance.php)
    'attendance_manage' => 'controller/manage_attendance/view.php',

    // Gère l'importation du fichier Excel (anciennement upload_attendance.php)
    'attendance_upload' => 'controller/manage_attendance/upload.php',

    // Affiche la page du kiosque de pointage (anciennement scanner.php)
    'attendance_scanner' => 'controller/scanner/kiosk.php',

    // API pour traiter un scan (anciennement scan_handler.php)
    'attendance_scan_handler' => 'controller/scanner/handler.php',
    
    // API pour le flux de données en direct du kiosque (NOUVELLE ROUTE)
    'scanner_feed' => 'controller/scanner/feed.php',


    // --- Routes qui n'ont pas encore été refactorisées ---
    // Celles-ci pointent toujours vers les anciens fichiers.
    // Idéalement, elles devraient aussi être migrées vers une structure MVC.
    'attendance_export_pdf' => 'attendance/export_attendance_pdf.php',
    'attendance_generate_template' => 'attendance/generate_template.php',
    'attendance_history' => 'attendance/history.php',



     // Formations (ADD THESE NEW ROUTES)
    'formations_list' => 'formations/index.php',
    'formations_add' => 'formations/add.php',
    'formations_view' => 'formations/view.php',
    'formations_print_catalog' => 'formations/print_catalog.php',

    // Contracts
    'contracts_add' => 'contracts/add.php',
    'contracts_generate_pdf' => 'contracts/generate_contract_pdf.php',
    'contracts_generate_pv' => 'contracts/generate_pv_installation_pdf.php',
    'contracts_index' => 'contracts/index.php',

      'employees_list' => 'controller/employees/list.php',
    'employees_add' => 'controller/employees/add.php',
    'employees_edit' => 'controller/employees/edit.php',
    'employees_view' => 'controller/employees/view_employee.php', // Controller for the main profile view
     'employees_renew_contract' => 'controller/employees/renew_contract.php', // <-- AJOUTER CETTE LIGNE
    

    // Employee document management
    'employees_documents' => 'controller/employees/documents.php',
    'employees_document_delete' => 'controller/employees/document_delete.php', // New route for deleting a document

    // Processing and action routes (no view)
    'employees_delete' => 'controller/employees/delete.php',
    'employees_process_departure' => 'controller/employees/process_departure.php',

    // File generation routes
    'employees_badge' => 'controller/employees/badge.php',
    'employees_generate_pdf' => 'controller/employees/generate_pdf.php',

    // Public profile route
    'employees_public' => 'controller/employees/public.php',
    // Leave
    'leave_Leave_certificate' => 'leave/Leave_certificate.php',
    'leave_Leave_certificate_duplicata' => 'leave/Leave_certificate_duplicata.php',
    'leave_List_sick_leave' => 'leave/List_sick_leave.php',
    'leave_add' => 'leave/add.php',
    'leave_add_maternity_leave' => 'leave/add_maternity_leave.php',
    'maternity_leave' => 'leave/List_sick_meternity.php',
    'leave_add_sick_leave' => 'leave/add_sick_leave.php',
    'leave_adjust_leave' => 'leave/adjust_leave.php',
    'leave_approve' => 'leave/approve.php',
    'leave_cancel_leave' => 'leave/cancel_leave.php',
    'leave_emp_on_leave' => 'leave/emp_on_leave.php',
    'leave_emp_reliquat' => 'leave/emp_reliquat.php',
    'leave_leave_historique' => 'leave/leave_historique.php',
    'leave_leave_logs' => 'leave/leave_logs.php',
    'leave_manual_resume' => 'leave/manual_resume.php',
    'leave_pause_leave' => 'leave/pause_leave.php',
    'leave_report' => 'leave/report.php',
    'leave_requests' => 'leave/requests.php',
    'leave_resume_extend_enddate' => 'leave/resume_extend_enddate.php',
    'leave_resume_keep_enddate' => 'leave/resume_keep_enddate.php',
    'leave_seek_leave_analytics' => 'leave/seek_leave_analytics.php',
    'leave_view' => 'leave/view.php',
    'leaves_download_justification' => 'leave/download_justification.php',
      // This route is for the DataTables server-side processing
    'ajax_leave_history' => 'leave/ajax_leave_history.php',

    // Missions
    'missions_add_mission' => 'missions/add_mission.php',
    'missions_edit_mission' => 'missions/edit_mission.php',
    'missions_generate_mission_order_pdf' => 'missions/generate_mission_order_pdf.php',
    'missions_list_missions' => 'missions/list_missions.php',
    'missions_view_mission' => 'missions/view_mission.php',

    // Promotions
    'promotions_generate_decision_pdf' => 'promotions/generate_decision_pdf.php',
    'promotions_handle_decision' => 'promotions/handle_decision.php',
    'promotions_index' => 'promotions/index.php',
     'promotions_verify_decision' => 'promotions/verify_decision.php', // <-- ADD THIS LINE
    

    // Questionnaires
    'questionnaires_add_handler' => 'questionnaires/add_handler.php',
    'questionnaires_generate_questionnaire_pdf' => 'questionnaires/generate_questionnaire_pdf.php',
    'questionnaires_questionnaire_handler' => 'questionnaires/questionnaire_handler.php',
    'questionnaires_update_handler' => 'questionnaires/update_handler.php',
    'questionnaires_view_questionnaire' => 'questionnaires/view_questionnaire.php',
    'questionnaires_index' => 'questionnaires/index.php',

    // Reports
    'reports_certificates' => 'reports/certificates.php',
    'reports_download' => 'reports/download.php',
    'reports_export_excel' => 'reports/export_excel.php',
    //generation attestation de travaille et attestation de salaire
    'reports_generate' => 'reports/generate.php',
    'reports_generate_handler' => 'reports/generate_handler.php',
    'reports_view_certificate' => 'reports/view_certificate.php',
     'reports_history' => 'reports/history.php',
    'reports_generate_attendance_report_pdf' => 'reports/generate_attendance_report_pdf.php',
    'reports_decesion' => 'promotions/index.php',
    'reports_trial_notifications_list' => 'reports/trial_notifications_list.php',

    // Sanctions
    'sanctions_add_handler' => 'sanctions/add_handler.php',
    'sanctions_generate_notification_pdf' => 'sanctions/generate_notification_pdf.php',
    'sanctions_view_sanction' => 'sanctions/view_sanction.php',
    'sanctions_index' => 'sanctions/index.php',

    // Settings
    'settings_company' => 'settings/company.php',
    'settings_handle_structure' => 'settings/handle_structure.php',
    'settings_index' => 'settings/index.php',
    'settings_leave_history' => 'settings/leave_history.php',
    'settings_personnel_organisation' => 'settings/personnel_organisation.php',
    'settings_personnel_settings' => 'settings/personnel_settings.php',
    'settings_settings_smtp' => 'settings/settings_smtp.php',
    'settings_ajax_test_smtp' => 'settings/ajax_test_smtp.php',
    'settings_structure' => 'settings/structure.php',

    // Trial Notifications
    'trial_notifications_generate_notification_pdf' => 'trial_notifications/generate_notification_pdf.php',
    'trial_notifications_index' => 'trial_notifications/index.php',
    'trial_notifications_process_trial_decision' => 'trial_notifications/process_trial_decision.php',
    'trial_notifications_trial_notification_view' => 'trial_notifications/trial_notification_view.php',

    // Users
    'users_edit' => 'users/edit.php',
    'users_index' => 'users/index.php',
    'users_quick_create' => 'users/quick_create.php',
    'users_register' => 'users/register.php',
    'users_reset_password' => 'users/reset_password.php',
    'users_toggle_status' => 'users/toggle_status.php',
    'users_setup_otp_app' => 'users/setup_otp_app.php', // Add this line
    'users_users' => 'users/users.php',
    'users_view' => 'users/view.php',
];


// --- Routing & Dispatching Logic ---
$route = $_GET['route'] ?? 'update_system';

// **CRITICAL**: Validate that the requested route exists in our defined list.
if (!array_key_exists($route, $routes)) {
    http_response_code(404);
    // Optionally include a nice 404 page
    // require_once __DIR__ . '/pages/404.php';
    die('404 - Page Not Found');
}

// The route is valid, proceed to load the file.
$page = $routes[$route];
$target = __DIR__ . '/pages/' . $page;

// Final check that the file physically exists before including.
if (!file_exists($target)) {
    http_response_code(500);
    // This indicates a server configuration error, not a user error.
    die('500 - Server Error: Route file is missing.');
}

// Load the target page.
require_once $target;