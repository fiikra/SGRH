// assets/js/employees/profile/modals.js

// Manages modal opening, closing, validation, and dynamic content for Talenteo-style modals

document.addEventListener('DOMContentLoaded', function() {
    // Departure reason custom input toggle
    const departureReasonSelect = document.getElementById('departure_reason');
    const customReasonWrapper = document.getElementById('custom_reason_wrapper');
    if (departureReasonSelect && customReasonWrapper) {
        departureReasonSelect.addEventListener('change', function() {
            if (this.value === 'Autre') {
                customReasonWrapper.style.display = 'block';
                document.getElementById('custom_reason').required = true;
            } else {
                customReasonWrapper.style.display = 'none';
                document.getElementById('custom_reason').required = false;
            }
        });
    }

    // Promotion modal: show/hide fields based on type
    const decisionTypeSelect = document.getElementById('decision_type');
    function togglePromotionFields() {
        const positionFields = document.getElementById('position_fields');
        const salaryField = document.getElementById('salary_field');
        const newPositionSelect = document.getElementById('new_position');
        const newSalaryInput = document.getElementById('new_salary');
        if (!decisionTypeSelect) return;

        const selectedType = decisionTypeSelect.value;
        positionFields.style.display = 'none';
        salaryField.style.display = 'none';
        newPositionSelect.removeAttribute('required');
        newSalaryInput.removeAttribute('required');

        if (selectedType === 'promotion_only') {
            positionFields.style.display = 'block';
            newPositionSelect.setAttribute('required', 'required');
        } else if (selectedType === 'promotion_salary') {
            positionFields.style.display = 'block';
            salaryField.style.display = 'block';
            newPositionSelect.setAttribute('required', 'required');
            newSalaryInput.setAttribute('required', 'required');
        } else if (selectedType === 'salary_only') {
            salaryField.style.display = 'block';
            newSalaryInput.setAttribute('required', 'required');
        }
    }
    if (decisionTypeSelect) {
        decisionTypeSelect.addEventListener('change', togglePromotionFields);
        togglePromotionFields();
    }

    // Modal for updating questionnaire
    window.openUpdateQuestionnaireModal = function(data) {
        var modalElement = document.getElementById('updateQuestionnaireModal');
        try {
            document.getElementById('update_q_ref').value = data.reference_number;
            document.getElementById('update_status').value = data.status;
            document.getElementById('update_response_summary').value = data.response_summary || '';
            document.getElementById('update_decision').value = data.decision || '';
        } catch (e) {
            alert("Erreur lors du remplissage du formulaire: " + e.message);
            return;
        }
        try {
            var myModal = new bootstrap.Modal(modalElement);
            myModal.show();
        } catch (e) {
            alert("Impossible d'afficher le modal. Erreur: " + e.message);
        }
    };

    // Dynamic questionnaire questions
    const typeSelect = document.getElementById('questionnaire_type');
    const questionsContainer = document.getElementById('questions_container');
    if (typeSelect && questionsContainer && window.defaultQuestions) {
        function updateQuestionFields() {
            const selectedType = typeSelect.value;
            const questions = window.defaultQuestions[selectedType] || ["", "", ""];
            let questionsHtml = '';
            questions.forEach((questionText, index) => {
                questionsHtml += `
                    <div class="input-group mb-2">
                        <span class="input-group-text">${index + 1}.</span>
                        <input 
                            type="text" 
                            name="questions[]" 
                            class="form-control" 
                            placeholder="Question ${index + 1}" 
                            value="${questionText.replace(/"/g, '&quot;')}" 
                            required>
                    </div>
                `;
            });
            questionsContainer.innerHTML = questionsHtml;
        }
        typeSelect.addEventListener('change', updateQuestionFields);
        updateQuestionFields();
    }

    // Show PDF preview modal
    window.showPdfPreview = function(pdfUrl) {
        var modalEl = document.getElementById('pdfPreviewModal');
        if(modalEl){
            var pdfPreviewModalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            document.getElementById('pdfPreviewFrame').src = pdfUrl;
            pdfPreviewModalInstance.show();
        } else { console.error("Modal 'pdfPreviewModal' non trouvée."); }
    };

    // Print voucher modal
    window.printVoucher = function() {
        const printContent = document.getElementById('voucherContent').innerHTML;
        const styles = Array.from(document.head.querySelectorAll('link[rel="stylesheet"], style'));
        let printWindow = window.open('', '_blank', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Imprimer Voucher</title>');
        styles.forEach(style => {
            if (style.tagName === 'LINK') {
                printWindow.document.write(`<link rel="stylesheet" href="${style.href}">`);
            } else {
                printWindow.document.write(style.outerHTML);
            }
        });
        printWindow.document.write('</head><body>');
        printWindow.document.write(`<div class="container p-3">${printContent}</div>`);
        printWindow.document.close(); 
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250); 
    };
});