<x-messages-layout :title="'Chat with ' . $otherUser->name" active="messages">
<div class="mx-auto max-w-3xl">

    <a href="{{ route('messages.inbox') }}" class="text-sm hover:underline" style="color:#8a7f78;">&larr; All messages</a>

    <div class="mt-3 overflow-hidden rounded-xl border bg-white" style="border-color:#ECE7E3;">
        <header class="flex items-center gap-3 border-b px-4 py-3" style="border-color:#F2EEEA;">
            <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold" style="background:#F5F2EF;color:#57534E;">
                {{ strtoupper(mb_substr($otherUser->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold" style="color:#222;">{{ $otherUser->name }}</p>
                <p class="text-xs" style="color:#8a7f78;">{{ ucfirst($otherUser->role ?? 'User') }}</p>
            </div>
        </header>

        <div id="chat-box" class="overflow-y-auto px-4 py-3" style="max-height:60vh;min-height:16rem;">
            @php $lastDay = null; @endphp
            @forelse ($messages as $msg)
                @php
                    $isMine = $msg->sender_id === auth()->id();
                    $day = $msg->created_at->isToday() ? 'Today' : ($msg->created_at->isYesterday() ? 'Yesterday' : $msg->created_at->format('M j, Y'));
                @endphp
                @if ($day !== $lastDay)
                    <div class="my-3 flex items-center gap-3 text-[11px] font-medium" style="color:#a39890;">
                        <span class="h-px flex-1" style="background:#F2EEEA;"></span>{{ $day }}<span class="h-px flex-1" style="background:#F2EEEA;"></span>
                    </div>
                    @php $lastDay = $day; @endphp
                @endif
                <div class="mb-2 flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}">
                    @if ($msg->product)
                        @include('messages._product-card', ['product' => $msg->product])
                    @endif
                    @if ($msg->attachment_path)
                        <a href="{{ asset('storage/' . $msg->attachment_path) }}" target="_blank" class="mb-1">
                            <img src="{{ asset('storage/' . $msg->attachment_path) }}" alt="Photo" class="max-h-56 max-w-[16rem] rounded-xl border object-cover" style="border-color:#ECE7E3;" loading="lazy">
                        </a>
                    @endif
                    @if ($msg->body !== '')
                    <div class="max-w-[75%] whitespace-pre-wrap break-words rounded-2xl px-3.5 py-2 text-sm leading-relaxed"
                         style="{{ $isMine ? 'background:#fa4e1c;color:#fff;border-bottom-right-radius:.3rem;' : 'background:#F5F2EF;color:#222;border-bottom-left-radius:.3rem;' }}">{{ $msg->body }}</div>
                    @endif
                    <span class="mt-0.5 text-[11px]" style="color:#b3a79f;">
                        {{ $isMine ? '' : ($msg->sender->name ?? '—') . ' · ' }}{{ $msg->created_at->format('g:i A') }}
                    </span>
                </div>
            @empty
                <p class="py-16 text-center text-sm" style="color:#a39890;">No messages yet. Say hello.</p>
            @endforelse
        </div>

        @if ($aboutProduct)
            <div class="border-t px-4 pt-3" style="border-color:#F2EEEA;">
                <p class="mb-1 text-[11px]" style="color:#a39890;">You're asking about:</p>
                @include('messages._product-card', ['product' => $aboutProduct])
            </div>
        @endif
        <form action="{{ route('messages.direct.store', $threadKey) }}" method="POST" enctype="multipart/form-data" class="flex items-end gap-2 border-t px-4 py-3" style="border-color:#F2EEEA;">
            @csrf
            <label class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-lg border" style="border-color:#E7E5E4;color:#57534E;" title="Attach a photo (JPG or PNG, up to 5 MB)">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <input type="file" name="attachment" accept="image/jpeg,image/png" class="sr-only" onchange="this.closest('form').querySelector('[data-file]').textContent = this.files[0] ? this.files[0].name : ''">
            </label>
            <textarea name="body" rows="2" maxlength="1000" aria-label="Message"
                      class="flex-1 resize-none rounded-lg border px-3 py-2 text-sm focus:border-[#fa4e1c] focus:outline-none"
                      style="border-color:#E7E5E4;color:#222;" placeholder="Write a message"></textarea>
            <button type="submit" class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90" style="background:#fa4e1c;">Send</button>
        </form>
        <p data-file class="px-4 pb-1 text-[11px]" style="color:#8a7f78;"></p>
        @error('body')<p class="px-4 pb-3 text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
        @error('attachment')<p class="px-4 pb-3 text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
    </div>
</div>

<script src="{{ asset('js/chat-scroll.js') }}"></script>
</x-messages-layout>
