<x-messages-layout title="Messages" active="messages">
<style>
    .mx-shell { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; overflow: hidden; height: calc(100vh - 210px); min-height: 480px; }
    .mx-list-head { padding: .75rem .9rem; border-bottom: 1px solid #F2EEEA; }
    .mx-search { width: 100%; border: 1px solid #E7E5E4; border-radius: .375rem; padding: .45rem .7rem .45rem 2rem; font-size: .8125rem; color: #222; outline: none; }
    .mx-search:focus { border-color: #fa4e1c; }
    .mx-group { padding: .7rem .9rem .3rem; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; }
    .mx-item { display: flex; width: 100%; gap: .65rem; padding: .65rem .9rem; text-align: left; border-left: 2px solid transparent; transition: background .12s; }
    .mx-item:hover { background: #FAFAF9; }
    .mx-item.is-active { background: #FFF7F4; border-left-color: #fa4e1c; }
    .mx-avatar { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 9999px; background: #F5F2EF; color: #57534E; font-size: .8125rem; font-weight: 600; }
    .mx-unread { flex-shrink: 0; min-width: 1.15rem; height: 1.15rem; padding: 0 .3rem; border-radius: 9999px; background: #fa4e1c; color: #fff; font-size: .65rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    .mx-day { display: flex; align-items: center; gap: .75rem; margin: 1rem 0 .5rem; font-size: .7rem; font-weight: 500; color: #a39890; }
    .mx-day::before, .mx-day::after { content: ''; flex: 1; height: 1px; background: #F2EEEA; }
    .mx-bubble { max-width: min(75%, 34rem); padding: .5rem .8rem; border-radius: .9rem; font-size: .875rem; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; }
    .mx-bubble.mine { background: #fa4e1c; color: #fff; border-bottom-right-radius: .3rem; }
    .mx-bubble.theirs { background: #F5F2EF; color: #222; border-bottom-left-radius: .3rem; }
    .mx-time { margin-top: .2rem; font-size: .68rem; color: #b3a79f; }
    .mx-composer { border-top: 1px solid #F2EEEA; padding: .7rem .9rem; }
    .mx-input { flex: 1; resize: none; max-height: 8rem; border: 1px solid #E7E5E4; border-radius: .5rem; padding: .55rem .75rem; font-size: .875rem; line-height: 1.4; color: #222; outline: none; }
    .mx-input:focus { border-color: #fa4e1c; }
    .mx-send { flex-shrink: 0; border-radius: .5rem; padding: .55rem 1rem; font-size: .875rem; font-weight: 600; color: #fff; background: #fa4e1c; transition: opacity .15s; }
    .mx-send:disabled { opacity: .45; cursor: not-allowed; }
    @media (max-width: 767px) {
        .mx-shell.has-thread .mx-list { display: none; }
        .mx-shell:not(.has-thread) .mx-thread { display: none; }
    }
</style>

<div class="mx-auto max-w-6xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
        <div>
            {{-- The admin layout already shows the page title in its top bar. --}}
            @unless (auth()->user()?->isAdmin())
                <h1 class="font-display text-2xl font-bold" style="color:#222;">Messages</h1>
            @endunless
            <p class="mt-0.5 text-sm" style="color:#8a7f78;">
                @if ($unreadCount > 0)
                    {{ $unreadCount }} unread {{ \Illuminate\Support\Str::plural('message', $unreadCount) }}
                @else
                    You're all caught up.
                @endif
            </p>
        </div>
    </div>

    <div class="mx-shell flex" id="mx-shell">

        {{-- Conversation list --}}
        <aside class="mx-list flex w-full flex-col border-r md:w-80 md:shrink-0" style="border-color:#F2EEEA;">
            <div class="mx-list-head">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input id="chat-search" type="search" placeholder="Search" class="mx-search" aria-label="Search conversations">
                </div>
            </div>
            <div id="chat-thread-list" class="flex-1 overflow-y-auto">
                <p class="p-6 text-center text-sm" style="color:#a39890;">Loading…</p>
            </div>
        </aside>

        {{-- Conversation --}}
        <section class="mx-thread flex min-w-0 flex-1 flex-col">
            <header class="flex items-center gap-3 border-b px-4 py-3" style="border-color:#F2EEEA;">
                <button type="button" id="chat-back" class="text-sm md:hidden" style="color:#57534E;" aria-label="Back to conversations">&larr;</button>
                <span id="chat-thread-avatar" class="mx-avatar hidden"></span>
                <div class="min-w-0">
                    <p id="chat-thread-title" class="truncate text-sm font-semibold" style="color:#222;">Select a conversation</p>
                    <p id="chat-thread-sub" class="truncate text-xs" style="color:#8a7f78;"></p>
                </div>
            </header>

            <div id="chat-messages" class="flex-1 overflow-y-auto px-4 py-3">
                <div class="flex h-full items-center justify-center text-center">
                    <p class="text-sm" style="color:#a39890;">Pick a conversation on the left to read and reply.</p>
                </div>
            </div>

            <form id="chat-send-form" class="mx-composer hidden items-end gap-2">
                <textarea id="chat-input" rows="1" maxlength="1000" placeholder="Write a message" class="mx-input" aria-label="Message"></textarea>
                <button type="submit" id="chat-send" class="mx-send" disabled>Send</button>
            </form>
            <p id="chat-hint" class="hidden px-4 pb-2 text-[11px]" style="color:#b3a79f;">Enter to send · Shift + Enter for a new line</p>
        </section>
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
