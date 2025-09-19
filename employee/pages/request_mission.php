<?php
// --- Security Headers: Set before any output ---
header('X-Frame-Options: DENY'); // Prevent clickjacking
header('X-Content-Type-Options: nosniff'); // Prevent MIME sniffing
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net;");
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload'); // Only works over HTTPS
/*
// --- Force HTTPS if not already (optional, best to do in server config) ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: $httpsUrl", true, 301);
    exit();
}
*/
// --- Harden Session Handling ---
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '', // Set to your production domain if needed
    'secure' => true,   // Only send cookie over HTTPS
    'httponly' => true, // JavaScript can't access
    'samesite' => 'Strict' // CSRF protection
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
session_regenerate_id(true);
// --- Generic error handler (don't leak errors to users in production) ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
// --- Prevent session fixation ---
require_once '../config/config.php';
require_once '../includes/auth.php'; 
require_once '../includes/functions.php';
require_once '../includes/flash.php';
require_once '../includes/flash_messages.php';

// Ensure the user is logged in and is an employee
if (!isLoggedIn()) {
    $_SESSION['error'] = "Vous devez être connecté pour accéder à cette page.";
    header("Location: " . APP_LINK . "/auth/login.php");
    exit();
}
// Add a check if it's specifically an employee, not admin/HR, if needed
// For example, if you have an isEmployee() function:
// if (!isEmployee()) {
//     $_SESSION['error'] = "Accès réservé aux employés.";
//     header("Location: " . APP_LINK . "/index.php"); // Or appropriate redirect
//     exit();
// }


$pageTitle = "Demander un Ordre de Mission";
$errors = [];
$employee_nin_session = $_SESSION['user_nin'] ?? ''; 
$current_user_id_session = $_SESSION['user_id'] ?? null;

if (empty($employee_nin_session) || empty($current_user_id_session)) {
    $_SESSION['error'] = "Informations utilisateur manquantes dans la session. Veuillez vous reconnecter.";
    // Redirect or handle error, for now, we allow form display but submission will fail if NIN is missing.
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($employee_nin_session) || empty($current_user_id_session)) {
        $errors[] = "Votre session a peut-être expiré ou vos informations sont incomplètes. Veuillez vous reconnecter.";
    } else {
        $destination = sanitize($_POST['destination'] ?? '');
        $departure_date_str = sanitize($_POST['departure_date'] ?? '');
        $return_date_str = sanitize($_POST['return_date'] ?? '');
        $objective = sanitize($_POST['objective'] ?? '');
        $vehicle_registration = sanitize($_POST['vehicle_registration'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        if (empty($destination)) $errors[] = "La destination est requise.";
        if (empty($departure_date_str)) $errors[] = "La date de départ est requise.";
        if (empty($return_date_str)) $errors[] = "La date de retour est requise.";
        if (empty($objective)) $errors[] = "L'objectif de la mission est requis.";

        $departure_date = null;
        $return_date = null;

        if (!empty($departure_date_str)) {
            try { $departure_date = new DateTime($departure_date_str); } 
            catch (Exception $e) { $errors[] = "Format de date de départ invalide."; }
        }
        if (!empty($return_date_str)) {
            try { $return_date = new DateTime($return_date_str); } 
            catch (Exception $e) { $errors[] = "Format de date de retour invalide."; }
        }

        if ($departure_date && $return_date && $departure_date >= $return_date) {
            $errors[] = "La date de départ doit être antérieure à la date de retour.";
        }
        
        if (empty($errors)) {
            try {
                $prefix = "OM"; // RMO for "Request Mission Order" or use "OM"
                $current_year_for_ref = date('Y'); 

                $stmt_max_seq = $db->prepare(
                    "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(reference_number, '-', 2), '-', -1) AS UNSIGNED)) 
                     FROM mission_orders 
                     WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(reference_number, '-', 3), '-', -1) = :current_year
                     AND reference_number LIKE :prefix_pattern"
                );
                $stmt_max_seq->execute([':current_year' => $current_year_for_ref, ':prefix_pattern' => $prefix . "-%"]);
                $max_seq_this_year = $stmt_max_seq->fetchColumn();
                
                $next_seq_num = ($max_seq_this_year !== null) ? (int)$max_seq_this_year + 1 : 1;
                $sequential_part = sprintf("%04d", $next_seq_num); 

                $random_part = generateRandomAlphanumericString(4);
                $reference_number = $prefix . "-" . $sequential_part . "-" . $current_year_for_ref . "-" . $random_part;

                $db->beginTransaction();

                $sql = "INSERT INTO mission_orders 
                            (employee_nin, reference_number, destination, departure_date, return_date, objective, vehicle_registration, notes, status, created_by_user_id)
                        VALUES 
                            (:employee_nin, :reference_number, :destination, :departure_date, :return_date, :objective, :vehicle_registration, :notes, :status, :created_by_user_id)";
                
                $stmt = $db->prepare($sql);
                $params = [
                    ':employee_nin' => $employee_nin_session,
                    ':reference_number' => $reference_number,
                    ':destination' => $destination,
                    ':departure_date' => $departure_date->format('Y-m-d H:i:s'),
                    ':return_date' => $return_date->format('Y-m-d H:i:s'),
                    ':objective' => $objective,
                    ':vehicle_registration' => !empty($vehicle_registration) ? $vehicle_registration : null,
                    ':notes' => $notes,
                    ':status' => 'pending', 
                    ':created_by_user_id' => $current_user_id_session
                ];
                
                $stmt->execute($params);
                $db->commit();
                $_SESSION['success'] = "Votre demande d'ordre de mission (N°{$reference_number}) a été soumise avec succès et est en attente de validation.";
                header("Location: my_missions.php"); 
                exit();

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $errors[] = "Erreur lors de la soumission: " . $e->getMessage();
                error_log("Employee Mission Request Error: " . $e->getMessage());
            }
        }
    }
}

include '../includes/header.php'; // Ensure this header is appropriate for employee section
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-briefcase me-2"></i><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="my_missions.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i> Mes Ordres de Mission</a>
    </div>

    <?php display_flash_messages(); // Use your flash message display function ?>

    <?php if (empty($employee_nin_session)): ?>
        <div class="alert alert-danger">Vos informations d'identification (NIN) n'ont pu être chargées. Veuillez vous reconnecter ou contacter l'administration.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="request_mission.php">
                  <?php csrf_input(); // ✅ Correct: Just call the function here ?>
                <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="destination" class="form-label">Destination(s)*</label>
                            <input type="text" class="form-control" id="destination" name="destination" value="<?= htmlspecialchars($_POST['destination'] ?? '') ?>" placeholder="Ex: Alger - Sétif - Alger" required>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="vehicle_registration" class="form-label">Immatriculation Véhicule (si applicable)</label>
                            <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" value="<?= htmlspecialchars($_POST['vehicle_registration'] ?? '') ?>" placeholder="Ex: 12345-116-01">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departure_date" class="form-label">Date et Heure de Départ*</label>
                            <input type="datetime-local" class="form-control" id="departure_date" name="departure_date" value="<?= htmlspecialchars($_POST['departure_date'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="return_date" class="form-label">Date et Heure de Retour*</label>
                            <input type="datetime-local" class="form-control" id="return_date" name="return_date" value="<?= htmlspecialchars($_POST['return_date'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="objective" class="form-label">Objectif de la Mission*</label>
                        <textarea class="form-control" id="objective" name="objective" rows="4" required><?= htmlspecialchars($_POST['objective'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes / Informations complémentaires (Optionnel)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Soumettre la Demande</button>
                    <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-x-circle me-2"></i>Annuler</a>
                </form>
            </div>
        </div>
   <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>