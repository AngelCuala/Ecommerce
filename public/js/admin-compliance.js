// Admin compliance — highlight the action button by severity
function updateActionBtn(val) {
    const btn = document.getElementById('action-btn');
    const warn = document.getElementById('action-warning');
    const dangerous = ['product_removed','account_suspended','account_deactivated'];
    warn.classList.toggle('hidden', !dangerous.includes(val));
    const colors = {
        'warning':             '#D97706',
        'product_removed':     '#fa4e1c',
        'account_suspended':   '#DC2626',
        'account_deactivated': '#6B7280',
    };
    btn.style.background = colors[val] || '#fa4e1c';
}
