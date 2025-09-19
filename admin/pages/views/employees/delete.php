<?php include __DIR__ . '../../../../../includes/header.php'; ?>

<div class="container mt-5" style="max-width: 600px;">
    <div class="card border-danger shadow">
        <div class="card-header bg-danger text-white">
            <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer la Suppression</h4>
        </div>
        <div class="card-body p-4">
            <p class="lead">Êtes-vous absolument sûr de vouloir supprimer définitivement l'employé suivant ?</p>
            
            <div class="alert alert-warning">
                <div class="d-flex align-items-center">
                    <?php if (!empty($employee['photo_path'])): ?>
                        <img src="/<?= htmlspecialchars($employee['photo_path']) ?>" class="rounded-circle me-3" width="60" height="60" style="object-fit: cover;">
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-0"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h5>
                        <small class="text-muted">NIN: <?= htmlspecialchars($employee['nin']) ?> | Poste: <?= htmlspecialchars($employee['position']) ?></small>
                    </div>
                </div>
            </div>
            
            <p class="text-danger fw-bold mt-3">
                <i class="bi bi-trash-fill"></i> Cette action est irréversible. Toutes les données associées (documents, congés, historiques, etc.) seront définitivement effacées.
            </p>
            
            <form action="<?= route('employees_delete', ['nin' => $nin]) ?>" method="post" class="mt-4">
                <?php csrf_input(); ?>
                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= route('employees_view', ['nin' => $nin]) ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Oui, Confirmer la Suppression
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>