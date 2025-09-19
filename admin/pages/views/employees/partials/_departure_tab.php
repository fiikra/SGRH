<?php // /admin/pages/views/employees/partials/_departure_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Gestion du Départ de l'Employé</h5>
</div>
<?php if ($employee['status'] === 'active'): ?>
<div class="alert alert-warning">
    <strong>Attention :</strong> L'enregistrement du départ rendra le profil de l'employé **inactif**. Cette action est difficilement réversible.
</div>
<button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#departureModal"><i class="bi bi-box-arrow-right"></i> Enregistrer un Départ</button>
<?php else: ?>
    <div class="alert alert-secondary">
        <h5 class="alert-heading">Départ déjà enregistré</h5>
        <p>Cet employé est déjà marqué comme inactif.</p>
        <hr>
        <p class="mb-0"><strong>Date de sortie :</strong> <?= formatDate($employee['departure_date']) ?><br>
        <strong>Motif :</strong> <?= htmlspecialchars($employee['departure_reason']) ?></p>
    </div>
<?php endif; ?>