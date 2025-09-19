<?php include_once APP_ROOT . '/includes/header.php'; ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?> (<?= htmlspecialchars($monthNameDisplay . ' ' . $filterYear) ?>)</h1>
    </div>

    <?php display_flash_messages(); ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-list-check me-1"></i> Registre pour <?= sprintf('%02d/%04d', $filterMonth, $filterYear) ?></div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-3 align-items-end">
                <input type="hidden" name="route" value="attendance_history">
                <div class="col-md-3">
                    <label class="form-label">Employé</label>
                    <select name="employee_nin" class="form-select form-select-sm">
                        <option value="">Tous les employés</option>
                        <?php foreach($employeesList as $emp): ?>
                        <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= ($filterEmployeeNin == $emp['nin']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter_year_input" class="form-label">Année</label>
                    <input type="number" name="year" id="filter_year_input" class="form-control form-control-sm" value="<?= $filterYear ?>" min="2000" max="2050" required>
                </div>
                <div class="col-md-3">
                    <label for="filter_month_input" class="form-label">Mois</label>
                    <select name="month" id="filter_month_input" class="form-control form-control-sm" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($filterMonth == $m) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(DateTime::createFromFormat('!m', $m)->format('F')) ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-info btn-sm w-100"><i class="bi bi-filter"></i> Filtrer</button></div>
                <div class="col-md-2"><a href="<?= route('attendance_history') ?>" class="btn btn-secondary btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-striped table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Employé</th>
                            <th>Statut Pointage</th>
                            <th>Notes/Type Excel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendanceRecords)): ?>
                            <tr><td colspan="4" class="text-center">Aucun enregistrement trouvé.</td></tr>
                        <?php else: foreach ($attendanceRecords as $record): ?>
                        <tr>
                            <td><?= formatDate($record['attendance_date'] ?? null, 'd/m/Y') ?></td>
                            <td>
                                <a href="<?= route('employees_view', ['nin' => $record['employee_nin']]) ?>">
                                    <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                                </a>
                            </td>
                            <td>
                                <?php
                                    $statusKey = ucfirst(str_replace('_', ' ', $record['status'] ?? 'N/A'));
                                    $badgeClass = 'bg-secondary';
                                    foreach($attendanceCodeMap as $code => $details) {
                                        if (stripos($statusKey, $code) !== false || stripos($record['notes'], $code) !== false) {
                                            $badgeClass = $details['badge'];
                                            break;
                                        }
                                    }
                                    echo "<span class=\"badge $badgeClass\">" . htmlspecialchars($statusKey) . "</span>";
                                ?>
                            </td>
                            <td><small><?= htmlspecialchars($record['notes'] ?? '') ?></small></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include_once APP_ROOT . '/includes/footer.php'; ?>