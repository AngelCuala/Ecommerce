// Product page — select a variation and highlight the active button
function selectVariation(btn) {
    document.querySelectorAll('.variation-btn').forEach(b => {
        b.style.borderColor = '#cfdce8';
        b.style.background = '';
        b.style.color = '#1a4d6e';
    });
    btn.style.borderColor = '#fa4e1c';
    btn.style.background = '#FFF6EE';
    btn.style.color = '#fa4e1c';
    document.getElementById('variation-input').value = btn.dataset.variation;
}
