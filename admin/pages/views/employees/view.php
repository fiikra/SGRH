<?php
// /admin/pages/views/employees/view.php

if (!defined('APP_SECURE_INCLUDE')) exit('No direct access allowed');
include __DIR__ . '../../../../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <?php include __DIR__ . '/partials/_profile_summary.php'; ?>

    <div class="card">
        <div class="card-header p-0 border-bottom">
            <ul class="nav nav-tabs card-header-tabs" id="employee-tabs" role="tablist">
                <li class="nav-item" role="presentation"><a class="nav-link active" data-bs-toggle="tab" href="#info" role="tab" aria-controls="info" aria-selected="true">Informations</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#documents" role="tab" aria-controls="documents" aria-selected="false">Documents</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#formations" role="tab" aria-controls="formations" aria-selected="false">Formations</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#career-and-decisions" role="tab" aria-controls="career-and-decisions" aria-selected="false">Carrière & Décisions</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#attendance" role="tab" aria-controls="attendance" aria-selected="false">Présence</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#leaves" role="tab" aria-controls="leaves" aria-selected="false">Congés</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#sick-leaves" role="tab" aria-controls="sick-leaves" aria-selected="false">Maladies</a></li>
                 <?php if ($employee['gender'] === 'female'): ?>
                    <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#maternity-leaves" role="tab" aria-controls="maternity-leaves" aria-selected="false">Maternité</a></li>
                <?php endif; ?>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#sanctions" role="tab" aria-controls="sanctions" aria-selected="false">Sanctions</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#questionnaires" role="tab" aria-controls="questionnaires" aria-selected="false">Questionnaires</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#certificates" role="tab" aria-controls="certificates" aria-selected="false">Certificats</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#notifications" role="tab" aria-controls="notifications" aria-selected="false"><i class="bi bi-bell"></i> Période d'essai</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#departure" role="tab" aria-controls="departure" aria-selected="false"><i class="bi bi-door-closed-fill text-danger"></i> Départ</a></li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="employee-tabs-content">
                <div class="tab-pane fade show active" id="info" role="tabpanel"><?php include __DIR__ . '/partials/_info_tab.php'; ?></div>
                <div class="tab-pane fade" id="documents" role="tabpanel"><?php include __DIR__ . '/partials/_documents_tab.php'; ?></div>
                <div class="tab-pane fade" id="formations" role="tabpanel"><?php include __DIR__ . '/partials/_formations_tab.php'; ?></div>
                <div class="tab-pane fade" id="career-and-decisions" role="tabpanel"><?php include __DIR__ . '/partials/_career_tab.php'; ?></div>
                <div class="tab-pane fade" id="attendance" role="tabpanel"><?php include __DIR__ . '/partials/_attendance_tab.php'; ?></div>
                <div class="tab-pane fade" id="leaves" role="tabpanel"><?php include __DIR__ . '/partials/_leaves_tab.php'; ?></div>
                <div class="tab-pane fade" id="sick-leaves" role="tabpanel"><?php include __DIR__ . '/partials/_sick_leaves_tab.php'; ?></div>
                <?php if ($employee['gender'] === 'female'): ?>
                    <div class="tab-pane fade" id="maternity-leaves" role="tabpanel"><?php include __DIR__ . '/partials/_maternity_leaves_tab.php'; ?></div>
                <?php endif; ?>
                <div class="tab-pane fade" id="sanctions" role="tabpanel"><?php include __DIR__ . '/partials/_sanctions_tab.php'; ?></div>
                <div class="tab-pane fade" id="questionnaires" role="tabpanel"><?php include __DIR__ . '/partials/_questionnaires_tab.php'; ?></div>
                <div class="tab-pane fade" id="certificates" role="tabpanel"><?php include __DIR__ . '/partials/_certificates_tab.php'; ?></div>
                <div class="tab-pane fade" id="notifications" role="tabpanel"><?php include __DIR__ . '/partials/_notifications_tab.php'; ?></div>
                <div class="tab-pane fade" id="departure" role="tabpanel"><?php include __DIR__ . '/partials/_departure_tab.php'; ?></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/_modals.php'; ?>

<script>
// Corrected openUpdateQuestionnaireModal function
function openUpdateQuestionnaireModal(data) {
    var modalElement = document.getElementById('updateQuestionnaireModal');
    try {
        document.getElementById('update_q_ref').value = data.reference_number;
        document.getElementById('update_status').value = data.status;
        document.getElementById('update_response_summary').value = data.response_summary || '';
        document.getElementById('update_decision').value = data.decision || '';
    } catch (e) {
        console.error("Error filling modal fields: " + e.message);
        return;
    }
    try {
        var myModal = new bootstrap.Modal(modalElement);
        myModal.show();
    } catch (e) {
        console.error("Error showing modal: " + e.message);
    }
}

function addQuestionField() {
    const container = document.getElementById('questions_container');
    const newIndex = container.getElementsByClassName('input-group').length + 1;
    const newField = document.createElement('div');
    newField.className = 'input-group mb-2';
    newField.innerHTML = `
        <span class="input-group-text">${newIndex}.</span>
        <input type="text" name="questions[]" class="form-control" placeholder="Question ${newIndex}">
    `;
    container.appendChild(newField);
}


function toggleCustomReason(value) {
    const wrapper = document.getElementById('custom_reason_wrapper');
    if (value === 'Autre') {
        wrapper.style.display = 'block';
        document.getElementById('custom_reason').required = true;
    } else {
        wrapper.style.display = 'none';
        document.getElementById('custom_reason').required = false;
    }
}

async function generateQuickAccess(createUrl, viewUrlBase, event) {
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Génération...';
    btn.disabled = true;
    try {
        const response = await fetch(createUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const responseText = await response.text();
        let data;
        try { data = JSON.parse(responseText); } catch (e) { console.error("JSON Parsing Error:", responseText); throw new Error('Invalid server response.'); }
        if (!response.ok) { throw new Error(data.message || `Server error (${response.status}).`); }
        if (!data.success) { throw new Error(data.message || 'Unknown error.'); }
        
        document.getElementById('voucherEmployeeName').textContent = `${data.employee_first_name} ${data.employee_last_name}`;
        document.getElementById('voucherCreationDate').textContent = new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        document.getElementById('voucherUsername').textContent = data.username;
        document.getElementById('voucherPassword').textContent = data.password;
        
        var voucherModalEl = document.getElementById('voucherModal');
        var voucherModal = bootstrap.Modal.getInstance(voucherModalEl) || new bootstrap.Modal(voucherModalEl);
        voucherModal.show();
        
        const viewUrl = viewUrlBase + '&id=' + data.user_id;
        const newButtonHtml = `<a href="${viewUrl}" class="btn btn-sm btn-secondary"><i class="bi bi-person-check"></i> Voir Compte Utilisateur</a>`;
        if (btn.parentElement) { btn.outerHTML = newButtonHtml; }

    } catch (error) {
        console.error('generateQuickAccess Error:', error);
        alert('Error: ' + error.message);
        if(btn && document.body.contains(btn)) { 
             btn.innerHTML = originalHtml; btn.disabled = false;
        }
    }
}

function printVoucher() {
    const printContent = document.getElementById('voucherContent').innerHTML;
    const styles = Array.from(document.head.querySelectorAll('link[rel="stylesheet"], style'));
    let printWindow = window.open('', '_blank', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Print Voucher</title>');
    styles.forEach(style => {
        if (style.tagName === 'LINK') {
            printWindow.document.write(`<link rel="stylesheet" href="${style.href}">`);
        } else {
            printWindow.document.write(style.outerHTML);
        }
    });
    printWindow.document.write('<style>body { padding: 2rem; }</style></head><body>');
    printWindow.document.write(printContent);
    printWindow.document.close(); 
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250); 
}

function showPdfPreview(pdfUrl) {
    var modalEl = document.getElementById('pdfPreviewModal');
    if(modalEl){
        var pdfPreviewModalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        document.getElementById('pdfPreviewFrame').src = pdfUrl;
        pdfPreviewModalInstance.show();
    } else { console.error("Modal 'pdfPreviewModal' not found."); }
}

const modalDetailedLeaveTypes = <?= json_encode($detailed_leave_types_view) ?>;
const modalLeaveTypeSelect = document.getElementById('modal_leave_type');
const modalAnnualBalanceSpan = document.getElementById('modal_annual_leave_balance');
const modalRemainingBalanceSpan = document.getElementById('modal_remaining_leave_balance');
const modalRecupBalanceSpan = document.getElementById('modal_recup_balance');
const modalStartDateInput = document.getElementById('modal_start_date');
const modalEndDateInput = document.getElementById('modal_end_date');

const employeeAnnualBalance = <?= json_encode($employee['annual_leave_balance'] ?? 0) ?>;
const employeeRemainingBalance = <?= json_encode($employee['remaining_leave_balance'] ?? 0) ?>;
const employeeRecupBalance = <?= json_encode($current_recup_balance ?? 0) ?>;

function updateModalLeaveTypeUI() {
    if (!modalLeaveTypeSelect) return;
    updateModalPermittedDays();
    updateModalLeaveTypesAvailability();
}

function updateModalPermittedDays() {
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
}

function updateModalLeaveTypesAvailability() {
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
}

function togglePromotionFields() {
    const decisionTypeSelect = document.getElementById('decision_type');
    if (!decisionTypeSelect) return;
    
    const positionFields = document.getElementById('position_fields');
    const newPositionSelect = document.getElementById('new_position');
    const salaryField = document.getElementById('salary_field');
    const newSalaryInput = document.getElementById('new_salary');
    const selectedType = decisionTypeSelect.value;

    newPositionSelect.removeAttribute('required');
    newSalaryInput.removeAttribute('required');
    positionFields.style.display = 'none';
    salaryField.style.display = 'none';

    if (selectedType === 'promotion_only') {
        positionFields.style.display = 'block';
        newPositionSelect.setAttribute('required', 'required');
    } else if (selectedType === 'promotion_salary') {
        positionFields.style.display = 'block';
        newPositionSelect.setAttribute('required', 'required');
        salaryField.style.display = 'block';
        newSalaryInput.setAttribute('required', 'required');
    } else if (selectedType === 'salary_only') {
        salaryField.style.display = 'block';
        newSalaryInput.setAttribute('required', 'required');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tabTriggerEl = document.querySelector(`.nav-tabs a[href="${hash}"]`);
        if (tabTriggerEl) {
            var tab = bootstrap.Tab.getInstance(tabTriggerEl) || new bootstrap.Tab(tabTriggerEl);
            tab.show();
        }
    }

    var newAddLeaveModalEl = document.getElementById('newAddLeaveModal');
    if (newAddLeaveModalEl) {
        newAddLeaveModalEl.addEventListener('show.bs.modal', function () {
            modalAnnualBalanceSpan.textContent = parseFloat(employeeAnnualBalance).toFixed(1);
            modalRemainingBalanceSpan.textContent = parseFloat(employeeRemainingBalance).toFixed(1);
            modalRecupBalanceSpan.textContent = parseFloat(employeeRecupBalance).toFixed(1);
            modalStartDateInput.valueAsDate = new Date();
            modalEndDateInput.value = modalStartDateInput.value;
            updateModalLeaveTypeUI();
        });
    }

    if (modalLeaveTypeSelect) {
        modalLeaveTypeSelect.addEventListener('change', updateModalLeaveTypeUI);
    }
    
    const renewRadio = document.getElementById('decision_renew');
    const renewOptions = document.getElementById('renew_options');
    if(renewRadio) {
        document.querySelectorAll('input[name="decision"]').forEach((elem) => {
            elem.addEventListener("change", function(event) {
                renewOptions.style.display = renewRadio.checked ? 'block' : 'none';
            });
        });
        if(renewRadio.checked) { renewOptions.style.display = 'block'; }
    }
    
    const decisionTypeSelect = document.getElementById('decision_type');
    if (decisionTypeSelect) {
        decisionTypeSelect.addEventListener('change', togglePromotionFields);
        togglePromotionFields();
    }

    const defaultQuestions = <?= json_encode($default_questions) ?>;
    const typeSelect = document.getElementById('questionnaire_type');
    const questionsContainer = document.getElementById('questions_container');

    function updateQuestionFields() {
        if (!typeSelect || !questionsContainer) return;
        const selectedType = typeSelect.value;
        const questions = defaultQuestions[selectedType] || ["", "", ""];
        let questionsHtml = '';
        questions.forEach((questionText, index) => {
            questionsHtml += `
                <div class="input-group mb-2">
                    <span class="input-group-text">${index + 1}.</span>
                    <input type="text" name="questions[]" class="form-control" placeholder="Question ${index + 1}" value="${questionText.replace(/"/g, '&quot;')}" required>
                </div>`;
        });
        questionsContainer.innerHTML = questionsHtml;
    }

    if(typeSelect) {
        typeSelect.addEventListener('change', updateQuestionFields);
        updateQuestionFields();
    }
});

<?php if ($pdf_to_show && $pdf_url): ?>
window.addEventListener('DOMContentLoaded', function() {
    var myModal = new bootstrap.Modal(document.getElementById('pdfModal'));
    myModal.show();
});
<?php endif; ?>
</script>

<?php include __DIR__ . '../../../../../includes/footer.php'; ?>