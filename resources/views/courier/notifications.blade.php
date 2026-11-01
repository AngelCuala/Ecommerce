<x-courier-layout title="Notifications" active="notifications">

<div class="cx-page-head" style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;">
    <div>
        <h1 class="font-display">Notifications</h1>
        <p>Job assignments, changes and account activity.{{ $unread ? ' ' . $unread . ' unread.' : '' }}</p>
    </div>
    @if ($unread)
        <form method="POST" action="{{ route('courier.notifications.read-all') }}">@csrf
            <button class="cx-btn cx-btn-orange" style="padding:6px 12px;font-size:12px;">Mark all as read</button>
        </form>
    @endif
</div>

<div class="card" style="padding:8px;">
    @forelse ($notifications as $n)
        @php
            $tone = match ($n->type) {
                'warning' => ['var(--red-soft)','var(--red)'],
                default   => ['var(--accent-soft)','var(--accent)'],
            };
        @endphp
        <a href="{{ route('courier.notifications.open', $n->id) }}" class="cx-notif" data-notification="{{ $n->id }}" @if (is_null($n->read_at)) data-unread @endif>
            <span class="cx-notif-icon" style="background:{{ $tone[0] }};color:{{ $tone[1] }};">
                @include('courier.partials.icon', ['name' => 'bell', 'size' => 20])
            </span>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <p style="font-weight:600;font-size:14px;color:var(--text);">{{ $n->title }}</p>
                    @if (is_null($n->read_at))
                        <span style="height:8px;width:8px;border-radius:999px;background:var(--accent);flex-shrink:0;"></span>
                    @endif
                </div>
                <p style="margin-top:2px;font-size:14px;line-height:1.4;color:#54728a;">{{ $n->body }}</p>
                <p style="margin-top:4px;font-size:11px;color:#c2d1dc;">{{ $n->created_at->diffForHumans() }}</p>
            </div>
        </a>
        @if (! $loop->last)<div class="cx-notif-sep"></div>@endif
    @empty
        <div class="cx-empty">
            <span class="cx-empty-icon">@include('courier.partials.icon', ['name' => 'bell', 'size' => 26])</span>
            <p class="cx-empty-title">No notifications yet</p>
            <p class="cx-empty-sub">Delivery and account updates will appear here.</p>
        </div>
    @endforelse
</div>
@if ($notifications->hasPages())
    <div style="margin-top:12px;">{{ $notifications->links() }}</div>
@endif

</x-courier-layout>
