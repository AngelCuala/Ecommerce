// Admin compliance — highlight the action button by severity
function updateActionBtn(val) {
    const btn = document.getElementById('action-btn');
    const warn = document.getElementById('action-warning');
    const dangerous = ['product_removed','account_suspended','account_deactivated'];
    warn.classList.toggle('hidden', !dangerous.includes(val));
    // Orange for a warning, red for anything that removes or restricts.
    btn.style.background = dangerous.includes(val) ? '#DC2626' : '#fa4e1c';
}
