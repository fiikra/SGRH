<?php
// --- Security & Initialization ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotHR();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect to dashboard if accessed directly via GET
    header("Location: " . route('dashboard'));
    exit;
}

// --- Input Processing ---
$nin = sanitize($_POST['employee_nin']);
$decision_type = sanitize($_POST['decision_type']);
$new_position = sanitize($_POST['new_position'] ?? null);
$new_salary = filter_input(INPUT_POST, 'new_salary', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$effective_date = sanitize($_POST['effective_date']);
$reason = sanitize($_POST['reason'] ?? 'Décision de carrière');

if (empty($nin) || empty($decision_type) || empty($effective_date)) {
    flash('error', "Tous les champs requis ne sont pas remplis.");
    header("Location: " . route('employees_view', ['nin' => $nin]) . "#career");
    exit;
}

// --- Database Transaction ---
$db->beginTransaction();
try {
    // 1. Generate the unique reference number.
    $ref = generate_reference_number('DCS', 'promotion_decisions', 'reference_number', $db);

    // 2. Insert the complete decision record.
    $stmt_decision = $db->prepare(
        "INSERT INTO promotion_decisions (employee_nin, reference_number, decision_type, old_position, old_salary, new_position, new_salary, effective_date, reason, issue_date, created_by)
         SELECT ?, ?, ?, position, salary, ?, ?, ?, ?, CURDATE(), ? FROM employees WHERE nin = ?"
    );
    $stmt_decision->execute([$nin, $ref, $decision_type, $new_position, $new_salary, $effective_date, $reason, $_SESSION['user_id'], $nin]);
    $decision_id = $db->lastInsertId();
    if (!$decision_id) {
        throw new Exception("La création de l'enregistrement de la décision a échoué.");
    }
    
    // 3. Prepare and execute updates for the main employee table and histories.
    $updates = [];
    $params = [];

    if ($decision_type === 'promotion_only' || $decision_type === 'promotion_salary') {
        if (empty($new_position)) throw new Exception("Le nouveau poste est requis pour une promotion.");
        $updates[] = "position = ?";
        $params[] = $new_position;
    }

    if ($decision_type === 'salary_only' || $decision_type === 'promotion_salary') {
        if (empty($new_salary) || $new_salary <= 0) throw new Exception("Un nouveau salaire valide est requis.");
        $updates[] = "salary = ?";
        $params[] = $new_salary;
    }

    if (!empty($updates)) {
        $sql = "UPDATE employees SET " . implode(', ', $updates) . " WHERE nin = ?";
        $params[] = $nin;
        $db->prepare($sql)->execute($params);
    }
    
    // 4. Commit all database changes.
    $db->commit();

    flash('success', "La décision a été enregistrée avec succès.");
    
    // --- JavaScript Redirection Logic ---
    // Generate the correct URLs using the route() function
    $pdf_url = route('promotions_generate_decision_pdf', ['id' => $decision_id]);
    $redirect_url = route('employees_view', ['nin' => $nin]) . '#decisions';

    // Output JavaScript to the browser to perform the two actions
    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head><title>Traitement en cours...</title></head>
    <body>
        <p>La décision a été enregistrée. Le PDF s'ouvre dans un nouvel onglet et vous serez redirigé...</p>
        <script>
            // Open the PDF in a new tab
            window.open('$pdf_url', '_blank');

            // Redirect the original tab back to the employee's profile
            window.location.href = '$redirect_url';
        </script>
    </body>
    </html>
HTML;
    exit;

} catch (Exception $e) {
    $db->rollBack();
    flash('error', "Erreur : " . $e->getMessage());
    header("Location: " . route('employees_view', ['nin' => $nin]) . "#decisions");
    exit;
}