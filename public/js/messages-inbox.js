// Full-page messages inbox — two-pane chat.
// Endpoint URLs come from window.CHAT_ROUTES (set inline in the view).
(function () {
    var ROUTES = window.CHAT_ROUTES || {};
    var listEl   = document.getElementById('chat-thread-list');
    var msgsEl   = document.getElementById('chat-messages');
    var titleEl  = document.getElementById('chat-thread-title');
    var form     = document.getElementById('chat-send-form');
    var input    = document.getElementById('chat-input');
    var searchEl = document.getElementById('chat-search');

    var CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var current = null;      // {type, id}
    var allThreads = [];

    function renderThreads(threads) {
        if (!threads.length) {
            listEl.innerHTML = '<p class="p-4 text-center text-xs" style="color:#9db3c4;">No conversations yet.</p>';
            return;
        }
        listEl.innerHTML = threads.map(function (t) {
            var active = current && (t.key === current.type + ':' + current.id);
            var unread = t.unread > 0
                ? '<span style="margin-left:auto;background:#fa4e1c;color:#fff;border-radius:999px;min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;padding:0 5px;">' + t.unread + '</span>'
                : '';
            var sub = t.last_body
                ? '<span style="display:block;font-size:12px;color:#9db3c4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(t.last_body) + '</span>'
                : '<span style="display:inline-block;margin-top:2px;font-size:10px;font-weight:600;color:#fa4e1c;background:#fff1ee;border-radius:999px;padding:1px 8px;">' + escapeHtml(t.position || 'User') + '</span>';
            return '<button data-key="' + t.key + '" class="chat-thread-item" style="display:flex;align-items:flex-start;gap:10px;width:100%;text-align:left;padding:12px 14px;border-bottom:1px solid #f2f5f8;transition:background .15s;background:' + (active ? '#f6f9fc' : '') + ';">' +
                '<span style="flex-shrink:0;width:38px;height:38px;border-radius:50%;background:#002b4d;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">' + (t.label.charAt(0).toUpperCase()) + '</span>' +
                '<span style="flex:1;min-width:0;">' +
                    '<span style="display:flex;align-items:center;gap:6px;"><span style="font-weight:600;font-size:13px;color:#002b4d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escapeHtml(t.label) + '</span>' + unread + '</span>' +
                    sub +
                '</span>' +
            '</button>';
        }).join('');

        listEl.querySelectorAll('.chat-thread-item').forEach(function (btn) {
            var key = btn.getAttribute('data-key');
            var isActive = current && (key === current.type + ':' + current.id);
            btn.addEventListener('mouseover', function () { if (!isActive) this.style.background = '#f6f9fc'; });
            btn.addEventListener('mouseout',  function () { if (!isActive) this.style.background = ''; });
            btn.addEventListener('click', function () { openThread(key); });
        });
    }

    function loadThreads(done) {
        fetch(ROUTES.threads, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                allThreads = data.threads;
                var q = (searchEl.value || '').toLowerCase();
                renderThreads(q ? allThreads.filter(function (t) { return t.label.toLowerCase().includes(q); }) : allThreads);
                if (typeof done === 'function') done();
            });
    }

    function openThread(key) {
        var parts = key.split(':');
        current = { type: parts[0], id: parts.slice(1).join(':') };
        form.classList.remove('hidden');
        form.classList.add('flex');
        msgsEl.innerHTML = '<p class="pt-20 text-center text-sm" style="color:#9db3c4;">Loading…</p>';
        renderThreads(allThreads); // refresh active highlight

        fetch(ROUTES.thread + '/' + current.type + '/' + encodeURIComponent(current.id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                titleEl.textContent = data.title;
                renderMessages(data.messages);
            });
    }

    function renderMessages(messages) {
        if (!messages.length) {
            msgsEl.innerHTML = '<p class="pt-20 text-center text-sm" style="color:#9db3c4;">No messages yet. Say hello!</p>';
            return;
        }
        msgsEl.innerHTML = messages.map(function (m) {
            if (m.mine) {
                return '<div style="display:flex;justify-content:flex-end;"><div style="max-width:70%;"><div style="background:#fa4e1c;color:#fff;border-radius:14px 14px 4px 14px;padding:9px 13px;font-size:13px;line-height:1.4;">' + escapeHtml(m.body) + '</div><p style="text-align:right;font-size:10px;color:#c2d1dc;margin-top:2px;">' + m.at + '</p></div></div>';
            }
            return '<div style="display:flex;justify-content:flex-start;"><div style="max-width:70%;"><div style="background:#eef2f6;color:#002b4d;border-radius:14px 14px 14px 4px;padding:9px 13px;font-size:13px;line-height:1.4;">' + escapeHtml(m.body) + '</div><p style="font-size:10px;color:#c2d1dc;margin-top:2px;">' + m.name + ' · ' + m.at + '</p></div></div>';
        }).join('');
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function escapeHtml(s) {
        var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!current || !input.value.trim()) return;
        var body = input.value.trim();
        input.value = '';

        fetch(ROUTES.send + '/' + current.type + '/' + encodeURIComponent(current.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ body: body })
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            openThread(current.type + ':' + current.id);
            loadThreads();
        });
    });

    searchEl.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        renderThreads(allThreads.filter(function (t) { return t.label.toLowerCase().includes(q); }));
    });

    // If opened with ?open=<type>:<id> (e.g. from an order's Chat button),
    // auto-open that conversation once the thread list has loaded.
    function autoOpenFromUrl() {
        var key = new URLSearchParams(window.location.search).get('open');
        if (key) openThread(key);
    }

    // Initial load + light polling to keep the list fresh
    loadThreads(autoOpenFromUrl);
    setInterval(loadThreads, 15000);
})();
