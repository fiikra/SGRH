<?php
// --- Security Headers: Set before any output ---
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}


redirectIfNotHR();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de congé invalide.";
    header("Location: requests.php");
    exit();
}
$leave_id = (int)$_GET['id'];

$db->beginTransaction();

try {
    $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ? FOR UPDATE");
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) { throw new Exception("Demande de congé non trouvée."); }
    
    // --- CONTRÔLES CRUCIAUX ---
    // Remove the restriction for paused leaves
    if ($leave['status'] === 'cancelled') { 
        throw new Exception("Ce congé est déjà annulé."); 
    }
    
    $today = new DateTime();
    $start_date = new DateTime($leave['start_date']);
    
    // Remove or modify this restriction to allow cancellation of started leaves
    // if ($today >= $start_date) {
    //     throw new Exception("Impossible d'annuler un congé déjà commencé ou terminé. Utilisez la fonction 'Interrompre'.");
    // }

    // Marquer le congé comme annulé
    $stmt_cancel = $db->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
    $stmt_cancel->execute([$leave_id]);
    
    // Restituer la totalité des soldes débités
    $restoration_log = "";
    $days_to_restore = (float)$leave['use_annuel'] + (float)$leave['use_reliquat'] + (float)$leave['use_recup'];
    $employee_nin = $leave['employee_nin'];

    // 1. Restore reliquat first (if any was used)
    if (!empty($leave['use_reliquat']) && $days_to_restore > 0) {
        $restore = min((float)$leave['use_reliquat'], $days_to_restore);
        $db->prepare("UPDATE employees SET remaining_leave_balance = remaining_leave_balance + ? WHERE nin = ?")->execute([$restore, $employee_nin]);
        $days_to_restore -= $restore;
        $restoration_log .= "Reliquat: +$restore; ";
    }

    // 2. Restore recup (if any was used)
    if (!empty($leave['use_recup']) && $days_to_restore > 0) {
        $restore = min((float)$leave['use_recup'], $days_to_restore);
        $recupRows = $db->prepare("SELECT id FROM employee_recup_days WHERE employee_nin = ? AND status = 'taked' ORDER BY year, month, id ASC");
        $recupRows->execute([$employee_nin]);
        $recupRow = $recupRows->fetch(PDO::FETCH_ASSOC);
        if ($recupRow) {
            $db->prepare("UPDATE employee_recup_days SET nb_jours = nb_jours + ?, status = 'not_taked' WHERE id = ?")->execute([$restore, $recupRow['id']]);
        } else {
            $db->prepare("INSERT INTO employee_recup_days (employee_nin, year, month, nb_jours, status) VALUES (?, YEAR(CURDATE()), MONTH(CURDATE()), ?, 'not_taked')")->execute([$employee_nin, $restore]);
        }
        $days_to_restore -= $restore;
        $restoration_log .= "Recup: +$restore; ";
    }

    // 3. Restore annuel
    if (!empty($leave['use_annuel']) && $days_to_restore > 0) {
        $restore = min((float)$leave['use_annuel'], $days_to_restore);
        $db->prepare("UPDATE employees SET annual_leave_balance = annual_leave_balance + ? WHERE nin = ?")->execute([$restore, $employee_nin]);
        $days_to_restore -= $restore;
        $restoration_log .= "Annuel: +$restore; ";
    }

    $db->commit();
    $_SESSION['success'] = "Congé annulé avec succès. Solde restitué : " . ($restoration_log ?: "Aucun");

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
}

header("Location: " . route('leave_view', ['id' => $leave_id]));
exit();
?>
