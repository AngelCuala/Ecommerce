// Full-page messages inbox — conversation list + open conversation.
// Endpoint URLs come from window.CHAT_ROUTES (set inline in the view).
(function () {
    var ROUTES   = window.CHAT_ROUTES || {};
    var shell    = document.getElementById('mx-shell');
    var listEl   = document.getElementById('chat-thread-list');
    var msgsEl   = document.getElementById('chat-messages');
    var titleEl  = document.getElementById('chat-thread-title');
    var subEl    = document.getElementById('chat-thread-sub');
    var avatarEl = document.getElementById('chat-thread-avatar');
    var form     = document.getElementById('chat-send-form');
    var input    = document.getElementById('chat-input');
    var sendBtn  = document.getElementById('chat-send');
    var hintEl   = document.getElementById('chat-hint');
    var searchEl = document.getElementById('chat-search');
    var backBtn  = document.getElementById('chat-back');

    var CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var current = null;      // {type, id, key}
    var allThreads = [];
    var sending = false;

    function esc(s) {
        var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML;
    }
    function initial(s) { return (s || '?').trim().charAt(0).toUpperCase(); }

    // ── Conversation list ─────────────────────────────────────
    function threadButton(t) {
        var active = current && t.key === current.key;
        var meta = t.last_at ? '<span class="shrink-0 text-[11px]" style="color:#b3a79f;">' + esc(t.last_at) + '</span>' : '';
        var preview = t.last_body
            ? esc(t.last_body)
            : '<span style="color:#b3a79f;">' + esc(t.position || 'Start a conversation') + '</span>';
        var unread = t.unread > 0 ? '<span class="mx-unread">' + (t.unread > 9 ? '9+' : t.unread) + '</span>' : '';
        var nameWeight = t.unread > 0 ? '600' : '500';

        return '<button type="button" data-key="' + esc(t.key) + '" class="mx-item' + (active ? ' is-active' : '') + '">' +
            '<span class="mx-avatar">' + esc(t.type === 'order' ? '#' : initial(t.label)) + '</span>' +
            '<span class="min-w-0 flex-1">' +
                '<span class="flex items-baseline justify-between gap-2">' +
                    '<span class="truncate text-sm" style="color:#222;font-weight:' + nameWeight + ';">' + esc(t.label) + '</span>' + meta +
                '</span>' +
                '<span class="mt-0.5 flex items-center gap-2">' +
                    '<span class="flex-1 truncate text-xs" style="color:#8a7f78;">' + preview + '</span>' + unread +
                '</span>' +
            '</span>' +
        '</button>';
    }

    function renderThreads(threads) {
        if (!threads.length) {
            listEl.innerHTML = '<p class="p-6 text-center text-sm" style="color:#a39890;">' +
                (searchEl.value ? 'No matches.' : 'No conversations yet.') + '</p>';
            return;
        }
        var convos   = threads.filter(function (t) { return t.last_body; });
        var contacts = threads.filter(function (t) { return !t.last_body; });
        var html = '';
        if (convos.length)   html += (contacts.length ? '<p class="mx-group">Conversations</p>' : '') + convos.map(threadButton).join('');
        if (contacts.length) html += '<p class="mx-group">Contacts</p>' + contacts.map(threadButton).join('');
        listEl.innerHTML = html;

        listEl.querySelectorAll('.mx-item').forEach(function (btn) {
            btn.addEventListener('click', function () { openThread(btn.getAttribute('data-key')); });
        });
    }

    function filtered() {
        var q = (searchEl.value || '').trim().toLowerCase();
        return q ? allThreads.filter(function (t) { return t.label.toLowerCase().includes(q); }) : allThreads;
    }

    function loadThreads(done) {
        fetch(ROUTES.threads, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                allThreads = data.threads || [];
                renderThreads(filtered());
                if (typeof done === 'function') done();
            })
            .catch(function () {
                listEl.innerHTML = '<p class="p-6 text-center text-sm" style="color:#B91C1C;">Couldn\'t load conversations. Refresh to try again.</p>';
            });
    }

    // ── Open conversation ─────────────────────────────────────
    function openThread(key, silent) {
        var parts = key.split(':');
        current = { type: parts[0], id: parts.slice(1).join(':'), key: key };
        var t = allThreads.find(function (x) { return x.key === key; }) || {};

        shell.classList.add('has-thread');
        form.classList.remove('hidden'); form.classList.add('flex');
        hintEl.classList.remove('hidden');
        avatarEl.classList.remove('hidden');
        avatarEl.textContent = current.type === 'order' ? '#' : initial(t.label);
        titleEl.textContent = t.label || 'Conversation';
        subEl.textContent = t.position || '';
        if (!silent) msgsEl.innerHTML = '<p class="pt-16 text-center text-sm" style="color:#a39890;">Loading…</p>';
        renderThreads(filtered());

        return fetch(ROUTES.thread + '/' + current.type + '/' + encodeURIComponent(current.id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                titleEl.textContent = data.title;
                renderMessages(data.messages || [], silent);
                if (t.unread) { t.unread = 0; renderThreads(filtered()); }
                if (!silent) input.focus();
            });
    }

    function renderMessages(messages, keepScroll) {
        // On background refreshes, don't yank the reader down if they scrolled up.
        var nearBottom = msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight < 80;
        var prevTop = msgsEl.scrollTop;
        if (!messages.length) {
            msgsEl.innerHTML = '<div class="flex h-full items-center justify-center"><p class="text-sm" style="color:#a39890;">No messages yet. Say hello.</p></div>';
            return;
        }
        var html = '', lastDay = null;
        messages.forEach(function (m) {
            var day = m.day || '';
            if (day && day !== lastDay) { html += '<div class="mx-day">' + esc(day) + '</div>'; lastDay = day; }
            var time = m.time || m.at;
            if (m.mine) {
                html += '<div class="mb-2 flex flex-col items-end"><div class="mx-bubble mine">' + esc(m.body) + '</div>' +
                        '<span class="mx-time">' + esc(time) + '</span></div>';
            } else {
                html += '<div class="mb-2 flex flex-col items-start"><div class="mx-bubble theirs">' + esc(m.body) + '</div>' +
                        '<span class="mx-time">' + esc(m.name) + ' · ' + esc(time) + '</span></div>';
            }
        });
        msgsEl.innerHTML = html;
        msgsEl.scrollTop = (keepScroll && !nearBottom) ? prevTop : msgsEl.scrollHeight;
    }

    // ── Composer ──────────────────────────────────────────────
    function syncComposer() {
        sendBtn.disabled = sending || !input.value.trim();
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 128) + 'px';
    }

    input.addEventListener('input', syncComposer);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!current || !body || sending) return;
        sending = true;
        syncComposer();

        fetch(ROUTES.send + '/' + current.type + '/' + encodeURIComponent(current.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ body: body })
        })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function () {
            input.value = '';
            return openThread(current.key, true);
        })
        .then(function () { loadThreads(); })
        .catch(function () { alert('Your message couldn\'t be sent. Please try again.'); })
        .finally(function () { sending = false; syncComposer(); input.focus(); });
    });

    searchEl.addEventListener('input', function () { renderThreads(filtered()); });

    backBtn.addEventListener('click', function () {
        shell.classList.remove('has-thread');
        current = null;
        renderThreads(filtered());
    });

    // ?open=<type>:<id> (e.g. from an order's Chat button) opens that conversation.
    function autoOpenFromUrl() {
        var key = new URLSearchParams(window.location.search).get('open');
        if (key) openThread(key);
    }

    loadThreads(autoOpenFromUrl);
    setInterval(function () {
        loadThreads();
        if (current && !sending && !input.value) openThread(current.key, true);
    }, 15000);
})();
