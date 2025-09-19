<?php
if (!defined('APP_SECURE_INCLUDE')) { http_response_code(403); die('Direct access not allowed.'); }
redirectIfNotAdminOrHR();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? ''); // Get the new subject field
    $trainer = trim($_POST['trainer_name'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');

    if (!empty($title) && !empty($start_date) && !empty($end_date)) {
        // --- Generate Unique Formation Code ---
        $year = date('Y');
        $stmt_last = $db->prepare("SELECT reference_number FROM formations WHERE reference_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt_last->execute(["FR%-$year"]);
        $last_ref = $stmt_last->fetchColumn();
        
        $new_num = 1;
        if ($last_ref) {
            $num_part = (int)substr($last_ref, 2, 4);
            $new_num = $num_part + 1;
        }
        $reference_number = 'FR' . str_pad($new_num, 4, '0', STR_PAD_LEFT) . '-' . $year;
        // --- End Generation ---

        $stmt = $db->prepare(
            "INSERT INTO formations (reference_number, title, subject, trainer_name, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$reference_number, $title, $subject, $trainer, $start_date, $end_date]);
        
        $formationId = $db->lastInsertId();
        flash('success', 'La formation a été créée avec succès ! Vous pouvez maintenant y ajouter des participants.');
        header('Location: index.php?route=formations_view&id=' . $formationId);
        exit;
    } else {
        flash('danger', 'Veuillez remplir tous les champs obligatoires.');
    }
}

$pageTitle = "Créer une Nouvelle Formation";
include __DIR__ . '../../../../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4"><?= htmlspecialchars($pageTitle) ?></h1>
    <?php display_flash_messages(); ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                <div class="mb-3">
                    <label for="title" class="form-label">Titre de la Formation <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>

                <div class="mb-3">
                    <label for="subject" class="form-label">Sujet / Description de la Formation</label>
                    <textarea class="form-control" id="subject" name="subject" rows="4"></textarea>
                </div>

                <div class="mb-3">
                    <label for="trainer_name" class="form-label">Nom du Formateur ou de l'École</label>
                    <input type="text" class="form-control" id="trainer_name" name="trainer_name">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">Date de Début <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">Date de Fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                </div>
                <hr>
                <button type="submit" class="btn btn-primary">Créer et Continuer</button>
                <a href="index.php?route=formations_list" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../includes/footer.php'; ?>