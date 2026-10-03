// Mobile sidebar drawer — open/close for admin & seller panels
(function () {
    var backdrop = document.getElementById('sidebar-backdrop');
    var drawer   = document.getElementById('mobile-sidebar');
    var openBtn  = document.getElementById('open-sidebar-btn');
    var closeBtn = document.getElementById('close-sidebar-btn');
    function show() {
        if (!drawer || !backdrop) return;
        drawer.style.display = 'flex'; backdrop.style.display = 'block';
        requestAnimationFrame(function () {
            drawer.classList.remove('-translate-x-full');
            backdrop.classList.remove('opacity-0');
        });
        document.body.style.overflow = 'hidden';
    }
    function hide() {
        if (!drawer || !backdrop) return;
        drawer.classList.add('-translate-x-full');
        backdrop.classList.add('opacity-0');
        setTimeout(function () { drawer.style.display = 'none'; backdrop.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
    }
    if (openBtn)  openBtn.addEventListener('click', show);
    if (closeBtn) closeBtn.addEventListener('click', hide);
    if (backdrop) backdrop.addEventListener('click', hide);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
})();
