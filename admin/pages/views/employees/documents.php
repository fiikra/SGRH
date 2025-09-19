<?php include __DIR__ . '../../../../../includes/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-folder-symlink-fill me-2"></i>
                Documents de <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
            </h4>
            <a href="<?= route('employees_view', ['nin' => $employee['nin']]) ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle"></i> Retour au Profil
            </a>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card h-100 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Ajouter un Document</h5>
                        </div>
                        <div class="card-body">
                            <form action="<?= route('employees_documents', ['nin' => $nin]) ?>" method="post" enctype="multipart/form-data">
                                <?php csrf_input(); ?>
                                <div class="mb-3">
                                    <label class="form-label">Type de Document*</label>
                                    <select name="document_type" class="form-select" required>
                                        <option value="contrat">Contrat de Travail</option>
                                        <option value="cin">Carte d'Identité</option>
                                        <option value="cv">CV</option>
                                        <option value="diplome">Diplôme / Certification</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Titre du Document*</label>
                                    <input type="text" name="title" class="form-control" placeholder="Ex: Contrat CDI 2025" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Fichier*</label>
                                    <input type="file" name="document_file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png">
                                    <small class="text-muted">Formats autorisés: PDF, JPG, PNG. Taille max: 5MB.</small>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Date d'Émission</label><input type="date" name="issue_date" class="form-control"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Date d'Expiration</label><input type="date" name="expiry_date" class="form-control"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Informations additionnelles..."></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Uploader le Document</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <h5 class="mb-3"><i class="bi bi-archive-fill"></i> Documents Archivés</h5>
                    <?php if (count($documents) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($documents as $doc): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-text h3 me-3 text-secondary"></i>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($doc['title']) ?></h6>
                                            <small class="text-muted">
                                                Type: <strong><?= htmlspecialchars(ucfirst($doc['document_type'])) ?></strong> | 
                                                Ajouté le: <?= formatDate($doc['upload_date'], 'd/m/Y') ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="btn-group">
                                        <a href="/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Voir"><i class="bi bi-eye"></i></a>
                                        <a href="#" class="btn btn-sm btn-outline-danger" title="Supprimer" onclick="confirmDelete(<?= $doc['id'] ?>, '<?= route('employees_document_delete', ['nin' => $nin]) ?>')"><i class="bi bi-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle h4"></i><br>
                            Aucun document archivé pour cet employé.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(docId, baseUrl) {
    if (confirm("Êtes-vous sûr de vouloir supprimer ce document ? Cette action est irréversible.")) {
        // The route() function in PHP already created the base URL with the NIN. We just add the doc ID.
        window.location.href = `${baseUrl}&id=${docId}`;
    }
}
</script>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>