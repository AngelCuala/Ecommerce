@extends('sc.layout')
@section('title', 'Chat / Messaging')
@section('icon', '💬')

@section('content')
<div class="page-header"><h1>Chat / Messaging</h1></div>
<div class="page-body" style="padding-bottom:0;">

    <div class="chat-layout">

        {{-- Contact list --}}
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">Messages</div>

            @foreach($contacts as $contact)
                @php
                    $initial = strtoupper(substr($contact->name,0,1));
                    $colors  = ['#1565c0','#6a1b9a','#2e7d32','#bf360c','#4e342e','#00695c'];
                    $color   = $colors[$contact->id % count($colors)];
                    $unread  = \App\Models\Message::where('sender_id',$contact->id)
                                   ->where('receiver_id',auth()->id())
                                   ->where('is_read', false)->count();
                    $last    = \App\Models\Message::where(function($q) use($contact){
                                   $q->where('sender_id',auth()->id())->where('receiver_id',$contact->id);
                               })->orWhere(function($q) use($contact){
                                   $q->where('sender_id',$contact->id)->where('receiver_id',auth()->id());
                               })->latest()->first();
                @endphp
                <a href="{{ route('sc.chat',['contact_id'=>$contact->id]) }}"
                   class="chat-item {{ $activeContact?->id===$contact->id ? 'active' : '' }}">
                    <div class="chat-avatar" style="background:{{ $color }};">{{ $initial }}</div>
                    <div class="chat-item-info">
                        <div class="ci-name">{{ $contact->name }}</div>
                        <div class="ci-meta">{{ ucfirst($contact->role ?? 'user') }}</div>
                        <div class="ci-preview">{{ $last?->body ?? 'No messages yet' }}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
                        <span class="chat-item-time">{{ $last?->created_at?->format('H:i') ?? '' }}</span>
                        @if($unread > 0)<span class="unread-dot"></span>@endif
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Message window --}}
        <div class="chat-main">
            @if($activeContact)
                <div class="chat-main-header">
                    <div class="cm-name">{{ $activeContact->name }}</div>
                    <div class="cm-meta">{{ ucfirst($activeContact->role ?? 'user') }}</div>
                </div>

                <div class="chat-messages" id="chat-messages"
                     data-contact="{{ $activeContact->id }}"
                     data-last-id="{{ $messages->last()?->id ?? 0 }}">
                    @foreach($messages as $msg)
                        @php $mine = $msg->sender_id === auth()->id(); @endphp
                        <div class="msg {{ $mine ? 'msg-outgoing' : 'msg-incoming' }}">
                            <div class="msg-bubble">{{ $msg->body }}</div>
                            <div class="msg-time">{{ $msg->created_at->format('H:i') }}</div>
                        </div>
                    @endforeach
                </div>

                <form class="chat-input-bar" method="POST" action="{{ route('sc.chat.send') }}" id="chat-form">
                    @csrf
                    <input type="hidden" name="receiver_id" value="{{ $activeContact->id }}">
                    <input type="text" class="chat-input" id="chat-input" name="body"
                           placeholder="Type a message..." required autocomplete="off">
                    <button type="submit" class="btn btn-blue">Send</button>
                </form>
            @else
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);font-size:13px;">
                    Select a conversation to start messaging.
                </div>
            @endif
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const el = document.getElementById('chat-messages');
if (el) {
    el.scrollTop = el.scrollHeight;

    document.getElementById('chat-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const input = document.getElementById('chat-input');
        if (!input.value.trim()) return;
        const fd = new FormData(this);
        await fetch(this.action, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        const msg = document.createElement('div');
        msg.className = 'msg msg-outgoing';
        msg.innerHTML = `<div class="msg-bubble">${input.value}</div><div class="msg-time">${new Date().toTimeString().substr(0,5)}</div>`;
        el.appendChild(msg);
        el.scrollTop = el.scrollHeight;
        input.value = '';
    });

    setInterval(async () => {
        try {
            const res = await fetch(`{{ route('sc.chat.poll') }}?contact_id=${el.dataset.contact}&after_id=${el.dataset.lastId}`);
            const msgs = await res.json();
            msgs.forEach(m => {
                if (m.sender_id == {{ auth()->id() }}) return;
                const d = document.createElement('div');
                d.className = 'msg msg-incoming';
                d.innerHTML = `<div class="msg-bubble">${m.body}</div><div class="msg-time">${m.created_at.substr(11,5)}</div>`;
                el.appendChild(d);
                el.dataset.lastId = m.id;
            });
            if (msgs.length) el.scrollTop = el.scrollHeight;
        } catch(_) {}
    }, 4000);
}
</script>
@endpush
