// Courier portal — mobile sidebar drawer open/close.
// Toggles inline display + an `.is-open` class that courier.css animates.
(function () {
    var backdrop = document.getElementById('cx-backdrop');
    var drawer   = document.getElementById('cx-drawer');
    var openBtn  = document.getElementById('cx-open-sidebar');
    var closeBtn = document.getElementById('cx-close-sidebar');

    function show() {
        if (!drawer || !backdrop) return;
        drawer.style.display = 'flex';
        backdrop.style.display = 'block';
        requestAnimationFrame(function () {
            drawer.classList.add('is-open');
            backdrop.style.opacity = '1';
        });
        document.body.style.overflow = 'hidden';
    }
    function hide() {
        if (!drawer || !backdrop) return;
        drawer.classList.remove('is-open');
        backdrop.style.opacity = '0';
        setTimeout(function () {
            drawer.style.display = 'none';
            backdrop.style.display = 'none';
        }, 250);
        document.body.style.overflow = '';
    }

    if (openBtn)  openBtn.addEventListener('click', show);
    if (closeBtn) closeBtn.addEventListener('click', hide);
    if (backdrop) backdrop.addEventListener('click', hide);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
})();
