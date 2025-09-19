<?php
// Variables disponibles depuis le contrôleur:
// $pageTitle, $filterYear, $filterMonthNum, $monthNameDisplay,
// $attendanceRecords, $monthlySummaries, $employeesList, $filterEmployeeNin,
// $attendanceCodeMap
?>
<?php include __DIR__ . '../../../../../includes/header.php'; ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?> (<?= htmlspecialchars($monthNameDisplay . ' ' . $filterYear) ?>)</h1>
        <div>
            <a href="<?= route('attendance_generate_template', ['year' => $filterYear, 'month' => $filterMonthNum]) ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-excel"></i> Télécharger le Modèle
            </a>
        </div>
    </div>

    <?php display_flash_messages(); // Affiche les messages de session (succès, erreur) ?>

    <?php include __DIR__ . '/partials/_upload_form.php'; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-list-check me-1"></i> Registre pour <?= sprintf('%02d/%04d', $filterMonthNum, $filterYear) ?></div>
        <div class="card-body">
            <?php include __DIR__ . '/partials/_filter_form.php'; ?>
            <?php include __DIR__ . '/partials/_attendance_table.php'; ?>
        </div>
    </div>
</div>
<style>
    .bg-purple { background-color: #6f42c1 !important; color: #fff !important; }
    .bg-pink { background-color: #e83e8c !important; color: #fff !important; }
    .bg-orange { background-color: #fd7e14 !important; color: #fff !important; }
    .bg-teal { background-color: #20c997 !important; color: #fff !important; }
    .bg-indigo { background-color: #6610f2 !important; color: #fff !important; }
    .badge.bg-info.text-dark { color: #000 !important; }
</style>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>