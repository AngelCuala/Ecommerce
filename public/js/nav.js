// Public header — mobile nav panel + user menu dropdown toggles
(function () {
    // While a menu is open the page behind it doesn't scroll (the scrollbar's width is kept so nothing jumps).
    var root = document.documentElement;
    function lockScroll(on) {
        if (on) {
            var bar = window.innerWidth - root.clientWidth;
            root.style.overflow = 'hidden';
            if (bar > 0) document.body.style.paddingRight = bar + 'px';
        } else {
            root.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    }
    function sync() {
        var open = ['mobile-nav-panel', 'user-menu-panel'].some(function (id) {
            var el = document.getElementById(id);
            return el && !el.hidden;
        });
        lockScroll(open);
    }

    var btn = document.getElementById('mobile-nav-trigger');
    var pnl = document.getElementById('mobile-nav-panel');
    if (btn && pnl) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            pnl.hidden = !pnl.hidden; sync();
            btn.setAttribute('aria-expanded', String(!pnl.hidden));
        });
        document.addEventListener('click', function (e) {
            if (!pnl.hidden && !pnl.contains(e.target) && e.target !== btn) { pnl.hidden = true; sync(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !pnl.hidden) { pnl.hidden = true; sync(); btn.focus(); }
        });
    }

    var uBtn = document.getElementById('user-menu-btn');
    var uPnl = document.getElementById('user-menu-panel');
    if (uBtn && uPnl) {
        uBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            uPnl.hidden = !uPnl.hidden; sync();
            uBtn.setAttribute('aria-expanded', String(!uPnl.hidden));
        });
        document.addEventListener('click', function (e) {
            if (!uPnl.hidden && !uPnl.contains(e.target) && e.target !== uBtn) { uPnl.hidden = true; sync(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !uPnl.hidden) { uPnl.hidden = true; sync(); uBtn.focus(); }
        });
    }
})();
