@auth
{{-- ═══════════════════════════════ FLOATING CHAT WIDGET ═══════════════════════════════ --}}
<div id="chat-widget" style="position:fixed;bottom:20px;right:20px;z-index:60;font-family:'Inter',sans-serif;">

    {{-- Launcher button --}}
    <button id="chat-launcher" type="button"
            class="flex items-center gap-2 rounded-lg px-4 py-3 text-sm font-bold text-white shadow-lg transition"
            style="background:#fa4e1c;"
            onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
        Chat
        <span id="chat-launcher-badge" class="hidden ml-0.5 flex h-5 min-w-[20px] items-center justify-center rounded-full px-1 text-[11px] font-bold"
              style="background:#fff;color:#fa4e1c;"></span>
    </button>

    {{-- Panel --}}
    <div id="chat-panel" class="hidden overflow-hidden rounded-xl shadow-2xl"
         style="position:absolute;bottom:0;right:0;width:680px;max-width:calc(100vw - 40px);height:520px;max-height:calc(100vh - 100px);background:#fff;border:1px solid #e5eaf0;">
        <div class="flex h-full">

            {{-- Left: thread list --}}
            <div class="flex w-[260px] shrink-0 flex-col border-r" style="border-color:#eef2f6;">
                <div class="flex items-center justify-between px-4 py-3 border-b" style="border-color:#eef2f6;">
                    <span class="font-display font-bold text-base" style="color:#fa4e1c;">Chat</span>
                    <button id="chat-close" class="text-xl leading-none" style="color:#9db3c4;" aria-label="Close">&times;</button>
                </div>
                <div class="px-3 py-2">
                    <input id="chat-search" type="text" placeholder="Search name"
                           class="w-full rounded-lg border px-3 py-1.5 text-xs outline-none"
                           style="border-color:#eef2f6;color:#002b4d;">
                </div>
                <div id="chat-thread-list" class="flex-1 overflow-y-auto">
                    <p class="p-4 text-center text-xs" style="color:#9db3c4;">Loading…</p>
                </div>
            </div>

            {{-- Right: conversation --}}
            <div class="flex flex-1 flex-col min-w-0">
                <div class="flex items-center gap-2 px-4 py-3 border-b" style="border-color:#eef2f6;">
                    <p id="chat-thread-title" class="font-semibold text-sm truncate" style="color:#002b4d;">Select a conversation</p>
                </div>
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3" style="background:#fafbfc;">
                    <p class="pt-16 text-center text-xs" style="color:#9db3c4;">Choose a chat on the left to start messaging.</p>
                </div>
                <form id="chat-send-form" class="hidden items-center gap-2 border-t px-3 py-3" style="border-color:#eef2f6;">
                    <input id="chat-input" type="text" placeholder="Type a message here" autocomplete="off"
                           class="flex-1 rounded-lg border px-3 py-2 text-sm outline-none"
                           style="border-color:#eef2f6;color:#002b4d;">
                    <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-lg text-white" style="background:#fa4e1c;" aria-label="Send">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var launcher = document.getElementById('chat-launcher');
    var panel    = document.getElementById('chat-panel');
    var closeBtn = document.getElementById('chat-close');
    var listEl   = document.getElementById('chat-thread-list');
    var msgsEl   = document.getElementById('chat-messages');
    var titleEl  = document.getElementById('chat-thread-title');
    var form     = document.getElementById('chat-send-form');
    var input    = document.getElementById('chat-input');
    var searchEl = document.getElementById('chat-search');
    var badge    = document.getElementById('chat-launcher-badge');

    var CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var current = null;      // {type, id}
    var allThreads = [];

    function open()  { panel.classList.remove('hidden'); loadThreads(); }
    function close() { panel.classList.add('hidden'); }

    launcher.addEventListener('click', function () {
        panel.classList.contains('hidden') ? open() : close();
    });
    closeBtn.addEventListener('click', close);

    function renderThreads(threads) {
        if (!threads.length) {
            listEl.innerHTML = '<p class="p-4 text-center text-xs" style="color:#9db3c4;">No conversations yet.</p>';
            return;
        }
        listEl.innerHTML = threads.map(function (t) {
            var unread = t.unread > 0
                ? '<span style="margin-left:auto;background:#fa4e1c;color:#fff;border-radius:999px;min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;padding:0 5px;">' + t.unread + '</span>'
                : '';
            return '<button data-key="' + t.key + '" class="chat-thread-item" style="display:flex;align-items:flex-start;gap:10px;width:100%;text-align:left;padding:12px 14px;border-bottom:1px solid #f2f5f8;transition:background .15s;">' +
                '<span style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:#002b4d;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">' + (t.label.charAt(0).toUpperCase()) + '</span>' +
                '<span style="flex:1;min-width:0;">' +
                    '<span style="display:flex;align-items:center;gap:6px;"><span style="font-weight:600;font-size:13px;color:#002b4d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + t.label + '</span>' + unread + '</span>' +
                    '<span style="display:block;font-size:12px;color:#9db3c4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (t.last_body || '') + '</span>' +
                '</span>' +
            '</button>';
        }).join('');

        listEl.querySelectorAll('.chat-thread-item').forEach(function (btn) {
            btn.addEventListener('mouseover', function () { this.style.background = '#f6f9fc'; });
            btn.addEventListener('mouseout',  function () { this.style.background = ''; });
            btn.addEventListener('click', function () { openThread(this.getAttribute('data-key')); });
        });
    }

    function loadThreads() {
        fetch('{{ route('chat.threads') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                allThreads = data.threads;
                renderThreads(allThreads);
                if (data.unread > 0) { badge.textContent = data.unread; badge.classList.remove('hidden'); }
                else { badge.classList.add('hidden'); }
            });
    }

    function openThread(key) {
        var parts = key.split(':');
        current = { type: parts[0], id: parts.slice(1).join(':') };
        form.classList.remove('hidden');
        form.classList.add('flex');
        msgsEl.innerHTML = '<p class="pt-16 text-center text-xs" style="color:#9db3c4;">Loading…</p>';

        fetch('{{ url('chat/thread') }}/' + current.type + '/' + encodeURIComponent(current.id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                titleEl.textContent = data.title;
                renderMessages(data.messages);
            });
    }

    function renderMessages(messages) {
        if (!messages.length) {
            msgsEl.innerHTML = '<p class="pt-16 text-center text-xs" style="color:#9db3c4;">No messages yet. Say hello!</p>';
            return;
        }
        msgsEl.innerHTML = messages.map(function (m) {
            if (m.mine) {
                return '<div style="display:flex;justify-content:flex-end;"><div style="max-width:75%;"><div style="background:#fa4e1c;color:#fff;border-radius:14px 14px 4px 14px;padding:8px 12px;font-size:13px;line-height:1.4;">' + escapeHtml(m.body) + '</div><p style="text-align:right;font-size:10px;color:#c2d1dc;margin-top:2px;">' + m.at + '</p></div></div>';
            }
            return '<div style="display:flex;justify-content:flex-start;"><div style="max-width:75%;"><div style="background:#eef2f6;color:#002b4d;border-radius:14px 14px 14px 4px;padding:8px 12px;font-size:13px;line-height:1.4;">' + escapeHtml(m.body) + '</div><p style="font-size:10px;color:#c2d1dc;margin-top:2px;">' + m.name + ' · ' + m.at + '</p></div></div>';
        }).join('');
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function escapeHtml(s) {
        var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!current || !input.value.trim()) return;
        var body = input.value.trim();
        input.value = '';

        fetch('{{ url('chat/send') }}/' + current.type + '/' + encodeURIComponent(current.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ body: body })
        })
        .then(function (r) { return r.json(); })
        .then(function () {
            // reload the thread and thread list
            openThread(current.type + ':' + current.id);
            loadThreads();
        });
    });

    searchEl.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        renderThreads(allThreads.filter(function (t) { return t.label.toLowerCase().includes(q); }));
    });
})();
</script>
@endauth
