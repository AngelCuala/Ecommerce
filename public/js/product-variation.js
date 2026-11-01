// Product page — select a variation and highlight the active button
function selectVariation(btn) {
    document.querySelectorAll('.variation-btn').forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    document.getElementById('variation-input').value = btn.dataset.variation;
}
