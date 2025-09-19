document.addEventListener('DOMContentLoaded', function() {
    // Activer les tooltips Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Activer les popovers Bootstrap
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Confirmation avant suppression
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.')) {
                e.preventDefault();
            }
        });
    });

    // Gestion des dates dans les formulaires de congé
    const startDateInputs = document.querySelectorAll('input[name="start_date"]');
    const endDateInputs = document.querySelectorAll('input[name="end_date"]');
    
    if (startDateInputs.length && endDateInputs.length) {
        startDateInputs.forEach((startDate, index) => {
            // Définir la date de début par défaut à aujourd'hui
            if (!startDate.value) {
                const today = new Date().toISOString().split('T')[0];
                startDate.value = today;
            }

            // Synchroniser la date de fin
            startDate.addEventListener('change', function() {
                if (!endDateInputs[index].value || new Date(endDateInputs[index].value) < new Date(this.value)) {
                    endDateInputs[index].value = this.value;
                }
            });
        });
    }

    // Calcul automatique de la durée des congés
    const calculateLeaveDays = () => {
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');
        const daysField = document.querySelector('input[name="days_requested"]');
        
        if (startDate && endDate && daysField) {
            const calculate = () => {
                if (startDate.value && endDate.value) {
                    const start = new Date(startDate.value);
                    const end = new Date(endDate.value);
                    const diffTime = Math.abs(end - start);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // Inclure le premier jour
                    daysField.value = diffDays;
                }
            };
            
            startDate.addEventListener('change', calculate);
            endDate.addEventListener('change', calculate);
        }
    };
    
    calculateLeaveDays();

    // Preview des images uploadées
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.getAttribute('data-preview');
            const previewElement = document.getElementById(previewId);
            const file = this.files[0];
            
            if (file && previewElement) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (previewElement.tagName === 'IMG') {
                        previewElement.src = e.target.result;
                    } else {
                        previewElement.style.backgroundImage = `url(${e.target.result})`;
                    }
                    previewElement.style.display = 'block';
                };
                
                reader.readAsDataURL(file);
            }
        });
    });

    // Gestion des onglets avec localStorage
    const tabPanes = document.querySelectorAll('.tab-pane');
    const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
    
    if (tabPanes.length && tabLinks.length) {
        // Restaurer l'onglet actif
        const activeTab = localStorage.getItem('activeTab');
        if (activeTab) {
            const tab = document.querySelector(`[data-bs-target="${activeTab}"]`);
            if (tab) {
                new bootstrap.Tab(tab).show();
            }
        }
        
        // Sauvegarder l'onglet actif
        tabLinks.forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.getAttribute('data-bs-target');
                localStorage.setItem('activeTab', target);
            });
        });
    }

    // Auto-complétion pour les champs de recherche
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const dropdownId = this.getAttribute('data-dropdown');
            const dropdown = document.getElementById(dropdownId);
            
            if (dropdown && this.value.length > 2) {
                // Simuler une requête AJAX (à remplacer par une vraie requête)
                dropdown.style.display = 'block';
            } else if (dropdown) {
                dropdown.style.display = 'none';
            }
        });
    });

    // Gestion des modales dynamiques
    const dynamicModals = document.querySelectorAll('[data-modal-target]');
    dynamicModals.forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-modal-target');
            const modal = document.getElementById(target);
            
            if (modal) {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
                
                // Charger le contenu dynamique si nécessaire
                const loadUrl = this.getAttribute('data-load-url');
                if (loadUrl) {
                    fetch(loadUrl)
                        .then(response => response.text())
                        .then(html => {
                            modal.querySelector('.modal-content').innerHTML = html;
                        });
                }
            }
        });
    });
});

// Fonction pour afficher les messages toast
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
    
    // Supprimer le toast après fermeture
    toastEl.addEventListener('hidden.bs.toast', function() {
        toastEl.remove();
    });
}

// Fonction pour confirmer avant une action
function confirmAction(message, callback) {
    if (confirm(message)) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}