<x-layout :title="'Order Chat — #' . str_pad($order->id, 6, '0', STR_PAD_LEFT)">
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="mb-5 flex items-center gap-3">
        <a href="{{ route('messages.inbox') }}"
           class="text-sm font-semibold transition" style="color:#fa4e1c;"
           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
            ← Inbox
        </a>
        <span style="color:#E0E0E0;">|</span>
        <div>
            <p class="font-bold text-sm" style="color:#222222;">
                Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
            </p>
            <p class="text-xs" style="color:#6b90aa;">
                {{ $order->user->name ?? '—' }} · {{ $order->status }}
            </p>
        </div>
        <span class="ml-auto rounded-full px-3 py-1 text-[11px] font-bold"
              style="background:#fff1ee;color:#fa4e1c;">Order Chat</span>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg p-3 text-sm"
             style="background:rgba(250,78,28,.08);border:1px solid rgba(250,78,28,.25);color:#d93d0e;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Chat box --}}
    <div class="rounded-2xl border bg-white mb-4 overflow-hidden" style="border-color:#EFEFEF;">

        {{-- Participants bar --}}
        <div class="flex flex-wrap items-center gap-2 border-b px-5 py-3"
             style="background:#FAFAFA;border-color:#EFEFEF;">
            <span class="text-xs font-bold uppercase tracking-widest mr-1" style="color:#6b90aa;">In this chat:</span>
            @foreach ($participants as $pid => $pname)
                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                      style="background:#fff1ee;color:#fa4e1c;">{{ $pname }}</span>
            @endforeach
            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                  style="background:#fff1ee;color:#fa4e1c;">
                You ({{ ucfirst(auth()->user()->role ?? 'User') }})
            </span>
        </div>

        {{-- Messages --}}
        <div id="chat-box" class="space-y-4 p-5 overflow-y-auto" style="max-height:480px;">
            @forelse ($messages as $msg)
                @php $isMine = $msg->sender_id === auth()->id(); @endphp
                <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} gap-2">
                    @if (!$isMine)
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              style="background:#fa4e1c;color:#fff;">
                            {{ strtoupper(substr($msg->sender->name ?? '?', 0, 1)) }}
                        </span>
                    @endif
                    <div class="max-w-[75%]">
                        <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
                             style="{{ $isMine
                                 ? 'background:#fa4e1c;color:#fff;border-bottom-right-radius:.25rem;'
                                 : 'background:#F5F5F5;color:#222222;border-bottom-left-radius:.25rem;' }}">
                            {{ $msg->body }}
                        </div>
                        <p class="mt-1 text-[10px] {{ $isMine ? 'text-right' : '' }}" style="color:#BBBBBB;">
                            {{ $msg->sender->name ?? '—' }} · {{ $msg->created_at->format('M d, H:i') }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="py-12 text-center text-sm" style="color:#BBBBBB;">
                    No messages yet. Start the conversation!
                </p>
            @endforelse
        </div>

        {{-- Send form --}}
        @if ($participants)
            <form action="{{ route('messages.store', $order->id) }}" method="POST"
                  class="border-t px-5 py-4 space-y-3" style="border-color:#EFEFEF;">
                @csrf
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Send to</label>
                    <select name="receiver_id" class="input mt-1 text-sm" required>
                        <option value="">— Select recipient —</option>
                        @foreach ($participants as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-3">
                    <textarea name="body" rows="2" required maxlength="1000"
                              class="flex-1 rounded-xl border px-4 py-2.5 text-sm resize-none focus:outline-none"
                              style="border-color:#E0E0E0;color:#222;"
                              onfocus="this.style.borderColor='#fa4e1c';"
                              onblur="this.style.borderColor='#E0E0E0';"
                              placeholder="Type a message…"></textarea>
                    <button type="submit"
                            class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90 self-end"
                            style="background:#002b4d;">
                        Send
                    </button>
                </div>
            </form>
        @else
            <div class="border-t px-5 py-4 text-sm text-center" style="border-color:#EFEFEF;color:#BBBBBB;">
                No other participants found for this order.
            </div>
        @endif
    </div>
</div>

<script>
    var box = document.getElementById('chat-box');
    if (box) box.scrollTop = box.scrollHeight;
</script>
</x-layout>
