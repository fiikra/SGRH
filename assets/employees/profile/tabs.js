// assets/js/employees/profile/tabs.js

// Handles tab navigation, sticky tab headers, and highlights for Talenteo-style UI

document.addEventListener('DOMContentLoaded', function() {
    // Activate tab based on URL hash (if any)
    const hash = window.location.hash;
    if (hash) {
        const tabTriggerEl = document.querySelector(`.nav-tabs a[href="${hash}"]`);
        if (tabTriggerEl) {
            let tab = bootstrap.Tab.getInstance(tabTriggerEl);
            if (!tab) tab = new bootstrap.Tab(tabTriggerEl);
            tab.show();
        }
    }

    // Scroll to active tab on page load, improves UX for long profile pages
    const activeTab = document.querySelector('.nav-tabs .nav-link.active');
    if (activeTab) {
        activeTab.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    // Update hash when tab changes
    document.querySelectorAll('.nav-tabs a').forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function(e) {
            window.location.hash = e.target.getAttribute('href');
        });
    });

    // Optional: Sticky tab header on scroll (Talenteo style)
    const tabHeader = document.querySelector('.card-header .nav-tabs');
    if (tabHeader) {
        const stickyOffset = tabHeader.offsetTop;
        window.addEventListener('scroll', function() {
            if (window.scrollY > stickyOffset) {
                tabHeader.classList.add('talenteo-sticky-tabs');
            } else {
                tabHeader.classList.remove('talenteo-sticky-tabs');
            }
        });
    }
});