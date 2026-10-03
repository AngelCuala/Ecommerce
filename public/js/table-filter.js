// Generic table row filter — used by sorting-center list pages
function filterTable(q, tbodyId) {
    q = q.toLowerCase();
    document.querySelectorAll('#' + tbodyId + ' tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
