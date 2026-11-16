{{--
    Light / dark mode for every web page (the buyer shop, seller, admin, courier, logistics and sorting
    center portals, sign-in pages). Include it at the top of <head> so the page never flashes white.

    The choice is "light" (the default), "dark" or "system" (follow the device), kept in this browser (localStorage).
    Dark mode flips the page's lightness but keeps its colours (invert + hue-rotate), so every page works
    without its own dark colours; photos, videos, ALLIE and anything marked data-keep-colors are flipped
    back so they look the same in both modes. Printing always uses the light page.
    The switch: @include('partials.theme-toggle') (an icon with a menu) or ['variant' => 'row'] (sidebars).
--}}
<script>
(function () {
    var KEY = 'alvy-theme', root = document.documentElement, choice;
    try { choice = localStorage.getItem(KEY); } catch (e) {}
    if (choice !== 'dark' && choice !== 'system') choice = 'light';   // light until someone picks otherwise
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function mark() {
        document.querySelectorAll('[data-theme-set]').forEach(function (b) {
            var on = b.getAttribute('data-theme-set') === choice;
            b.setAttribute(b.getAttribute('role') === 'menuitemradio' ? 'aria-checked' : 'aria-pressed', on ? 'true' : 'false');
        });
    }
    function apply() {
        var dark = choice === 'dark' || (choice === 'system' && !!media && media.matches);
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        root.setAttribute('data-theme-choice', choice);
        mark();
    }
    function closeMenus(except) {
        document.querySelectorAll('[data-theme-wrap].open').forEach(function (w) {
            if (w === except) return;
            w.classList.remove('open');
            var b = w.querySelector('[data-theme-menu]');
            if (b) b.setAttribute('aria-expanded', 'false');
        });
    }
    apply();
    if (media) {
        var follow = function () { if (choice === 'system') apply(); };
        media.addEventListener ? media.addEventListener('change', follow) : media.addListener(follow);
    }

    window.AlvyTheme = {
        get: function () { return choice; },
        set: function (value) {
            choice = value === 'dark' || value === 'system' ? value : 'light';
            try { choice === 'light' ? localStorage.removeItem(KEY) : localStorage.setItem(KEY, choice); } catch (e) {}
            apply();
        },
    };
    // Another tab changed it: follow along.
    window.addEventListener('storage', function (e) { if (e.key === KEY) { choice = e.newValue === 'dark' || e.newValue === 'system' ? e.newValue : 'light'; apply(); } });

    document.addEventListener('click', function (e) {
        var pick = e.target.closest && e.target.closest('[data-theme-set]');
        if (pick) { window.AlvyTheme.set(pick.getAttribute('data-theme-set')); closeMenus(); return; }
        var toggle = e.target.closest && e.target.closest('[data-theme-menu]');
        if (toggle) {
            var wrap = toggle.closest('[data-theme-wrap]'), open = !wrap.classList.contains('open');
            closeMenus(wrap);
            wrap.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) { var first = wrap.querySelector('[aria-checked="true"]') || wrap.querySelector('[data-theme-set]'); if (first) first.focus(); }
            return;
        }
        if (!(e.target.closest && e.target.closest('[data-theme-wrap]'))) closeMenus();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = document.querySelector('[data-theme-wrap].open');
        if (open) { closeMenus(); var b = open.querySelector('[data-theme-menu]'); if (b) b.focus(); }
    });
    // ALVY's navy panels (top bar, footer, chat headers, banners, hero cards) keep their real colours in dark mode,
    // instead of flipping to pale blue. Found by their background colour, so new navy panels are covered too.
    var NAVY = /rgb\((0, 43, 77|11, 74, 122|0, 61, 107|11, 61, 102|0, 58, 102)\)/;
    // Orange buttons and badges keep their colours too, so their white text stays white.
    var ORANGE = /^rgb\((250, 78, 28|217, 61, 14|224, 67, 26|251, 112, 72|255, 122, 69)\)$/;
    var WHITE = /^rgba?\(255, 255, 255(, 1)?\)$/;
    // Orange gradient buttons and bars ("Next", "Log In") too.
    var ORANGE_FADE = /gradient\([^)]*rgb\(250, 78, 28\)/;
    function keepNavy(scope) {
        var walker = document.createTreeWalker(scope || document.body, NodeFilter.SHOW_ELEMENT, {
            acceptNode: function (el) {
                if (el.hasAttribute('data-keep-colors')) return NodeFilter.FILTER_REJECT;   // already kept, with all inside
                if (el instanceof SVGElement) return NodeFilter.FILTER_REJECT;
                var cs = getComputedStyle(el), bg = cs.backgroundColor;
                if (NAVY.test(bg) || NAVY.test(cs.backgroundImage) || ORANGE.test(bg) || ORANGE_FADE.test(cs.backgroundImage)) { el._keep = true; return NodeFilter.FILTER_ACCEPT; }
                // White rounded or raised boxes (cards, panels, menus, fields, chat bubbles) become a lifted slate.
                if (WHITE.test(bg) && (cs.borderTopLeftRadius !== '0px' || cs.boxShadow !== 'none')) { el._surface = /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName) ? 'field' : 'card'; return NodeFilter.FILTER_ACCEPT; }
                return NodeFilter.FILTER_SKIP;
            },
        });
        var found = [];
        while (walker.nextNode()) found.push(walker.currentNode);
        found.forEach(function (el) {
            if (el._keep) { if (!el.parentElement || !el.parentElement.closest('[data-keep-colors]')) el.setAttribute('data-keep-colors', ''); }
            else el.setAttribute('data-surface', el._surface);
        });
    }
    document.addEventListener('DOMContentLoaded', function () { mark(); keepNavy(); });
})();
</script>
<style>
    @media screen {
        /* brightness lifts text back to a clean white after contrast softens the black background */
        html[data-theme="dark"] { filter: invert(1) hue-rotate(180deg) contrast(.9) brightness(1.1); background: #fff; }
        /* Pictures keep their real colours (flipped back). */
        /* Checkboxes and radio buttons too: a clear white box, and orange with a white tick when ticked. */
        html[data-theme="dark"] :is(img, video, canvas, iframe, svg.ai-avatar, [data-keep-colors], .auth-photo, input[type="checkbox"], input[type="radio"]):not(:is([data-keep-colors], svg.ai-avatar) *) {
            filter: invert(1) hue-rotate(180deg);
        }
        /* A pop-up opened with showModal() is drawn above the page (the top layer), outside the page's filter:
           give it the same flip, so it is dark like the page and the pictures inside (the scanner's camera) look right. */
        html[data-theme="dark"] dialog:modal { filter: invert(1) hue-rotate(180deg) contrast(.9) brightness(1.1); }
        html[data-theme="dark"] { scrollbar-color: #9a9a9a #e6e6e6; }
        /* Cards and panels: these cool light greys come out as a lifted slate (warm greys would turn brown);
           form fields a shade darker so they stand out inside a card. */
        html[data-theme="dark"] [data-surface="card"] { background-color: #D5DAE0 !important; }
        html[data-theme="dark"] [data-surface="field"] { background-color: #E6E9ED !important; }
    }

    /* The switch */
    .theme-pick { position: relative; display: inline-flex; }
    .theme-btn, .theme-opt, .theme-seg button { margin: 0; border: 0; background: transparent; font: inherit; cursor: pointer; -webkit-appearance: none; appearance: none; }
    .theme-btn { display: flex; height: 2.25rem; width: 2.25rem; padding: 0; align-items: center; justify-content: center; border-radius: .75rem; color: inherit; transition: background .15s; }
    .theme-btn > span { display: flex; }
    .theme-pick svg { flex: none; }
    .theme-btn:hover, .theme-pick.open .theme-btn { background: rgba(127,127,127,.18); }
    .theme-btn:focus-visible, .theme-opt:focus-visible, .theme-seg button:focus-visible { outline: 2px solid #fa4e1c; outline-offset: 2px; }
    .theme-btn .ti-dark, html[data-theme="dark"] .theme-btn .ti-light { display: none; }
    html[data-theme="dark"] .theme-btn .ti-dark { display: flex; }
    .theme-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 90; display: none; min-width: 9.5rem; padding: .3rem; border-radius: .8rem;
                  background: #fff; border: 1px solid #ECE7E3; box-shadow: 0 14px 34px -12px rgba(0,0,0,.35); }
    .theme-pick.open .theme-menu { display: block; }
    .theme-opt { display: flex; width: 100%; align-items: center; gap: .55rem; padding: .45rem .6rem; border-radius: .55rem; font-size: .8125rem; font-weight: 600; color: #44403C; text-align: left; }
    .theme-opt:hover { background: #F5F2EF; }
    .theme-opt[aria-checked="true"] { color: #d93d0e; background: #FFF1EB; }
    .theme-opt .tick { margin-left: auto; visibility: hidden; }
    .theme-opt[aria-checked="true"] .tick { visibility: visible; }
    .theme-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .theme-row-label { font-size: .75rem; font-weight: 600; color: #8a7f78; }
    .theme-seg { display: inline-flex; gap: .125rem; padding: .1875rem; border-radius: .6rem; background: #F5F2EF; }
    .theme-seg button { display: flex; height: 1.75rem; width: 2rem; padding: 0; align-items: center; justify-content: center; border-radius: .45rem; color: #78716C; }
    .theme-seg button:hover { color: #222; }
    .theme-seg button[aria-pressed="true"] { background: #fff; color: #fa4e1c; box-shadow: 0 1px 2px rgba(0,0,0,.15); }
    @media print { .theme-pick, .theme-row { display: none !important; } }
</style>
