<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    exit('Accès direct non autorisé');
}
redirectIfNotHR();

// --- Request Method Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . route('employees_list'));
    exit;
}

// --- Input Sanitization & Validation ---
$nin = sanitize($_POST['employee_nin'] ?? '');
$decision = sanitize($_POST['decision'] ?? '');
$renewal_months = (int)($_POST['renewal_duration_months'] ?? 0);
$renew_period_text = sanitize($_POST['renew_period'] ?? ''); // e.g. "3 mois"

$redirect_url = route('employees_view', ['nin' => $nin]);

if (empty($nin) || empty($decision)) {
    flash('error', "Données de décision manquantes.");
    header("Location: " . $redirect_url);
    exit;
}
if ($decision === 'renew' && $renewal_months <= 0) {
    flash('error', "La durée de renouvellement est invalide.");
    header("Location: " . $redirect_url);
    exit;
}

// --- Database Transaction ---
$db->beginTransaction();
try {
    $issuerId = $_SESSION['user_id'] ?? null;
    
    // 1. Generate a unique reference for the notification
    $ref = generate_reference_number('NT', 'trial_notifications', 'reference_number', $db);

    // 2. Perform the action based on the decision
    if ($decision === 'confirm') {
        // Remove trial status from employee
        $stmt = $db->prepare("UPDATE employees SET on_trial = 0, trial_end_date = NULL WHERE nin = ?");
        $stmt->execute([$nin]);
        flash('success', "L'employé a été confirmé avec succès.");

    } elseif ($decision === 'renew') {
        // Calculate the new trial end date and update the employee
        $new_end_date = (new DateTime())
            ->modify("+$renewal_months months")
            ->format('Y-m-d');
        $stmt = $db->prepare("UPDATE employees SET trial_end_date = ? WHERE nin = ?");
        $stmt->execute([$new_end_date, $nin]);
        flash('success', "La période d'essai a été renouvelée jusqu'au " . formatDate($new_end_date) . ".");
        
    } elseif ($decision === 'terminate') {
        // Make the employee inactive
        $stmt = $db->prepare("UPDATE employees SET status = 'inactive', on_trial = 0, departure_date = CURDATE(), departure_reason = 'Période d\'essai non concluante' WHERE nin = ?");
        $stmt->execute([$nin]);
        flash('success', "Le contrat de l'employé a été terminé.");
    }

    // 3. Record the notification event in the history
    $stmtNotif = $db->prepare(
        "INSERT INTO trial_notifications (employee_nin, reference_number, issue_date, decision, created_by, renew_period) 
         VALUES (?, ?, NOW(), ?, ?, ?)"
    );
    $stmtNotif->execute([
        $nin, 
        $ref, 
        $decision, 
        $issuerId, 
        ($decision === 'renew' ? $renew_period_text : null)
    ]);

    // If everything succeeded, commit the changes
    $db->commit();
    
    // Redirect to the employee view, focusing on the notifications tab
    header("Location: " . $redirect_url . "#notifications");
    exit;

} catch (Exception $e) {
    $db->rollBack();
    error_log("Trial decision processing error: " . $e->getMessage());
    flash('error', "Une erreur de base de données est survenue: " . $e->getMessage());
    header("Location: " . $redirect_url);
    exit;
}
?>