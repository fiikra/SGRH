<?php
// --- Security Headers: Set before any output ---

if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}
redirectIfNotAdminOrHR();

$pageTitle = "Détails de l'Ordre de Mission";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID d'ordre de mission invalide.";
    header("Location: " . route('missions_list_missions')); // MODIFIED
    exit();
}
$mission_id = (int)$_GET['id'];

// Handle Status Update Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $new_status = '';
    $admin_comment = sanitize($_POST['admin_comment'] ?? '');
    $current_user_id = $_SESSION['user_id'] ?? null;

    switch ($action) {
        case 'approve': $new_status = 'approved'; break;
        case 'reject': $new_status = 'rejected'; break;
        case 'complete': $new_status = 'completed'; break;
        case 'cancel': $new_status = 'cancelled'; break;
        default:
            $_SESSION['error'] = "Action invalide.";
            header("Location: " . route('missions_view_mission', ['id' => $mission_id])); // MODIFIED
            exit();
    }

    try {
        $db->beginTransaction();
        $update_sql = "UPDATE mission_orders SET status = :status, updated_at = NOW()";
        $params = [':status' => $new_status, ':id' => $mission_id];

        if ($action === 'approve') {
            $update_sql .= ", approved_by_user_id = :approved_by, approval_date = NOW()";
            $params[':approved_by'] = $current_user_id;
        }

        if (!empty($admin_comment)) {
            $current_notes_stmt = $db->prepare("SELECT notes FROM mission_orders WHERE id = ?");
            $current_notes_stmt->execute([$mission_id]);
            $current_notes_val = $current_notes_stmt->fetchColumn();
            
            $new_notes = ($current_notes_val ? $current_notes_val . "\n\n" : "") . "Commentaire Admin (" . date('d/m/Y H:i') . " - " . htmlspecialchars($action) . "):\n" . $admin_comment;
            $update_sql .= ", notes = :notes";
            $params[':notes'] = $new_notes;
        }
        
        $update_sql .= " WHERE id = :id";
        $stmt_update = $db->prepare($update_sql);
        $stmt_update->execute($params);
        
        $db->commit();
        $_SESSION['success'] = "Statut de l'ordre de mission mis à jour avec succès à : " . ucfirst($new_status);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = "Erreur lors de la mise à jour du statut: " . $e->getMessage();
        error_log("Mission Status Update Error: " . $e->getMessage());
    }
    header("Location: " . route('missions_view_mission', ['id' => $mission_id])); // MODIFIED
    exit();
}


// Fetch mission order details for display
$stmt = $db->prepare("
    SELECT mo.*, 
           e.first_name as emp_first_name, e.last_name as emp_last_name, e.position as emp_position, e.department as emp_department,
           creator.username as creator_username,
           approver.username as approver_username
    FROM mission_orders mo
    JOIN employees e ON mo.employee_nin = e.nin
    LEFT JOIN users creator ON mo.created_by_user_id = creator.id
    LEFT JOIN users approver ON mo.approved_by_user_id = approver.id
    WHERE mo.id = ?
");
$stmt->execute([$mission_id]);
$mission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mission) {
    $_SESSION['error'] = "Ordre de mission non trouvé.";
    header("Location: " . route('missions_list_missions')); // MODIFIED
    exit();
}

include __DIR__. '../../../../includes/header.php';
?>
<style>
    .info-section h5 { margin-bottom: 0.75rem; color: var(--bs-primary); font-weight:500; }
    .info-section dl dt { font-weight: 500; color: #6c757d; }
    .info-section dl dd { margin-bottom: 0.5rem; }
    .action-buttons .btn { margin-right: 0.5rem; margin-bottom: 0.5rem;}
</style>

<div class="container-fluid mt-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-file-earmark-text-fill me-2"></i><?= htmlspecialchars($pageTitle) ?>: <?= htmlspecialchars($mission['reference_number']) ?>
        </h1>
        <div>
            <a href="<?= route('missions_list_missions') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i> Retour à la Liste</a>
            <a href="<?= route('missions_edit_mission', ['id' => $mission_id]) ?>" class="btn btn-sm btn-outline-warning ms-2"><i class="bi bi-pencil-square me-1"></i> Modifier</a>
            <a href="<?= route('missions_generate_mission_order_pdf', ['id' => $mission_id]) ?>" class="btn btn-sm btn-outline-danger ms-2" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Détails de l'Ordre de Mission</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 info-section mb-3">
                    <h5><i class="bi bi-person-badge me-2"></i>Informations Employé(e)</h5>
                    <dl class="row">
                        <dt class="col-sm-4">Nom Complet:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['emp_first_name'] . ' ' . $mission['emp_last_name']) ?></dd>
                        <dt class="col-sm-4">NIN:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['employee_nin']) ?></dd>
                        <dt class="col-sm-4">Poste:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['emp_position']) ?></dd>
                        <dt class="col-sm-4">Département:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['emp_department']) ?></dd>
                    </dl>
                </div>
                <div class="col-md-6 info-section mb-3">
                    <h5><i class="bi bi-info-circle me-2"></i>Détails de la Mission</h5>
                    <dl class="row">
                        <dt class="col-sm-4">Référence:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['reference_number']) ?></dd>
                        <dt class="col-sm-4">Statut:</dt>
                        <dd class="col-sm-8">
                            <?php
                                $status_badge_class = 'secondary';
                                switch ($mission['status']) {
                                    case 'approved': $status_badge_class = 'success'; break;
                                    case 'pending': $status_badge_class = 'warning text-dark'; break;
                                    case 'rejected': $status_badge_class = 'danger'; break;
                                    case 'completed': $status_badge_class = 'primary'; break;
                                    case 'cancelled': $status_badge_class = 'dark'; break;
                                }
                            ?>
                            <span class="badge bg-<?= $status_badge_class ?>"><?= ucfirst(htmlspecialchars($mission['status'])) ?></span>
                        </dd>
                        <dt class="col-sm-4">Créé par:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['creator_username'] ?? 'N/A') ?> le <?= formatDate($mission['created_at']) ?></dd>
                        <?php if ($mission['status'] === 'approved' && !empty($mission['approver_username'])): ?>
                            <dt class="col-sm-4">Approuvé par:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['approver_username']) ?> le <?= formatDate($mission['approval_date']) ?></dd>
                        <?php elseif ($mission['status'] === 'rejected' && !empty($mission['approved_by_user_id'])): ?>
                             <dt class="col-sm-4">Traité par:</dt><dd class="col-sm-8"><?= htmlspecialchars($mission['approver_username'] ?? 'Admin') ?> le <?= formatDate($mission['approval_date']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            <hr>
            <div class="row info-section">
                <div class="col-md-12 mb-3">
                      <h5><i class="bi bi-geo-alt-fill me-2"></i>Trajet et Objectif</h5>
                      <dl class="row">
                           <dt class="col-sm-3">Destination(s):</dt><dd class="col-sm-9"><?= nl2br(htmlspecialchars($mission['destination'])) ?></dd>
                           <dt class="col-sm-3">Date et Heure de Départ:</dt><dd class="col-sm-9"><?= formatDate($mission['departure_date'], 'd/m/Y à H:i') ?></dd>
                           <dt class="col-sm-3">Date et Heure de Retour:</dt><dd class="col-sm-9"><?= formatDate($mission['return_date'], 'd/m/Y à H:i') ?></dd>
                           <dt class="col-sm-3">Objectif:</dt><dd class="col-sm-9"><?= nl2br(htmlspecialchars($mission['objective'])) ?></dd>
                           <?php if (!empty($mission['vehicle_registration'])): ?>
                               <dt class="col-sm-3">Immatriculation Véhicule:</dt><dd class="col-sm-9"><?= htmlspecialchars($mission['vehicle_registration']) ?></dd>
                           <?php endif; ?>
                           <?php if (!empty($mission['notes'])): ?>
                               <dt class="col-sm-3">Notes:</dt><dd class="col-sm-9"><?= nl2br(htmlspecialchars($mission['notes'])) ?></dd>
                           <?php endif; ?>
                      </dl>
                </div>
            </div>
        </div>
    </div>

    <?php if (in_array($mission['status'], ['pending', 'approved'])): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">Actions Administratives</h6>
        </div>
        <div class="card-body action-buttons">
            <?php if ($mission['status'] === 'pending'): ?>
                <form method="POST" action="<?= route('missions_view_mission', ['id' => $mission_id]) ?>" class="d-inline needs-validation" novalidate>
                    <?php csrf_input(); ?>
                    <div class="mb-3">
                        <label for="admin_comment_approve" class="form-label">Commentaire (pour approbation/rejet)</label>
                        <textarea name="admin_comment" id="admin_comment_approve" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"><i class="bi bi-check-circle-fill me-1"></i> Approuver</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"><i class="bi bi-x-circle-fill me-1"></i> Rejeter</button>
                </form>
            <?php elseif ($mission['status'] === 'approved'): ?>
                 <form method="POST" action="<?= route('missions_view_mission', ['id' => $mission_id]) ?>" class="d-inline needs-validation" novalidate>
                    <?php csrf_input(); ?>
                   <div class="mb-3">
                        <label for="admin_comment_status_change" class="form-label">Commentaire (pour changement de statut)</label>
                        <textarea name="admin_comment" id="admin_comment_status_change" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button type="submit" name="action" value="complete" class="btn btn-primary btn-sm"><i class="bi bi-flag-fill me-1"></i> Marquer comme Terminée</button>
                    <button type="submit" name="action" value="cancel" class="btn btn-warning btn-sm text-dark"><i class="bi bi-calendar-x-fill me-1"></i> Annuler la Mission</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__. '../../../../includes/footer.php'; ?>