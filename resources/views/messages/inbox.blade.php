<x-messages-layout title="Messages" active="messages">
<div class="mx-auto max-w-6xl">

    {{-- Header --}}
    <div class="mb-5">
        <h1 class="font-display text-2xl font-bold" style="color:#222222;">Messages</h1>
        @if ($unreadCount > 0)
            <p class="text-sm mt-0.5" style="color:#fa4e1c;">{{ $unreadCount }} unread message{{ $unreadCount !== 1 ? 's' : '' }}</p>
        @endif
    </div>

    {{-- ═══════════ TWO-PANE CHAT ═══════════ --}}
    <div class="overflow-hidden rounded-2xl border bg-white"
         style="border-color:#e5eaf0;height:calc(100vh - 220px);min-height:480px;">
        <div class="flex h-full">

            {{-- Left: conversation list --}}
            <div class="flex w-full max-w-[300px] shrink-0 flex-col border-r sm:w-[300px]" style="border-color:#eef2f6;">
                <div class="flex items-center justify-between px-4 py-3.5 border-b" style="border-color:#eef2f6;">
                    <span class="font-display font-bold text-base" style="color:#fa4e1c;">Chat</span>
                </div>
                <div class="px-3 py-2.5">
                    <input id="chat-search" type="text" placeholder="Search name"
                           class="w-full rounded-lg border px-3 py-2 text-xs outline-none"
                           style="border-color:#eef2f6;color:#002b4d;"
                           onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#eef2f6';">
                </div>
                <div id="chat-thread-list" class="flex-1 overflow-y-auto">
                    <p class="p-4 text-center text-xs" style="color:#9db3c4;">Loading…</p>
                </div>
            </div>

            {{-- Right: active conversation --}}
            <div class="flex flex-1 flex-col min-w-0">
                <div class="flex items-center gap-2 px-5 py-3.5 border-b" style="border-color:#eef2f6;">
                    <p id="chat-thread-title" class="font-semibold text-sm truncate" style="color:#002b4d;">Select a conversation</p>
                </div>
                <div id="chat-messages" class="flex-1 overflow-y-auto p-5 space-y-3" style="background:#fafbfc;">
                    <p class="pt-20 text-center text-sm" style="color:#9db3c4;">Choose a chat on the left to start messaging.</p>
                </div>
                <form id="chat-send-form" class="hidden items-center gap-2 border-t px-4 py-3" style="border-color:#eef2f6;">
                    <input id="chat-input" type="text" placeholder="Type a message here" autocomplete="off"
                           class="flex-1 rounded-lg border px-3.5 py-2.5 text-sm outline-none"
                           style="border-color:#eef2f6;color:#002b4d;"
                           onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#eef2f6';">
                    <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-lg text-white transition hover:opacity-90" style="background:#fa4e1c;" aria-label="Send">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.CHAT_ROUTES = {
        threads: '{{ route('chat.threads') }}',
        thread:  '{{ url('chat/thread') }}',
        send:    '{{ url('chat/send') }}',
    };
</script>
<script src="{{ asset('js/messages-inbox.js') }}"></script>
</x-messages-layout>
