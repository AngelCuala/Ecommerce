// Flash toasts — auto-dismiss after a few seconds
setTimeout(function () {
    document.querySelectorAll('#toast-stack .toast').forEach(function (t) {
        t.style.transition = 'opacity .4s ease, transform .4s ease';
        t.style.opacity = '0';
        t.style.transform = 'translateX(12px)';
        setTimeout(function () { t.remove(); }, 400);
    });
}, 4000);
