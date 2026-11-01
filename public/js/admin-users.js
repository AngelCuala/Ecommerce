// User Accounts table — role filter tabs
document.addEventListener('DOMContentLoaded', function () {
    const tabs   = document.querySelectorAll('.role-tab');
    const rows   = document.querySelectorAll('.user-row');
    const noRes  = document.getElementById('noResults');
    let activeRole = 'all';

    function applyFilters() {
        let visible = 0;
        rows.forEach(row => {
            const show = activeRole === 'all' || row.dataset.role === activeRole;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        noRes.classList.toggle('hidden', visible > 0 || rows.length === 0);
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            activeRole = this.dataset.role;
            tabs.forEach(t => t.classList.toggle('is-active', t === this));
            applyFilters();
        });
    });
});
