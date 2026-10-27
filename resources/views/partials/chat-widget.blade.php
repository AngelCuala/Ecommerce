@auth
{{-- ═══════════════════════════════ FLOATING CHAT WIDGET ═══════════════════════════════ --}}
<div id="chat-widget" style="position:fixed;bottom:20px;right:20px;z-index:60;font-family:'Inter',sans-serif;">

    {{-- Launcher button --}}
    <style>
        [data-chat-drag] { cursor: grab; touch-action: none; user-select: none; }
        #chat-widget.chat-dragging, #chat-widget.chat-dragging [data-chat-drag] { cursor: grabbing; }
        #chat-widget.chat-dragging #chat-panel { box-shadow: 0 30px 70px -20px rgba(0,43,77,.55); }
        .chat-grip { display: inline-flex; align-items: center; justify-content: center; height: 1.4rem; width: 1rem; color: #c3d0dc; }
    </style>
    <button id="chat-launcher" type="button" data-chat-drag title="Chat (drag to move)"
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
                <div class="flex items-center justify-between px-4 py-3 border-b" style="border-color:#eef2f6;" data-chat-drag title="Drag to move · double-click to put it back">
                    <span class="flex items-center gap-1.5">
                        <span class="chat-grip" aria-hidden="true"><svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor"><circle cx="2.5" cy="3" r="1.4"/><circle cx="7.5" cy="3" r="1.4"/><circle cx="2.5" cy="8" r="1.4"/><circle cx="7.5" cy="8" r="1.4"/><circle cx="2.5" cy="13" r="1.4"/><circle cx="7.5" cy="13" r="1.4"/></svg></span>
                        <span class="font-display font-bold text-base" style="color:#fa4e1c;">Chat</span>
                    </span>
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
                <div class="flex items-center gap-2 px-4 py-3 border-b" style="border-color:#eef2f6;" data-chat-drag>
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

    function open()  { panel.classList.remove('hidden'); reposition(); loadThreads(); }
    function close() { panel.classList.add('hidden'); clamp(); }

    launcher.addEventListener('click', function (e) {
        if (justDragged) { e.preventDefault(); return; }   // the end of a drag isn't a click
        panel.classList.contains('hidden') ? open() : close();
    });
    closeBtn.addEventListener('click', close);

    // ── Movable: drag the Chat button, or the window by its header. The spot is remembered. ──
    var widget = document.getElementById('chat-widget'), KEY = 'alvy-chat-pos', GAP = 8, justDragged = false;

    // The window opens toward the side with room (up or down, left or right of the button).
    function place() {
        if (panel.classList.contains('hidden')) return;
        var b = launcher.getBoundingClientRect();
        panel.style.left = panel.style.right = panel.style.top = panel.style.bottom = '';
        var w = panel.offsetWidth, h = panel.offsetHeight;
        if (b.right - w < GAP && b.left + w <= window.innerWidth - GAP) { panel.style.left = '0'; } else { panel.style.right = '0'; }
        if (b.bottom - h < GAP && b.top + h <= window.innerHeight - GAP) { panel.style.top = '0'; } else { panel.style.bottom = '0'; }
    }
    // Open toward the roomier side, then nudge the whole chat back on screen if it still doesn't fit.
    function reposition() { place(); clamp(); }
    // Keep what's visible (the window when open, else the button) on screen.
    function clamp() {
        if (!widget.style.left) return;   // still in its default corner
        var box = (panel.classList.contains('hidden') ? launcher : panel).getBoundingClientRect();
        var x = parseFloat(widget.style.left), y = parseFloat(widget.style.top);
        x += Math.max(0, GAP - box.left) - Math.max(0, box.right - (window.innerWidth - GAP));
        y += Math.max(0, GAP - box.top) - Math.max(0, box.bottom - (window.innerHeight - GAP));
        setPos(x, y);
    }
    function setPos(x, y) {
        widget.style.left = x + 'px'; widget.style.top = y + 'px';
        widget.style.right = widget.style.bottom = 'auto';
    }
    function save() { try { localStorage.setItem(KEY, JSON.stringify({ x: parseFloat(widget.style.left), y: parseFloat(widget.style.top) })); } catch (e) {} }
    function resetPos() {
        widget.style.left = widget.style.top = ''; widget.style.right = '20px'; widget.style.bottom = '20px';
        try { localStorage.removeItem(KEY); } catch (e) {}
        place();
    }
    try {
        var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (saved && isFinite(saved.x) && isFinite(saved.y)) { setPos(saved.x, saved.y); clamp(); }
    } catch (e) {}

    widget.querySelectorAll('[data-chat-drag]').forEach(function (handle) {
        handle.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            if (handle !== launcher && e.target.closest('button, input, a')) return;   // the close button etc. still work
            var start = widget.getBoundingClientRect(), sx = e.clientX, sy = e.clientY, moved = false;
            function move(ev) {
                var dx = ev.clientX - sx, dy = ev.clientY - sy;
                if (!moved && Math.abs(dx) + Math.abs(dy) < 6) return;   // small wobble = still a click
                moved = true;
                widget.classList.add('chat-dragging');
                setPos(start.left + dx, start.top + dy);
                clamp();
            }
            function up() {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
                widget.classList.remove('chat-dragging');
                if (moved) {
                    reposition(); save();
                    justDragged = true; setTimeout(function () { justDragged = false; }, 0);
                }
            }
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
        });
        if (handle !== launcher) handle.addEventListener('dblclick', function (e) { if (!e.target.closest('button, input, a')) resetPos(); });
    });
    window.addEventListener('resize', reposition);

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
        fetch('{{ \App\Support\Portal::route('chat.threads') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

        fetch('{{ \App\Support\Portal::url('chat/thread') }}/' + current.type + '/' + encodeURIComponent(current.id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

        fetch('{{ \App\Support\Portal::url('chat/send') }}/' + current.type + '/' + encodeURIComponent(current.id), {
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

    // Allow other page elements (e.g. an order's "Chat" button) to open the
    // widget on a specific conversation, e.g. openChatThread('order:12').
    window.openChatThread = function (key) {
        panel.classList.remove('hidden');
        reposition();
        loadThreads();          // refresh the thread list in the background
        openThread(key);        // open the requested conversation right away
    };
})();
</script>
@endauth
