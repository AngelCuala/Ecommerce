<x-logistics-layout title="Chat / Messaging">

<div class="chat-layout">
    {{-- Contacts list --}}
    <div class="chat-contacts">
        <div style="padding:12px 16px;border-bottom:1px solid var(--line);">
            <form method="GET"><input type="search" name="search" placeholder="Search users…" value="{{ request('search') }}" style="width:100%;"></form>
        </div>
        @foreach($contacts as $c)
            <a href="{{ route('logistics.chat.index', ['contact_id'=>$c->id]) }}"
               class="{{ $activeContact?->id == $c->id ? 'active' : '' }}">
                <div style="font-weight:500;">{{ $c->name }}</div>
                <div class="text-muted" style="font-size:12px;">{{ ucfirst($c->role ?? 'user') }}</div>
            </a>
        @endforeach
    </div>

    {{-- Message window --}}
    <div class="chat-window">
        @if($activeContact)
            <div style="padding:12px 18px;border-bottom:1px solid var(--line);font-weight:600;">{{ $activeContact->name }}</div>
            <div class="chat-messages" id="chat-messages">
                @foreach($messages as $msg)
                    <div class="bubble {{ $msg->sender_id === auth()->id() ? 'bubble--mine' : 'bubble--theirs' }}">
                        {{ $msg->body }}
                        <div style="font-size:11px;opacity:.65;margin-top:4px;">{{ $msg->created_at->format('H:i') }}</div>
                    </div>
                @endforeach
            </div>
            <form method="POST" action="{{ route('logistics.chat.send') }}" class="chat-input">
                @csrf
                <input type="hidden" name="receiver_id" value="{{ $activeContact->id }}">
                <input type="text" name="body" placeholder="Type a message…" required autocomplete="off" id="chat-body">
                <button class="btn btn--primary">Send</button>
            </form>
        @else
            <div class="empty-state">Select a contact to start chatting.</div>
        @endif
    </div>
</div>

<script>
// Auto-scroll to bottom
const msgs = document.getElementById('chat-messages');
if (msgs) msgs.scrollTop = msgs.scrollHeight;

// Simple polling every 5s
@if($activeContact)
let lastId = {{ $messages->last()?->id ?? 0 }};
setInterval(async () => {
    const r = await fetch('{{ route('logistics.chat.poll') }}?contact_id={{ $activeContact->id }}&after_id='+lastId);
    const data = await r.json();
    data.forEach(msg => {
        lastId = msg.id;
        const d = document.createElement('div');
        d.className = 'bubble ' + (msg.sender_id == {{ auth()->id() }} ? 'bubble--mine' : 'bubble--theirs');
        d.innerHTML = msg.body + '<div style="font-size:11px;opacity:.65;margin-top:4px;">' + msg.created_at.substring(11,16) + '</div>';
        msgs.appendChild(d);
    });
    if (data.length) msgs.scrollTop = msgs.scrollHeight;
}, 5000);
@endif
</script>

</x-logistics-layout>
