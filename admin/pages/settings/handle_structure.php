<?php
/**
 * Page: Company Settings
 *
 * Manages all global settings for the company, including legal information,
 * HR policies, SMTP, and organizational structure.
 */

// =========================================================================
// == BOOTSTRAP & SECURITY
// =========================================================================
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdmin();

$action = $_POST['action'] ?? '';

try {
    // Gérer les départements
    if ($action === 'add_department') {
        $stmt = $db->prepare("INSERT INTO departments (name, description, manager_nin) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['manager_nin']]);
        $_SESSION['success'] = "Département ajouté.";
    } elseif ($action === 'edit_department') {
        $stmt = $db->prepare("UPDATE departments SET name = ?, description = ?, manager_nin = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['description'], $_POST['manager_nin'], $_POST['department_id']]);
        $_SESSION['success'] = "Département mis à jour.";
    } 
    // Gérer les postes
    elseif ($action === 'add_position') {
        $stmt = $db->prepare("INSERT INTO positions (title, department_id, base_salary, job_description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['department_id'] ?: null, $_POST['base_salary'] ?: null, $_POST['job_description']]);
         $_SESSION['success'] = "Poste ajouté.";
    } elseif ($action === 'edit_position') {
        $stmt = $db->prepare("UPDATE positions SET title = ?, department_id = ?, base_salary = ?, job_description = ? WHERE id = ?");
        $stmt->execute([$_POST['title'], $_POST['department_id'] ?: null, $_POST['base_salary'] ?: null, $_POST['job_description'], $_POST['position_id']]);
        $_SESSION['success'] = "Poste mis à jour.";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
}

header('Location: structure.php');
exit();
?>
