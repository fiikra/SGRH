<div class="card mb-4">
    <div class="card-header"><i class="bi bi-upload me-1"></i> Importer Pointage pour <strong><?= sprintf('%02d/%04d', $filterMonthNum, $filterYear) ?></strong></div>
    <div class="card-body">
        <form action="<?= route('attendance_upload') ?>" method="post" enctype="multipart/form-data">
            <?php csrf_input(); ?>
            <input type="hidden" name="year" value="<?= $filterYear ?>">
            <input type="hidden" name="month" value="<?= $filterMonthNum ?>">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label for="attendance_file" class="form-label">Fichier Excel (.xlsx, .xls):</label>
                    <input class="form-control" type="file" id="attendance_file" name="attendance_file" accept=".xlsx, .xls" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload"></i> Importer</button>
                </div>
            </div>
            <small class="form-text text-muted mt-1">Le fichier doit être basé sur le modèle généré pour le mois sélectionné.</small>
        </form>
    </div>
</div>