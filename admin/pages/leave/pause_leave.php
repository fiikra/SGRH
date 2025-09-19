<?php
// --- Security Headers: Set before any output ---
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}


redirectIfNotHR();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de congé invalide";
    header("Location: " . route('leave_requests'));
    exit();
}

$leave_id = (int)$_GET['id'];
$pause_start = $_POST['pause_start'] ?? '';
$pause_end = $_POST['pause_end'] ?? '';
$pause_reason = trim($_POST['pause_reason'] ?? '');
$created_by = $_SESSION['user_id'] ?? null;


// Fetch leave to validate dates and type/source
$stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ?");
$stmt->execute([$leave_id]);
$leave = $stmt->fetch();

if (!$leave) {
    $_SESSION['error'] = "Leave not found.";
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}

// Only approved leave can be paused
if ($leave['status'] !== 'approved') {
    $_SESSION['error'] = "Seuls les congés approuvés peuvent être suspendus.";
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}

// Date limits for pausing: from max(leave start, today-2) to min(today, leave end)
$today = date('Y-m-d');
$twoDaysAgo = date('Y-m-d', strtotime('-2 days'));
$minDate = max($leave['start_date'], $twoDaysAgo);
$maxPauseStart = min($today, $leave['end_date']);
$maxPauseEnd = $leave['end_date'];

// Validate input
if (!$pause_start || !$pause_end || !$pause_reason) {
    $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}

if ($pause_start < $minDate || $pause_start > $maxPauseStart) {
    $_SESSION['error'] = "La date de début de la suspension doit être comprise entre $minDate et $maxPauseStart.";
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}

if ($pause_end < $pause_start || $pause_end > $maxPauseEnd) {
    $_SESSION['error'] = "La date de fin de la suspension est invalide.";
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}

// Optional attachment
$attachment_filename = null;
if (!empty($_FILES['pause_attachment']['name'])) {
    $file = $_FILES['pause_attachment'];
    $allowed = ['application/pdf'];
    if (!in_array($file['type'], $allowed)) {
        $_SESSION['error'] = "Seuls les fichiers PDF sont autorisés.";
        header("Location: " . route('leave_view', ['id' => $leave_id]));
        exit();
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newname = 'pause_' . $leave_id . '_' . time() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], '../../uploads/' . $newname);
    $attachment_filename = $newname;
}

// --- SOLD RESTORATION LOGIC ---
try {
    $db->beginTransaction();

    // Insert pause record
    $stmt = $db->prepare("INSERT INTO leave_pauses
        (leave_request_id, pause_start_date, pause_end_date, reason, attachment_filename, created_by)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $leave_id,
        $pause_start,
        $pause_end,
        $pause_reason,
        $attachment_filename,
        $created_by
    ]);

    // Calculate number of paused days (inclusive)
    $pauseStartDate = new DateTime($pause_start);
    $pauseEndDate = new DateTime($pause_end);
    $paused_days = $pauseEndDate->diff($pauseStartDate)->days + 1;

    // Restore to original sources as in cancellation logic
    // (commented out logic can be implemented here if needed)

    // Set leave status to "paused"
    $stmt = $db->prepare("UPDATE leave_requests SET status = 'paused' WHERE id = ?");
    $stmt->execute([$leave_id]);

    $db->commit();
    $_SESSION['success'] = "Suspension du congé enregistrée, soldes rétablis.";
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
    header("Location: " . route('leave_view', ['id' => $leave_id]));
    exit();
}
?>