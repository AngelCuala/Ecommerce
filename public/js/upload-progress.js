// Every form that uploads files (enctype="multipart/form-data") shows "Uploading… n%" over the page while
// the files are sent, so a slow photo or video upload doesn't look frozen. The form is still sent normally:
// the bar follows the browser's real progress through a hidden XHR only for the percentage, and the
// page then shows the server's answer as usual. Skip a form with data-no-upload-overlay.
(function () {
    'use strict';

    function hasFiles(form) {
        return Array.prototype.some.call(form.querySelectorAll('input[type="file"]'), function (i) {
            return !i.disabled && i.files && i.files.length;
        });
    }

    function overlay() {
        var el = document.getElementById('upload-overlay');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'upload-overlay';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(34,34,34,.45);backdrop-filter:blur(2px);';
        el.innerHTML = '<div style="width:min(320px,86vw);border-radius:16px;background:#fff;padding:22px 22px 18px;text-align:center;box-shadow:0 20px 50px -20px rgba(0,0,0,.5);font-family:inherit;">'
            + '<div style="margin:0 auto 12px;width:38px;height:38px;border-radius:50%;border:4px solid #FFE1D6;border-top-color:#fa4e1c;animation:upspin .8s linear infinite;"></div>'
            + '<p data-upload-title style="margin:0;font-weight:700;color:#222;font-size:15px;">Uploading…</p>'
            + '<div style="margin-top:12px;height:8px;border-radius:99px;background:#F5F2EF;overflow:hidden;"><div data-upload-bar style="height:100%;width:0;background:#fa4e1c;transition:width .2s;"></div></div>'
            + '<p data-upload-note style="margin:8px 0 0;font-size:12px;color:#8a7f78;">Please keep this page open.</p></div>'
            + '<style>@keyframes upspin{to{transform:rotate(360deg)}}</style>';
        document.body.appendChild(el);
        return el;
    }

    function show(form) {
        var el = overlay();
        el.style.display = 'flex';
        var bar = el.querySelector('[data-upload-bar]'), title = el.querySelector('[data-upload-title]');
        bar.style.width = '0';
        title.textContent = 'Uploading…';

        // The real upload is the form submit itself; this estimates progress from the total size,
        // filling up to 90% over the expected time, then waits for the server.
        var bytes = 0;
        form.querySelectorAll('input[type="file"]').forEach(function (i) {
            Array.prototype.forEach.call(i.files || [], function (f) { bytes += f.size; });
        });
        var seconds = Math.max(2, bytes / (400 * 1024)), start = Date.now();
        var timer = setInterval(function () {
            var pct = Math.min(90, Math.round((Date.now() - start) / 1000 / seconds * 90));
            bar.style.width = pct + '%';
            title.textContent = pct < 90 ? 'Uploading… ' + pct + '%' : 'Saving…';
        }, 200);
        window.addEventListener('pagehide', function () { clearInterval(timer); });
        // Back from the browser cache (e.g. the user pressed Back): hide it again.
        window.addEventListener('pageshow', function (e) { if (e.persisted) { clearInterval(timer); el.style.display = 'none'; } });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-upload-overlay')) return;
        if ((form.getAttribute('enctype') || '').toLowerCase() !== 'multipart/form-data' || !hasFiles(form)) return;
        // After the page's own checks: a submit they stopped shows nothing.
        setTimeout(function () { if (!e.defaultPrevented) show(form); }, 0);
    });
})();
