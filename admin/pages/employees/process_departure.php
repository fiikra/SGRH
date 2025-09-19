<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

redirectIfNotHR();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nin = sanitize($_POST['employee_nin'] ?? '');
    $departure_date = sanitize($_POST['departure_date'] ?? '');
    $departure_reason = sanitize($_POST['departure_reason'] ?? '');
    $custom_reason = sanitize($_POST['custom_reason'] ?? '');

    // Construct the redirect URL for errors first
    $redirect_url = route('employees_view', ['nin' => $nin]);

    if (empty($nin) || empty($departure_date) || empty($departure_reason)) {
        $_SESSION['error'] = 'Tous les champs requis ne sont pas remplis.';
        header("Location: " . $redirect_url);
        exit();
    }

    $reason_to_store = ($departure_reason === 'Autre' && !empty($custom_reason)) ? $custom_reason : $departure_reason;

    try {
        $db->beginTransaction();

        $sql = "UPDATE employees SET 
                    status = 'inactive', 
                    departure_date = :departure_date, 
                    departure_reason = :departure_reason 
                WHERE nin = :nin";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':departure_date' => $departure_date,
            ':departure_reason' => $reason_to_store,
            ':nin' => $nin
        ]);

        $db->commit();
        $_SESSION['success'] = "Le départ de l'employé a été enregistré avec succès.";

    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur lors de l'enregistrement du départ : " . $e->getMessage();
    }

    header("Location: " . $redirect_url);
    exit();

} else {
    // Redirect if accessed via GET
    header("Location: " . route('employees_list'));
    exit();
}