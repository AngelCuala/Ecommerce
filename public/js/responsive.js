// Any screen size: the ☰ button slides the sidebar in on small screens, and wide tables scroll sideways in their card.
(function () {
    var body = document.body;
    var toggle = document.querySelector('[data-nav-toggle]');
    function setOpen(open) {
        body.classList.toggle('nav-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (toggle) toggle.addEventListener('click', function () { setOpen(!body.classList.contains('nav-open')); });
    document.querySelectorAll('[data-nav-close]').forEach(function (el) { el.addEventListener('click', function () { setOpen(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    document.querySelectorAll('.sidebar a').forEach(function (a) { a.addEventListener('click', function () { setOpen(false); }); });

    // Wrap each table that isn't already in a sideways-scrolling box.
    // Already inside its own sideways-scrolling box? (The page's main area scrolls too, but a card in between that
    // hides overflow would cut the table off, so only a box below any such card counts.)
    function scrolls(el) {
        for (var p = el.parentElement; p && p !== body; p = p.parentElement) {
            if (p.matches('.content, main, .main')) return false;
            var o = getComputedStyle(p).overflowX;
            if (o === 'auto' || o === 'scroll') return true;
            if (o === 'hidden' || o === 'clip') return false;
        }
        return false;
    }
    document.querySelectorAll('.content table, main table').forEach(function (t) {
        if (scrolls(t)) return;
        var box = document.createElement('div');
        box.className = 'table-scroll';
        box.style.overflowX = 'auto';
        box.style.maxWidth = '100%';
        t.parentNode.insertBefore(box, t);
        box.appendChild(t);
    });

    // A table still too wide for its card shows each row as a card instead (column name beside each value), so the
    // buttons in the last column are always on screen; nobody has to slide the table sideways to find them.
    function labelCells(t) {
        if (t.dataset.labelled || !t.tHead || !t.tHead.rows.length) return;
        var head = t.tHead.rows[t.tHead.rows.length - 1], names = [];
        for (var i = 0; i < head.cells.length; i++) {
            var span = head.cells[i].colSpan || 1, text = head.cells[i].textContent.replace(/\s+/g, ' ').trim();
            for (var k = 0; k < span; k++) names.push(text);
        }
        for (var b = 0; b < t.tBodies.length; b++) {
            for (var r = 0; r < t.tBodies[b].rows.length; r++) {
                var row = t.tBodies[b].rows[r], col = 0;
                for (var c = 0; c < row.cells.length; c++) {
                    var cell = row.cells[c];
                    cell.setAttribute('data-label', cell.colSpan > 1 ? '' : (names[col] || ''));
                    col += cell.colSpan || 1;
                }
            }
        }
        t.dataset.labelled = '1';
    }
    var tables = [].slice.call(document.querySelectorAll('.content table, main table')).filter(function (t) {
        return t.tHead && t.tBodies.length && !t.closest('td') && !t.hasAttribute('data-no-stack');
    });
    function fit() {
        tables.forEach(function (t) {
            t.classList.remove('table-stacked');
            var box = t.parentElement;
            var hasRows = [].some.call(t.tBodies, function (b) { return [].some.call(b.rows, function (r) { return r.cells.length > 1; }); });
            if (hasRows && box && box.scrollWidth > box.clientWidth + 2) {
                labelCells(t);
                t.classList.add('table-stacked');
            }
        });
    }
    fit();
    var timer;
    window.addEventListener('resize', function () { clearTimeout(timer); timer = setTimeout(fit, 120); });
    window.addEventListener('load', fit);
})();
