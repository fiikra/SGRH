<?php
// Prevent direct access to this file.
if (!defined('APP_SECURE_INCLUDE')) {
    http_response_code(403);
    die('Direct access not allowed.');
}

if (empty($_GET['nin'])) {
    header("Location: " . route('employees_list'));
    exit();
}

$nin = sanitize($_GET['nin']);

// Get employee information
$stmt = $db->prepare("SELECT * FROM employees WHERE nin = ?");
$stmt->execute([$nin]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = "Employé non trouvé";
    header("Location: " . route('employees_list'));
    exit();
}

// Get documents
$documents = $db->prepare("SELECT * FROM employee_documents WHERE employee_nin = ? ORDER BY upload_date DESC");
$documents->execute([$nin]);

// Get leave history
$leaves = $db->prepare("SELECT * FROM leave_requests WHERE employee_nin = ? ORDER BY created_at DESC");
$leaves->execute([$nin]);

// Get company info for the logo
$company = $db->query("SELECT company_name, logo_path FROM company_settings LIMIT 1")->fetch();

$pageTitle = "Profil de " . htmlspecialchars($employee['first_name'] . " " . $employee['last_name']);
include __DIR__. '/../../../../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Profil Employé</h1>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if (!empty($employee['photo_path'])): ?>
                        <img src="/<?= htmlspecialchars($employee['photo_path']) ?>" class="rounded-circle mb-3" width="200" height="200" alt="Photo">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 200px; height: 200px; margin: 0 auto;">
                            <i class="bi bi-person" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>

                    <h3><?= htmlspecialchars($employee['first_name']) ?> <?= htmlspecialchars($employee['last_name']) ?></h3>
                    <h5 class="text-muted"><?= htmlspecialchars($employee['position']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($employee['department']) ?></p>

                    <div class="d-grid gap-2">
                        <a href="<?= route('employees_view', ['nin' => $employee['nin']]) ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Détails
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#info">Informations</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="info">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Informations Personnelles</h5>
                                    <dl class="row">
                                        <dt class="col-sm-5">NIN</dt>
                                        <dd class="col-sm-7"><?= htmlspecialchars($employee['nin']) ?></dd>

                                        <dt class="col-sm-5">Date de Naissance</dt>
                                        <dd class="col-sm-7"><?= formatDate($employee['birth_date']) ?> (<?= htmlspecialchars($employee['birth_place']) ?>)</dd>

                                        <dt class="col-sm-5">Statut</dt>
                                        <dd class="col-sm-7">
                                            <span class="badge bg-<?=
                                                $employee['status'] === 'active' ? 'success' :
                                                ($employee['status'] === 'inactive' ? 'secondary' :
                                                ($employee['status'] === 'suspended' ? 'warning' : 'danger'))
                                                ?>">
                                                <?= ucfirst(htmlspecialchars($employee['status'])) ?>
                                            </span>
                                        </dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <h5>Informations Professionnelles</h5>
                                    <dl class="row">
                                        <dt class="col-sm-5">Date d'Embauche</dt>
                                        <dd class="col-sm-7"><?= formatDate($employee['hire_date']) ?></dd>
                                    </dl>
                                </div>
                            </div>

                            <?php if (!empty($employee['emergency_contact'])): ?>
                                <h5 class="mt-4">Contact d'Urgence</h5>
                                <p>
                                    <?= htmlspecialchars($employee['emergency_contact']) ?> - <?= htmlspecialchars($employee['emergency_phone']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__.'/../../../../includes/footer.php'; ?>