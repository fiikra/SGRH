<?php include __DIR__ . '../../../../../includes/header.php'; // Use your public header if different ?>

<div class="container mt-5">
    <div class="card shadow-lg" style="max-width: 800px; margin: auto;">
        <div class="card-body p-5">
            <div class="row align-items-center">
                <div class="col-md-4 text-center">
                    <?php if (!empty($employee['photo_path'])): ?>
                        <img src="/<?= htmlspecialchars($employee['photo_path']) ?>" class="img-fluid rounded-circle border border-3" alt="Photo">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; margin: auto;">
                            <i class="bi bi-person" style="font-size: 4rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-8">
                    <h2 class="display-6"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                    <h4 class="text-muted fw-light"><?= htmlspecialchars($employee['position']) ?></h4>
                    <p class="text-muted"><?= htmlspecialchars($employee['department']) ?></p>
                    <hr>
                    <p><i class="bi bi-briefcase-fill me-2"></i><strong>Date d'embauche:</strong> <?= formatDate($employee['hire_date']) ?></p>
                    <p><i class="bi bi-person-check-fill me-2"></i><strong>Statut:</strong> <span class="badge bg-success">Actif</span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../../includes/footer.php'; // Use your public footer if different ?>