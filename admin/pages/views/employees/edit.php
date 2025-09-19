<?php include __DIR__ . '../../../../../includes/header.php'; ?>

<div class="container mt-4">
    <form method="post" enctype="multipart/form-data" id="employeeForm" action="<?= route('employees_edit', ['nin' => $nin_to_edit]) ?>">
        <?php csrf_input(); ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Modifier l'Employé</h4>
                <a href="<?= route('employees_view', ['nin' => $nin_to_edit]) ?>" class="btn btn-secondary"><i class="bi bi-eye-fill"></i> Voir le Profil</a>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><h5><i class="bi bi-person-badge"></i> Informations Personnelles</h5></div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Photo</label>
                                    <?php if (!empty($employee_original['photo_path']) && file_exists(PROJECT_ROOT .'/'. $employee_original['photo_path'])): ?>
                                        <div>
                                            <img src="/<?= htmlspecialchars($employee_original['photo_path']) ?>?t=<?= time() ?>" alt="Photo" style="max-height: 100px; border-radius: 5px;" class="mb-2 img-thumbnail">
                                            <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo"><label class="form-check-label" for="remove_photo">Supprimer la photo actuelle</label></div>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="photo" class="form-control mt-2" accept="image/jpeg,image/png,image/gif">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">NIN*</label><input type="text" class="form-control" value="<?= htmlspecialchars($display_data['nin']) ?>" readonly disabled></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">NSS*</label><input type="text" name="nss" class="form-control" required pattern="\d{10,12}" maxlength="12" value="<?= htmlspecialchars($display_data['nss']) ?>"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Prénom*</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($display_data['first_name']) ?>" required></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Nom*</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($display_data['last_name']) ?>" required></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3"><label class="form-label">Genre*</label><select name="gender" class="form-select" required><option value="">-- Sélectionner --</option><option value="male" <?= ($display_data['gender'] === 'male') ? 'selected' : '' ?>>Masculin</option><option value="female" <?= ($display_data['gender'] === 'female') ? 'selected' : '' ?>>Féminin</option></select></div>
                                    <div class="col-md-6 mb-3"><label class="form-label">Date de Naissance*</label><input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($display_data['birth_date']) ?>" required></div>
                                </div>
                                <div class="mb-3"><label class="form-label">Lieu de Naissance*</label><input type="text" name="birth_place" class="form-control" value="<?= htmlspecialchars($display_data['birth_place']) ?>" required></div>
                                <div class="mb-3"><label class="form-label">Situation Familiale*</label><select name="marital_status" class="form-select" required id="marital_status_select"><option value="">-- Sélectionner --</option><option value="Celibataire" <?= ($display_data['marital_status'] === 'Celibataire') ? 'selected' : '' ?>>Célibataire</option><option value="Marie" <?= ($display_data['marital_status'] === 'Marie') ? 'selected' : '' ?>>Marié(e)</option><option value="Divorce" <?= ($display_data['marital_status'] === 'Divorce') ? 'selected' : '' ?>>Divorcé(e)</option><option value="Veuf" <?= ($display_data['marital_status'] === 'Veuf') ? 'selected' : '' ?>>Veuf/Veuve</option></select></div>
                                <div class="mb-3" id="dependents_field" style="display:none;"><label class="form-label">Personnes à Charge</label><input type="number" name="dependents" class="form-control" min="0" value="<?= htmlspecialchars($display_data['dependents'] ?? '0') ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><h5><i class="bi bi-briefcase-fill"></i> Informations Professionnelles</h5></div>
                            <div class="card-body">
                                <div class="mb-3"><label class="form-label">Date d'Embauche*</label><input type="date" name="hire_date" class="form-control" required id="hireDate" value="<?= htmlspecialchars($display_data['hire_date']) ?>"></div>
                                <div class="mb-3"><label class="form-label">Type de Contrat*</label><select name="contract_type" class="form-select" required id="contractType"><option value="">-- Sélectionner --</option><option value="cdi" <?= ($display_data['contract_type'] === 'cdi') ? 'selected' : '' ?>>CDI</option><option value="cdd" <?= ($display_data['contract_type'] === 'cdd') ? 'selected' : '' ?>>CDD</option><option value="stage" <?= ($display_data['contract_type'] === 'stage') ? 'selected' : '' ?>>Stage</option><option value="interim" <?= ($display_data['contract_type'] === 'interim') ? 'selected' : '' ?>>Intérim</option></select></div>
                                <div class="mb-3" id="endDateGroup" style="display: none;"><label class="form-label" for="endDate">Date de Fin de Contrat*</label><input type="date" name="end_date" class="form-control" id="endDate" value="<?= htmlspecialchars($display_data['end_date'] ?? '') ?>"></div>
                                <div class="mb-3 form-check border p-3 rounded bg-light"><input type="checkbox" class="form-check-input" id="is_trial_period" name="is_trial_period" value="1" <?= !empty($display_data['on_trial']) ? 'checked' : '' ?>><label class="form-check-label fw-bold" for="is_trial_period">En période d'essai</label><div class="mt-2" id="trial_duration_group" style="display: none;"><label for="trial_duration_months" class="form-label">Durée Totale (mois)*</label><select name="trial_duration_months" id="trial_duration_months" class="form-select"><option value="3">3 mois</option><option value="6">6 mois</option><option value="12">12 mois</option></select></div></div>
                                <div class="mb-3"><label class="form-label">Département*</label><select name="department" id="department_select" class="form-select" required><option value="" disabled>-- Sélectionner --</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['name']) ?>" <?= ($display_data['department'] === $dept['name']) ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option><?php endforeach; ?></select></div>
                                <div class="mb-3"><label class="form-label">Poste*</label><select name="position" id="position_select" class="form-select" required><option value="" disabled>-- Choisir un département --</option><?php foreach ($positions as $pos): ?><option value="<?= htmlspecialchars($pos['title']) ?>" data-salary="<?= htmlspecialchars($pos['base_salary']) ?>" data-department-name="<?= htmlspecialchars($pos['department_name']) ?>" <?= ($display_data['position'] === $pos['title']) ? 'selected' : '' ?>><?= htmlspecialchars($pos['title']) ?></option><?php endforeach; ?></select></div>
                                <div class="mb-3"><label class="form-label">Salaire Brut*</label><div class="input-group"><input type="number" step="any" name="salary" id="salary_input" class="form-control" value="<?= htmlspecialchars($display_data['salary']) ?>" required min="0"><span class="input-group-text">DZD</span></div></div>
                                <div class="mb-3"><label class="form-label">Statut*</label><select name="status" class="form-select" required><option value="">-- Sélectionner --</option><option value="active" <?= ($display_data['status'] === 'active') ? 'selected' : '' ?>>Actif</option><option value="inactive" <?= ($display_data['status'] === 'inactive') ? 'selected' : '' ?>>Inactif</option><option value="suspended" <?= ($display_data['status'] === 'suspended') ? 'selected' : '' ?>>Suspendu</option></select></div>
                                                        <?php // NOUVEAU BLOC POUR LA CONVERSION CDI ?>
<?php if ($employee_original['contract_type'] === 'cdd' && $employee_original['status'] === 'active'): ?>
    <div class="d-grid gap-2 mt-3 p-3 border border-info rounded bg-light">
        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#changeToCdiModal">
            <i class="bi bi-award-fill"></i> Faire passer en CDI
        </button>
        <small class="text-muted text-center">Cette action mettra fin au contrat CDD et créera un contrat permanent.</small>
    </div>
<?php endif; ?>

<?php // NOUVEAU BLOC POUR LA RÉINTÉGRATION ?>
<?php if ($employee_original['status'] === 'inactive'): ?>
    <div class="alert alert-warning mt-3">
        <h5 class="alert-heading"><i class="bi bi-info-circle-fill"></i> Employé Inactif</h5>
        <p>Cet employé a quitté l'entreprise le <?= formatDate($employee_original['departure_date']) ?>.</p>
        <hr>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#reintegrateModal">
            <i class="bi bi-person-check-fill"></i> Réintégrer l'employé
        </button>
    </div>
<?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h5><i class="bi bi-geo-alt-fill"></i> Coordonnées</h5></div><div class="card-body"><div class="mb-3"><label class="form-label">Adresse Complète*</label><textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($display_data['address']) ?></textarea></div><div class="row"><div class="col-md-7 mb-3"><label class="form-label">Ville*</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($display_data['city']) ?>" required></div><div class="col-md-5 mb-3"><label class="form-label">Code Postal*</label><input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($display_data['postal_code']) ?>" required></div></div><div class="mb-3"><label class="form-label">Téléphone*</label><input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($display_data['phone']) ?>" required></div><div class="mb-3"><label class="form-label">Email*</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($display_data['email']) ?>" required></div></div></div></div>
                    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h5><i class="bi bi-info-circle-fill"></i> Informations Supplémentaires</h5></div><div class="card-body"><div class="mb-3"><label class="form-label">Nom de la Banque</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($display_data['bank_name'] ?? '') ?>"></div><div class="mb-3"><label class="form-label">N° Compte Bancaire</label><input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($display_data['bank_account'] ?? '') ?>"></div><div class="mb-3"><label class="form-label">Contact d'Urgence (Nom)</label><input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($display_data['emergency_contact'] ?? '') ?>"></div><div class="mb-3"><label class="form-label">Téléphone d'Urgence</label><input type="tel" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($display_data['emergency_phone'] ?? '') ?>"></div></div></div></div>
                </div>
            </div>
            <div class="card-footer text-center py-3">
                <button type="submit" class="btn btn-success btn-lg px-5"><i class="bi bi-save-fill"></i> Enregistrer les Modifications</button>
            </div>
        </div>
    </form>
</div>


<div class="modal fade" id="changeToCdiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= route('employees_edit', ['nin' => $nin_to_edit]) ?>" method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="change_to_cdi">
                <div class="modal-header">
                    <h5 class="modal-title">Passage en Contrat CDI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Vous êtes sur le point de transformer le contrat de <strong><?= htmlspecialchars($employee_original['first_name'] . ' ' . $employee_original['last_name']) ?></strong> en CDI.</p>
                    <div class="mb-3">
                        <label for="effective_date_cdi" class="form-label">Date d'effet du CDI*</label>
                        <input type="date" class="form-control" name="effective_date" id="effective_date_cdi" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="alert alert-info small">
                        La date de fin de contrat sera automatiquement annulée. Cette action sera enregistrée dans l'historique de carrière.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Confirmer le passage en CDI</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="reintegrateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= route('employees_edit', ['nin' => $nin_to_edit]) ?>" method="post">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="reintegrate">
                <div class="modal-header">
                    <h5 class="modal-title">Réintégration de l'Employé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Réintégration de <strong><?= htmlspecialchars($employee_original['first_name'] . ' ' . $employee_original['last_name']) ?></strong> comme employé actif.</p>
                    
                    <div class="mb-3">
                        <label for="new_hire_date" class="form-label">Nouvelle date d'embauche / de réintégration*</label>
                        <input type="date" class="form-control" name="new_hire_date" id="new_hire_date" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_contract_type" class="form-label">Nouveau type de contrat*</label>
                        <select class="form-select" name="new_contract_type" id="new_contract_type" required>
                            <option value="cdi" selected>CDI (Contrat à Durée Indéterminée)</option>
                            <option value="cdd">CDD (Contrat à Durée Déterminée)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="cdd_duration_group" style="display: none;">
                        <label for="cdd_duration_months" class="form-label">Durée du contrat CDD*</label>
                        <div class="input-group">
                            <select class="form-select" name="cdd_duration_months" id="cdd_duration_months">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="input-group-text">mois</span>
                        </div>
                    </div>

                    <div class="alert alert-info small">
                        Le statut passera à "Actif" et les informations de départ seront effacées. Cette action sera enregistrée dans l'historique de carrière.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Confirmer la Réintégration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Element Selections ---
    const departmentSelect = document.getElementById('department_select');
    const positionSelect = document.getElementById('position_select');
    const salaryInput = document.getElementById('salary_input');
    const contractTypeSelect = document.getElementById('contractType');
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

    // --- Data Injected from PHP Controller ---
    const defaultCompanyRestDays = <?= (isset($default_company_rest_days_array) ? json_encode($default_company_rest_days_array) : '[]') ?>;
    const companyWorkDays = <?= (isset($company_work_days_per_week) ? json_encode($company_work_days_per_week) : '5') ?>;
    const currentEmployeeRestDays = <?= (isset($current_employee_rest_days_array) ? json_encode($current_employee_rest_days_array) : '[]') ?>;
    const expectedRestDays = 7 - companyWorkDays;

    // --- Core Functions ---


    // ... (à l'intérieur de votre addEventListener 'DOMContentLoaded')

// --- Logique pour la modale de réintégration ---
const newContractTypeSelect = document.getElementById('new_contract_type');
const cddDurationGroup = document.getElementById('cdd_duration_group');
const cddDurationSelect = document.getElementById('cdd_duration_months');

if (newContractTypeSelect) {
    newContractTypeSelect.addEventListener('change', function() {
        if (this.value === 'cdd') {
            cddDurationGroup.style.display = 'block';
            cddDurationSelect.required = true;
        } else {
            cddDurationGroup.style.display = 'none';
            cddDurationSelect.required = false;
        }
    });
}

    function toggleEndDate() {
        if (!contractTypeSelect || !endDateGroup || !endDateInput) return;
        if (contractTypeSelect.value === 'cdi' || contractTypeSelect.value === '') {
            endDateGroup.style.display = 'none';
            endDateInput.required = false;
        } else {
            endDateGroup.style.display = 'block';
            endDateInput.required = true;
        }
    }

    function filterPositions() {
        if (!departmentSelect || !positionSelect) return;
        const selectedDepartmentName = departmentSelect.value;
        let hasVisibleOptions = false;
        
        // This flag prevents resetting the position/salary on the initial page load,
        // but allows it on subsequent user interactions.
        const isUserAction = departmentSelect.dataset.userAction === 'true';
        if (isUserAction) {
           positionSelect.value = '';
        }

        for (let option of positionSelect.options) {
            if (option.value === "" || option.dataset.departmentName === selectedDepartmentName) {
                option.style.display = 'block';
                if(option.value !== "") hasVisibleOptions = true;
            } else {
                option.style.display = 'none';
            }
        }
        
        const placeholder = positionSelect.options[0];
        if(placeholder) {
            placeholder.textContent = hasVisibleOptions ? '-- Sélectionner un poste --' : (selectedDepartmentName ? '-- Aucun poste --' : '-- Choisir un département --');
        }
    }
    
    function updateEmployeeRestDaysSelect() {
        if (!employeeRestDaysSelect || !employeeRestDaysHint || !restDayOptionCompany) return;
        
        Array.from(employeeRestDaysSelect.options).forEach(option => { option.selected = false; });
        
        if (restDayOptionCompany.checked) {
            employeeRestDaysSelect.disabled = true;
            defaultCompanyRestDays.forEach(day => {
                const option = employeeRestDaysSelect.querySelector(`option[value="${day}"]`);
                if (option) option.selected = true;
            });
            const selectedDayNames = defaultCompanyRestDays.map(d => employeeRestDaysSelect.querySelector(`option[value="${d}"]`)?.textContent).filter(Boolean);
            employeeRestDaysHint.textContent = `Les jours par défaut sont : ${selectedDayNames.join(', ')}.`;
        } else { // Custom option is checked
            employeeRestDaysSelect.disabled = false;
            if (currentEmployeeRestDays.length > 0) {
                 currentEmployeeRestDays.forEach(day => {
                     const option = employeeRestDaysSelect.querySelector(`option[value='${day}']`);
                     if(option) option.selected = true;
                 });
            }
            employeeRestDaysHint.textContent = `Sélectionnez exactement ${expectedRestDays} jour(s) de repos. (Ctrl/Cmd + clic)`;
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
    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            this.dataset.userAction = 'true';
            filterPositions.call(this);
        });
    }
    if (positionSelect) {
        positionSelect.addEventListener('change', function() {
            // Only update salary if it's a direct user action on the position dropdown
            if (departmentSelect && departmentSelect.dataset.userAction === 'true') {
                const selectedOption = this.options[this.selectedIndex];
                if (salaryInput && selectedOption && selectedOption.dataset.salary) {
                    salaryInput.value = selectedOption.dataset.salary;
                }
            }
        });
    }
    if (contractTypeSelect) contractTypeSelect.addEventListener('change', toggleEndDate);
    if (restDayOptionCompany) restDayOptionCompany.addEventListener('change', updateEmployeeRestDaysSelect);
    if (restDayOptionCustom) restDayOptionCustom.addEventListener('change', updateEmployeeRestDaysSelect);
    if (trialCheckbox) trialCheckbox.addEventListener('change', toggleTrialDuration);
    if (maritalStatusSelect) maritalStatusSelect.addEventListener('change', toggleDependentsField);
    
    // --- Initial State Setup on Page Load ---
    // Set userAction to false initially to prevent salary override on first load
    if (departmentSelect) departmentSelect.dataset.userAction = 'false';
    
    filterPositions.call(departmentSelect || window);
    toggleEndDate();
    updateEmployeeRestDaysSelect();
    toggleTrialDuration();
    toggleDependentsField();

});
</script>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>