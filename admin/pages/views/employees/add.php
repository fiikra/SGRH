<?php include __DIR__ . '../../../../../includes/header.php'; ?>

<div class="container mt-4">
    <form method="post" enctype="multipart/form-data" id="employeeForm" novalidate>
        <?php csrf_input(); ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Ajouter un Nouvel Employé</h4>
                <a href="<?= route('employees_list') ?>" class="btn btn-secondary"><i class="bi bi-list-ul"></i> Retour à la liste</a>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><h5><i class="bi bi-person-badge"></i> Informations Personnelles</h5></div>
                            <div class="card-body">
                                <div class="mb-3"><label class="form-label">Photo (Optionnel)</label><input type="file" name="photo" class="form-control" accept="image/jpeg,image/png"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">NIN*</label><input type="text" name="nin" class="form-control" required pattern="\d{18}" title="Doit contenir 18 chiffres" maxlength="18" value="<?= htmlspecialchars($_POST['nin'] ?? '') ?>"></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">NSS*</label><input type="text" name="nss" class="form-control" required pattern="\d{10,12}" title="Doit contenir 10 à 12 chiffres" maxlength="12" value="<?= htmlspecialchars($_POST['nss'] ?? '') ?>"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Prénom*</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Nom*</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Genre*</label><select name="gender" class="form-select" required><option value="">-- Sélectionner --</option><option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Masculin</option><option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Féminin</option></select></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Date de Naissance*</label><input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required max="<?= date('Y-m-d', strtotime('-16 years')) ?>"></div>
                                </div>
                                <div class="mb-3"><label class="form-label">Lieu de Naissance*</label><input type="text" name="birth_place" class="form-control" value="<?= htmlspecialchars($_POST['birth_place'] ?? '') ?>" required></div>
                                <div class="mb-3"><label class="form-label">Situation Familiale*</label><select name="marital_status" class="form-select" required id="marital_status_select"><option value="">-- Sélectionner --</option><option value="Celibataire" <?= (($_POST['marital_status'] ?? '') === 'Celibataire') ? 'selected' : '' ?>>Célibataire</option><option value="Marie" <?= (($_POST['marital_status'] ?? '') === 'Marie') ? 'selected' : '' ?>>Marié(e)</option><option value="Divorce" <?= (($_POST['marital_status'] ?? '') === 'Divorce') ? 'selected' : '' ?>>Divorcé(e)</option><option value="Veuf" <?= (($_POST['marital_status'] ?? '') === 'Veuf') ? 'selected' : '' ?>>Veuf/Veuve</option></select></div>
                                <div class="mb-3" id="dependents_field" style="display:none;"><label class="form-label">Personnes à Charge</label><input type="number" name="dependents" class="form-control" min="0" value="<?= htmlspecialchars($_POST['dependents'] ?? '0') ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><h5><i class="bi bi-briefcase-fill"></i> Informations Professionnelles</h5></div>
                            <div class="card-body">
                                <div class="mb-3"><label class="form-label">Date d'Embauche*</label><input type="date" name="hire_date" class="form-control" required id="hireDate" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['hire_date'] ?? '') ?>"></div>
                                <div class="mb-3"><label class="form-label">Type de Contrat*</label><select name="contract_type" class="form-select" required id="contractType"><option value="">-- Sélectionner --</option><option value="cdi">CDI</option><option value="cdd">CDD</option><option value="stage">Stage</option><option value="interim">Intérimaire</option></select></div>
                                <div class="mb-3" id="endDateGroup" style="display: none;"><label class="form-label" for="endDate">Date de Fin de Contrat*</label><input type="date" name="end_date" class="form-control" id="endDate" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>"></div>
                                <div class="mb-3 form-check border p-3 rounded bg-light"><input type="checkbox" class="form-check-input" id="is_trial_period" name="is_trial_period" value="1"><label class="form-check-label fw-bold" for="is_trial_period">Inclure une période d'essai</label><div class="mt-2" id="trial_duration_group" style="display: none;"><label for="trial_duration_months" class="form-label">Durée (mois)*</label><select name="trial_duration_months" id="trial_duration_months" class="form-select"><option value="3">3 mois</option><option value="6">6 mois</option><option value="12">12 mois</option></select></div></div>
                                <div class="mb-3"><label class="form-label">Département*</label><select name="department" id="department_select" class="form-select" required><option value="" disabled selected>-- Sélectionner --</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
                                <div class="mb-3"><label class="form-label">Poste*</label><select name="position" id="position_select" class="form-select" required><option value="" disabled selected>-- Choisir un département --</option><?php foreach ($positions as $pos): ?><option value="<?= htmlspecialchars($pos['title']) ?>" data-salary="<?= htmlspecialchars($pos['base_salary']) ?>" data-department-name="<?= htmlspecialchars($pos['department_name']) ?>"><?= htmlspecialchars($pos['title']) ?></option><?php endforeach; ?></select></div>
                                <div class="mb-3"><label class="form-label">Salaire Brut*</label><div class="input-group"><input type="number" step="any" name="salary" id="salary_input" class="form-control" value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>" required min="0"><span class="input-group-text">DZD</span></div></div>
                            </div>
                        </div>
                    </div>
                </div>

                 <div class="row g-4 mt-1">
                    <div class="col-lg-6">
                        <div class="card h-100">
                             <div class="card-header"><h5><i class="bi bi-geo-alt-fill"></i> Coordonnées</h5></div>
                             <div class="card-body">
                                <div class="mb-3"><label class="form-label">Adresse Complète*</label><textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea></div>
                                <div class="row"><div class="col-md-7 mb-3"><label class="form-label">Ville*</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required></div><div class="col-md-5 mb-3"><label class="form-label">Code Postal*</label><input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>" required></div></div>
                                <div class="mb-3"><label class="form-label">Téléphone Principal*</label><input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required></div>
                                <div class="mb-3"><label class="form-label">Email Principal*</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></div>
                             </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><h5><i class="bi bi-info-circle-fill"></i> Informations Supplémentaires</h5></div>
                            <div class="card-body">
                                <div class="mb-3"><label class="form-label">Nom de la Banque</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>"></div>
                                <div class="mb-3"><label class="form-label">N° Compte Bancaire</label><input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>"></div>
                                <div class="mb-3"><label class="form-label">Contact d'Urgence (Nom)</label><input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?>"></div>
                                <div class="mb-3"><label class="form-label">Téléphone d'Urgence</label><input type="tel" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($_POST['emergency_phone'] ?? '') ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-center py-3">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="bi bi-check-circle-fill"></i> Enregistrer l'Employé</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Element Selections ---
    const departmentSelect = document.getElementById('department_select');
    const positionSelect = document.getElementById('position_select');
    const salaryInput = document.getElementById('salary_input');
    const contractTypeSelect = document.getElementById('contractType');
    const hireDateInput = document.getElementById('hireDate');
    const endDateGroup = document.getElementById('endDateGroup');
    const endDateInput = document.getElementById('endDate');
    const maritalStatusSelect = document.getElementById('marital_status_select');
    const dependentsField = document.getElementById('dependents_field');
    const restDayOptionCompany = document.getElementById('restDayOptionCompany');
    const restDayOptionCustom = document.getElementById('restDayOptionCustom');
    const employeeRestDaysSelect = document.getElementById('employee_rest_days_select');
    const employeeRestDaysHint = document.getElementById('employee_rest_days_hint');
    const trialCheckbox = document.getElementById('is_trial_period');
    const trialGroup = document.getElementById('trial_duration_group');
    const trialDurationSelect = document.getElementById('trial_duration_months');
    
    // --- Data passed from PHP Controller ---
    // Safely parse JSON data passed from the controller
    const companyWorkDays = <?= (isset($company_work_days_per_week) ? json_encode($company_work_days_per_week) : '5') ?>;
    const defaultCompanyRestDays = <?= (isset($default_company_rest_days_array) ? json_encode($default_company_rest_days_array) : '["0","6"]') ?>;
    const expectedRestDays = 7 - companyWorkDays;

    // --- Core Functions ---

    function toggleEndDate() {
        if (!contractTypeSelect || !endDateGroup || !endDateInput) return;
        if (contractTypeSelect.value === 'cdi' || contractTypeSelect.value === '') {
            endDateGroup.style.display = 'none';
            endDateInput.required = false;
            endDateInput.value = '';
        } else {
            endDateGroup.style.display = 'block';
            endDateInput.required = true;
        }
    }
    
    function calculateEndDateSuggestion() {
        if (!hireDateInput.value || !contractTypeSelect.value || contractTypeSelect.value === 'cdi') {
            return;
        }
        // Only suggest a date if the end date field is empty
        if (endDateInput.value) {
            return;
        }

        try {
            let hireDate = new Date(hireDateInput.value);
            if (isNaN(hireDate.getTime())) return;

            let endDate = new Date(hireDate);
            switch(contractTypeSelect.value) {
                case 'cdd': endDate.setFullYear(endDate.getFullYear() + 1); break;
                case 'stage': endDate.setMonth(endDate.getMonth() + 6); break;
                case 'interim': endDate.setMonth(endDate.getMonth() + 3); break;
                default: return;
            }

            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');
            endDateInput.value = `${year}-${month}-${day}`;
        } catch (e) {
            console.error("Error in calculateEndDateSuggestion: ", e);
        }
    }
    
    function updateEmployeeRestDaysSelect() {
        if (!employeeRestDaysSelect || !employeeRestDaysHint || !restDayOptionCompany) return;

        Array.from(employeeRestDaysSelect.options).forEach(option => option.selected = false);
        
        if (restDayOptionCompany.checked) {
            employeeRestDaysSelect.disabled = true;
            defaultCompanyRestDays.forEach(day => {
                const option = employeeRestDaysSelect.querySelector(`option[value="${day}"]`);
                if (option) option.selected = true;
            });
            const selectedDayNames = defaultCompanyRestDays.map(d => employeeRestDaysSelect.querySelector(`option[value="${d}"]`)?.textContent).filter(Boolean);
            employeeRestDaysHint.textContent = `Les jours par défaut sont : ${selectedDayNames.join(', ')}.`;
        } else {
            employeeRestDaysSelect.disabled = false;
            employeeRestDaysHint.textContent = `Veuillez sélectionner exactement ${expectedRestDays} jour(s) de repos.`;
            <?php if (isset($_POST['rest_day_option'], $_POST['employee_rest_days']) && $_POST['rest_day_option'] === 'custom'): ?>
                const postRestDays = <?= json_encode($_POST['employee_rest_days']) ?>;
                postRestDays.forEach(day => {
                    const option = employeeRestDaysSelect.querySelector(`option[value='${day}']`);
                    if (option) option.selected = true;
                });
            <?php endif; ?>
        }
    }

    function filterPositions() {
        if (!departmentSelect || !positionSelect) return;
        const selectedDepartmentName = departmentSelect.value;
        let hasVisibleOptions = false;
        
        positionSelect.value = '';
        if (salaryInput) salaryInput.value = '';

        for (let option of positionSelect.options) {
            if (option.value === "") continue;
            const isMatch = option.dataset.departmentName === selectedDepartmentName;
            option.style.display = isMatch ? 'block' : 'none';
            if (isMatch) hasVisibleOptions = true;
        }
        
        const placeholder = positionSelect.options[0];
        if (placeholder) {
            if (hasVisibleOptions) placeholder.textContent = '-- Sélectionner un poste --';
            else if (selectedDepartmentName) placeholder.textContent = '-- Aucun poste pour ce département --';
            else placeholder.textContent = '-- D\'abord, choisir un département --';
        }
    }

    function toggleTrialDuration() {
        if (!trialCheckbox || !trialGroup || !trialDurationSelect) return;
        const isChecked = trialCheckbox.checked;
        trialGroup.style.display = isChecked ? 'block' : 'none';
        trialDurationSelect.required = isChecked;
    }
    
    function toggleDependentsField() {
        if (!maritalStatusSelect || !dependentsField) return;
        const status = maritalStatusSelect.value;
        dependentsField.style.display = (status && status !== 'Celibataire') ? 'block' : 'none';
    }

    // --- Event Listeners Registration ---
    if (departmentSelect) departmentSelect.addEventListener('change', filterPositions);
    if (positionSelect) {
        positionSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (salaryInput && selectedOption && selectedOption.dataset.salary) {
                salaryInput.value = selectedOption.dataset.salary;
            }
        });
    }
    if (contractTypeSelect) {
        contractTypeSelect.addEventListener('change', () => {
            toggleEndDate();
            calculateEndDateSuggestion();
        });
    }
    if (hireDateInput) hireDateInput.addEventListener('change', calculateEndDateSuggestion);
    if (restDayOptionCompany) restDayOptionCompany.addEventListener('change', updateEmployeeRestDaysSelect);
    if (restDayOptionCustom) restDayOptionCustom.addEventListener('change', updateEmployeeRestDaysSelect);
    if (trialCheckbox) trialCheckbox.addEventListener('change', toggleTrialDuration);
    if (maritalStatusSelect) maritalStatusSelect.addEventListener('change', toggleDependentsField);
    
    // --- Initial State Setup on Page Load ---
    toggleEndDate();
    updateEmployeeRestDaysSelect();
    filterPositions();
    toggleTrialDuration();
    toggleDependentsField();
    
    // --- Restore Form State After Server-Side Validation Failure ---
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if (isset($_POST['department'])): ?>
            departmentSelect.value = <?= json_encode($_POST['department']) ?>;
            filterPositions(); // Re-filter positions after setting department
        <?php endif; ?>
        <?php if (isset($_POST['position'])): ?>
            // Use a small delay to ensure options are visible after filtering
            setTimeout(() => {
                positionSelect.value = <?= json_encode($_POST['position']) ?>;
                positionSelect.dispatchEvent(new Event('change'));
            }, 100);
        <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>