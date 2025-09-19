<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

// --- Request Method Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . route('employees_list'));
    exit();
}

// --- Input Sanitization & Processing ---
$nin = sanitize($_POST['employee_nin'] ?? '');
$questionnaire_type = sanitize($_POST['questionnaire_type'] ?? '');
$issue_date = sanitize($_POST['issue_date'] ?? '');
$subject = sanitize($_POST['subject'] ?? '');
$questions = $_POST['questions'] ?? [];
$user_id = $_SESSION['user_id'];

$redirect_url = route('employees_view', ['nin' => $nin]) . "#questionnaires";

// --- Form Validation ---
if (empty($nin) || empty($questionnaire_type) || empty($issue_date) || empty($subject) || empty($questions) || empty(array_filter($questions))) {
    flash('error', 'Tous les champs marqués d\'un astérisque (*) et au moins une question sont requis.');
    header("Location: " . $redirect_url);
    exit();
}

$questions_json = json_encode($questions, JSON_UNESCAPED_UNICODE);
if ($questions_json === false) {
    flash('error', 'Erreur lors de l\'encodage des questions au format JSON.');
    header("Location: " . $redirect_url);
    exit();
}

// --- Auto-generate Reference Number ---
try {
    $stmt_last_num = $db->query("SELECT MAX(CAST(SUBSTRING(SUBSTRING_INDEX(reference_number, '-', 1), 4) AS UNSIGNED)) as last_num FROM employee_questionnaires");
    $last_num_data = $stmt_last_num->fetch(PDO::FETCH_ASSOC);
    $next_num = ($last_num_data['last_num'] ?? 0) + 1;

    $padded_num = str_pad($next_num, 4, '0', STR_PAD_LEFT);
    $year = date('Y');
    $random_chars = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);
    $reference_number = "QST" . $padded_num . "-" . $year . "-" . $random_chars;

} catch (PDOException $e) {
    error_log("Reference number generation error: " . $e->getMessage());
    flash('error', 'Erreur lors de la génération du numéro de référence.');
    header("Location: " . $redirect_url);
    exit();
}

// --- Database Interaction ---
try {
    $sql = "INSERT INTO employee_questionnaires 
                (reference_number, employee_nin, questionnaire_type, issue_date, subject, questions, status, created_by)
            VALUES 
                (:reference_number, :nin, :type, :issue_date, :subject, :questions, 'pending_response', :user_id)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':reference_number' => $reference_number,
        ':nin' => $nin,
        ':type' => $questionnaire_type,
        ':issue_date' => $issue_date,
        ':subject' => $subject,
        ':questions' => $questions_json,
        ':user_id' => $user_id
    ]);

    flash('success', 'Le questionnaire a été généré et enregistré avec succès. Réf: ' . htmlspecialchars($reference_number));

} catch (PDOException $e) {
    error_log("Questionnaire creation error: " . $e->getMessage());
    flash('error', 'Erreur lors de la création du questionnaire.');
}

// --- Final Redirect ---
header("Location: " . $redirect_url);
exit();