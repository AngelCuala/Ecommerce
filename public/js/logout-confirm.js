// "Are you sure?" pop-ups (styled, instead of the browser's confirm box).
//  - Sign out: any form that posts to a .../logout address asks "Sign out?" first.
//  - Any other form can ask too: <form data-confirm="Message" data-confirm-title="Title?" data-confirm-ok="Button text">.
// Cancel (or Esc, or a click outside) does nothing; the confirm button sends the form.
(function () {
    function isLogout(form) {
        var action = form.getAttribute('action') || '';
        return form.method && form.method.toLowerCase() === 'post' && /\/logout\/?(\?.*)?$/.test(action.split('#')[0]);
    }

    var ICONS = {
        logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        alert:  '<path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
    };

    var dialog = null, pendingForm = null, lastFocus = null;

    function build() {
        var wrap = document.createElement('div');
        wrap.id = 'logout-confirm';   // id kept for the sign-out pop-up
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-modal', 'true');
        wrap.setAttribute('aria-labelledby', 'logout-confirm-title');
        wrap.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:none;align-items:center;justify-content:center;padding:16px;'
            + 'background:rgba(15,23,42,.45);backdrop-filter:blur(2px);font-family:Inter,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;';
        wrap.innerHTML =
            '<div data-box style="width:100%;max-width:380px;background:#fff;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.25);padding:22px 22px 18px;text-align:center;">'
          +   '<div style="margin:0 auto 12px;width:48px;height:48px;border-radius:999px;background:#FEF2F2;color:#DC2626;display:flex;align-items:center;justify-content:center;">'
          +     '<svg data-icon width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"></svg>'
          +   '</div>'
          +   '<h2 id="logout-confirm-title" style="margin:0;font-size:18px;font-weight:700;color:#1f2937;"></h2>'
          +   '<p data-message style="margin:6px 0 18px;font-size:14px;line-height:1.45;color:#6b7280;"></p>'
          +   '<div style="display:flex;gap:10px;">'
          +     '<button type="button" data-cancel style="flex:1;padding:10px 12px;border-radius:10px;border:1px solid #E5E7EB;background:#fff;color:#374151;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>'
          +     '<button type="button" data-ok style="flex:1;padding:10px 12px;border-radius:10px;border:0;background:#DC2626;color:#fff;font-size:14px;font-weight:700;cursor:pointer;"></button>'
          +   '</div>'
          + '</div>';
        document.body.appendChild(wrap);

        wrap.querySelector('[data-cancel]').addEventListener('click', close);
        wrap.querySelector('[data-ok]').addEventListener('click', function () {
            var form = pendingForm;
            if (!form) return;
            if (!form.reportValidity()) { close(); return; }   // e.g. a required field left empty
            this.disabled = true;
            this.textContent = this.dataset.busy;
            form.dataset.confirmed = '1';
            form.submit();
        });
        wrap.addEventListener('click', function (e) { if (e.target === wrap) close(); });
        document.addEventListener('keydown', function (e) {
            if (wrap.style.display !== 'none' && e.key === 'Escape') close();
        });
        return wrap;
    }

    function open(form, opts) {
        dialog = dialog || build();
        pendingForm = form;
        lastFocus = document.activeElement;
        dialog.querySelector('[data-icon]').innerHTML = ICONS[opts.icon];
        dialog.querySelector('#logout-confirm-title').textContent = opts.title;
        dialog.querySelector('[data-message]').textContent = opts.message;
        var ok = dialog.querySelector('[data-ok]');
        ok.disabled = false;
        ok.textContent = opts.ok;
        ok.dataset.busy = opts.busy;
        dialog.style.display = 'flex';
        dialog.querySelector('[data-cancel]').focus();
    }

    function close() {
        if (!dialog) return;
        dialog.style.display = 'none';
        pendingForm = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    // Capture phase, so it runs before any other submit handler on the page.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirmed) return;

        var opts = null;
        if (form.hasAttribute('data-confirm')) {
            opts = { icon: 'alert', title: form.dataset.confirmTitle || 'Are you sure?', message: form.dataset.confirm,
                     ok: form.dataset.confirmOk || 'Yes, continue', busy: 'Sending…' };
        } else if (isLogout(form)) {
            opts = { icon: 'logout', title: 'Sign out?', message: 'Are you sure you want to sign out of your account?',
                     ok: 'Sign out', busy: 'Signing out…' };
        }
        if (!opts) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        open(form, opts);
    }, true);
})();
