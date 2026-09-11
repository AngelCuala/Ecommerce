@extends('admin.layouts.app')

@section('title', 'Chat / Messaging')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
@endsection

@section('content')

<div class="chat-layout">

    {{-- Contact list --}}
    <div class="chat-contacts">
        <div class="chat-contacts__header">Messages</div>

        @foreach($contacts as $contact)
            @php
                $unread = \App\Models\Message::where('sender_id',$contact->id)
                    ->where('receiver_id',auth()->id())
                    ->where('is_read', false)->count();
                $last = \App\Models\Message::where(function($q) use($contact) {
                    $q->where('sender_id',auth()->id())->where('receiver_id',$contact->id);
                })->orWhere(function($q) use($contact) {
                    $q->where('sender_id',$contact->id)->where('receiver_id',auth()->id());
                })->latest()->first();
                $initial = strtoupper(substr($contact->name,0,1));
                $colors  = ['#1f4e79','#1a7f37','#7d5a08','#8e1a16'];
                $color   = $colors[$contact->id % count($colors)];
            @endphp
            <a href="{{ route('admin.chat.index',['contact_id'=>$contact->id]) }}"
               class="chat-contact {{ $activeContact?->id===$contact->id ? 'active' : '' }}">
                <div class="chat-contact__avatar" style="background:{{ $color }};">{{ $initial }}</div>
                <div class="chat-contact__body">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="chat-contact__name">{{ $contact->name }}</span>
                        <span class="chat-contact__meta">{{ $last?->created_at?->format('H:i') ?? '' }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:3px;">
                        <span class="chat-contact__preview">{{ $last?->body ?? ucfirst($contact->role ?? 'user') }}</span>
                        @if($unread)<span class="chat-contact__unread">{{ $unread }}</span>@endif
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Message window --}}
    <div class="chat-window">
        @if($activeContact)
            <div class="chat-window__header">
                <div class="chat-contact__avatar" style="background:var(--blue-dim);width:34px;height:34px;font-size:13px;">
                    {{ strtoupper(substr($activeContact->name,0,1)) }}
                </div>
                <div>
                    <div class="chat-window__header-name">{{ $activeContact->name }}</div>
                    <div class="chat-window__header-sub">{{ ucfirst($activeContact->role ?? 'user') }} — {{ $activeContact->email }}</div>
                </div>
            </div>

            <div class="chat-messages" id="chat-messages"
                 data-contact="{{ $activeContact->id }}"
                 data-last-id="{{ $messages->last()?->id ?? 0 }}">
                @foreach($messages as $msg)
                    @php $mine = $msg->sender_id === auth()->id(); @endphp
                    <div class="bubble-row {{ $mine ? 'bubble-row--mine' : '' }}">
                        @if(!$mine)
                            <div class="bubble-avatar">{{ strtoupper(substr($activeContact->name,0,1)) }}</div>
                        @endif
                        <div>
                            <div class="bubble {{ $mine ? 'bubble--mine' : 'bubble--theirs' }}">{{ $msg->body }}</div>
                            <div class="bubble__time">{{ $msg->created_at->format('H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <form class="chat-input-area" method="POST" action="{{ route('admin.chat.send') }}" id="chat-form">
                @csrf
                <input type="hidden" name="receiver_id" value="{{ $activeContact->id }}">
                <input type="text" name="body" placeholder="Type a message…" required autocomplete="off" id="chat-body">
                <button class="btn btn--solid-blue">Send</button>
            </form>
        @else
            <div class="empty-state" style="margin:auto;">
                <div class="empty-state__icon">💬</div>
                Select a conversation to start messaging.
            </div>
        @endif
    </div>

</div>

@endsection

@push('scripts')
<script>
const el = document.getElementById('chat-messages');
if (el) {
    el.scrollTop = el.scrollHeight;

    // Submit form without page reload for snappier UX
    const form = document.getElementById('chat-form');
    form?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const body = document.getElementById('chat-body');
        if (!body.value.trim()) return;
        const fd = new FormData(form);
        await fetch(form.action, { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        // Append own bubble immediately
        const row = document.createElement('div');
        row.className = 'bubble-row bubble-row--mine';
        row.innerHTML = `<div><div class="bubble bubble--mine">${body.value}</div><div class="bubble__time">${new Date().toTimeString().substring(0,5)}</div></div>`;
        el.appendChild(row);
        el.scrollTop = el.scrollHeight;
        el.dataset.lastId = parseInt(el.dataset.lastId || 0) + 1;
        body.value = '';
    });

    // Poll for new messages every 4 s
    setInterval(async () => {
        const contactId = el.dataset.contact;
        const lastId    = el.dataset.lastId;
        try {
            const res  = await fetch(`{{ route('admin.chat.poll') }}?contact_id=${contactId}&after_id=${lastId}`);
            const msgs = await res.json();
            msgs.forEach(m => {
                if (m.sender_id == {{ auth()->id() }}) return; // already appended above
                const row = document.createElement('div');
                row.className = 'bubble-row';
                row.innerHTML = `
                    <div class="bubble-avatar">{{ strtoupper(substr($activeContact->name ?? 'U',0,1)) }}</div>
                    <div>
                        <div class="bubble bubble--theirs">${m.body}</div>
                        <div class="bubble__time">${m.created_at.substring(11,16)}</div>
                    </div>`;
                el.appendChild(row);
                el.dataset.lastId = m.id;
            });
            if (msgs.length) el.scrollTop = el.scrollHeight;
        } catch(_) {}
    }, 4000);
}
</script>
@endpush
