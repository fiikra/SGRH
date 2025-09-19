// assets/js/employees/profile/profile.js

// General page logic for employee profile, AJAX actions, leave modal logic, quick access, etc.

document.addEventListener('DOMContentLoaded', function() {
    // Leave modal: fill balances and restrict available types
    var newAddLeaveModalEl = document.getElementById('newAddLeaveModal');
    if (newAddLeaveModalEl) {
        newAddLeaveModalEl.addEventListener('show.bs.modal', function () {
            // These variables should be set via PHP in the page
            modalAnnualBalanceSpan.textContent = parseFloat(employeeAnnualBalance).toFixed(1);
            modalRemainingBalanceSpan.textContent = parseFloat(employeeRemainingBalance).toFixed(1);
            modalRecupBalanceSpan.textContent = parseFloat(employeeRecupBalance).toFixed(1);

            modalStartDateInput.valueAsDate = new Date();
            modalEndDateInput.value = modalStartDateInput.value;
            updateModalLeaveTypeUI();
        });
    }

    // Leave type logic for modal
    window.updateModalLeaveTypeUI = function() {
        updateModalPermittedDays();
        updateModalLeaveTypesAvailability();
    };
    window.updateModalPermittedDays = function() {
        const leaveType = modalLeaveTypeSelect.value;
        const annual = parseFloat(modalAnnualBalanceSpan.textContent) || 0;
        const reliquat = parseFloat(modalRemainingBalanceSpan.textContent) || 0;
        const recup = parseFloat(modalRecupBalanceSpan.textContent) || 0;

        let typeConfig = modalDetailedLeaveTypes[leaveType];
        let calculatedMaxDays = 365;

        if (typeConfig) {
            if (leaveType === 'annuel') {
                calculatedMaxDays = Math.floor(reliquat > 30 ? 30 : reliquat) + recup + annual;
            } else if (leaveType === 'reliquat') {
                calculatedMaxDays = Math.floor(reliquat > 30 ? 30 : reliquat);
            } else if (leaveType === 'recup') {
                calculatedMaxDays = recup;
            } else if (leaveType === 'anticipe') {
                calculatedMaxDays = 30;
            } else if (typeConfig.max_days !== null) {
                calculatedMaxDays = typeConfig.max_days;
            }
        }

        modalEndDateInput.min = modalStartDateInput.value;
        if (modalStartDateInput.value && calculatedMaxDays > 0) {
            const start = new Date(modalStartDateInput.value);
            const maxEnd = new Date(start);
            maxEnd.setDate(start.getDate() + calculatedMaxDays - 1);
            modalEndDateInput.max = maxEnd.toISOString().split('T')[0];
        } else {
            modalEndDateInput.max = "";
        }
    };
    window.updateModalLeaveTypesAvailability = function() {
        const annual = parseFloat(modalAnnualBalanceSpan.textContent) || 0;
        const reliquat = parseFloat(modalRemainingBalanceSpan.textContent) || 0;
        const recup = parseFloat(modalRecupBalanceSpan.textContent) || 0;
        const hasSold = (annual > 0) || (reliquat > 0) || (recup > 0);

        for (const option of modalLeaveTypeSelect.options) {
            const typeInfo = modalDetailedLeaveTypes[option.value];
            if (typeInfo) {
                if (typeInfo.has_sold === false) {
                    option.disabled = false;
                } else if ((option.value === 'unpaid' || option.value === 'anticipe') && hasSold) {
                    option.disabled = true;
                    if (modalLeaveTypeSelect.value === option.value) {
                        modalLeaveTypeSelect.value = 'annuel';
                        updateModalPermittedDays();
                    }
                } else {
                    option.disabled = false;
                }
            }
        }
    };

    // Generate quick access account
    window.generateQuickAccess = async function(createUrl, viewUrlBase, event) {
        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Génération...';
        btn.disabled = true;
        try {
            const response = await fetch(createUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const responseText = await response.text();
            let data;
            try { data = JSON.parse(responseText); } catch (e) { throw new Error('Réponse du serveur invalide.'); }
            if (!response.ok) { throw new Error(data.message || `Erreur du serveur (${response.status}).`); }
            if (!data.success) { throw new Error(data.message || 'Erreur inconnue.'); }
            
            document.getElementById('voucherEmployeeName').textContent = `${data.employee_first_name} ${data.employee_last_name}`;
            document.getElementById('voucherCreationDate').textContent = new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            document.getElementById('voucherUsername').textContent = data.username;
            document.getElementById('voucherPassword').textContent = data.password;
            
            var voucherModalEl = document.getElementById('voucherModal');
            var voucherModal = bootstrap.Modal.getInstance(voucherModalEl) || new bootstrap.Modal(voucherModalEl);
            voucherModal.show();
            
            // Update button to link to user view
            const viewUrl = viewUrlBase + '&id=' + data.user_id;
            const newButtonHtml = `<a href="${viewUrl}" class="btn btn-sm btn-secondary"><i class="bi bi-person-check"></i> Voir Compte Utilisateur</a>`;
            if (btn.parentElement) { btn.outerHTML = newButtonHtml; }

        } catch (error) {
            alert('Erreur: ' + error.message);
            if(btn && document.body.contains(btn)) { 
                 btn.innerHTML = originalHtml; btn.disabled = false;
            }
        }
    };

    // Trial decision modal logic
    const renewRadio = document.getElementById('decision_renew');
    const renewOptions = document.getElementById('renew_options');
    document.querySelectorAll('input[name="decision"]').forEach((elem) => {
        elem.addEventListener("change", function(event) {
            renewOptions.style.display = renewRadio.checked ? 'block' : 'none';
        });
    });
    var select = document.querySelector('select[name="renewal_duration_months"]');
    var hiddenLabel = document.getElementById('renew_period_label');
    function updateLabel() {
        if (select) {
            hiddenLabel.value = select.value + " mois";
        }
    }
    if (select) {
        select.addEventListener('change', updateLabel);
        updateLabel();
    }
    if(renewRadio && renewRadio.checked) {
        renewOptions.style.display = 'block';
    }
});