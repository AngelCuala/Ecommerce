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
})();
