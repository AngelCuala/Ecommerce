// Public header — mobile nav panel + user menu dropdown toggles
(function () {
    var btn = document.getElementById('mobile-nav-trigger');
    var pnl = document.getElementById('mobile-nav-panel');
    if (btn && pnl) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            pnl.hidden = !pnl.hidden;
            btn.setAttribute('aria-expanded', String(!pnl.hidden));
        });
        document.addEventListener('click', function (e) {
            if (!pnl.hidden && !pnl.contains(e.target) && e.target !== btn) pnl.hidden = true;
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !pnl.hidden) { pnl.hidden = true; btn.focus(); }
        });
    }

    var uBtn = document.getElementById('user-menu-btn');
    var uPnl = document.getElementById('user-menu-panel');
    if (uBtn && uPnl) {
        uBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            uPnl.hidden = !uPnl.hidden;
            uBtn.setAttribute('aria-expanded', String(!uPnl.hidden));
        });
        document.addEventListener('click', function (e) {
            if (!uPnl.hidden && !uPnl.contains(e.target) && e.target !== uBtn) uPnl.hidden = true;
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !uPnl.hidden) { uPnl.hidden = true; uBtn.focus(); }
        });
    }
})();
