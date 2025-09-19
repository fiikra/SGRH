<form method="get" action="<?= route('attendance_manage') ?>" class="row g-3 mb-3 align-items-end">
    <input type="hidden" name="route" value="attendance_manage">
    <div class="col-md-3">
        <label for="filter_employee_nin_select" class="form-label">Employé</label>
        <select name="employee_nin" id="filter_employee_nin_select" class="form-select form-select-sm">
            <option value=''>Tous</option>
            <?php foreach ($employeesList as $emp): ?>
                <option value="<?= htmlspecialchars($emp['nin']) ?>" <?= $filterEmployeeNin == $emp['nin'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name'].' ('.$emp['nin'].')') ?>
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
                <option value="<?= $m ?>" <?= $filterMonthNum == $m ? 'selected' : '' ?>>
                    <?= htmlspecialchars(DateTime::createFromFormat('!m', $m)->format('F')) ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-info btn-sm w-100"><i class="bi bi-filter"></i> Filtrer</button>
    </div>
    <div class="col-md-2">
        <a href="<?= route('attendance_manage') ?>" class="btn btn-secondary btn-sm w-100"><i class="bi bi-arrow-clockwise"></i> Reset</a>
    </div>
</form>