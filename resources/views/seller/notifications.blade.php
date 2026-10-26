<x-seller-layout title="Notifications" active="notifications">
<style>
    .sn-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; overflow:hidden; }
    .sn-row { display:flex; gap:.75rem; padding:.85rem 1rem; border-top:1px solid #F2EEEA; }
    .sn-row:first-child { border-top:0; }
    .sn-row:hover { background:#FAFAF9; }
    .sn-row.is-unread { background:#FFF9F6; }
    .sn-icon { display:flex; flex-shrink:0; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:9999px; }
</style>

<div class="mx-auto max-w-3xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <span class="section-eyebrow">General</span>
            <h1 class="text-2xl">Notifications</h1>
            <p class="text-sm" style="color:#8a7f78;">Orders, parcels, product reviews and store updates.{{ $unread ? ' ' . $unread . ' unread.' : '' }}</p>
        </div>
        @if ($unread)
            <form method="POST" action="{{ route('seller.notifications.read-all') }}">@csrf
                <button class="rounded-md border px-3 py-1.5 text-sm font-semibold" style="border-color:#E7E5E4;color:#57534E;background:#fff;">Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="sn-card">
        @forelse ($notifications as $n)
            @php
                $tone = match ($n->type) {
                    'warning' => ['#FEF2F2', '#DC2626'],
                    'success' => ['#ECFDF5', '#059669'],
                    default   => ['#FFF1EE', '#fa4e1c'],
                };
            @endphp
            <a href="{{ route('seller.notifications.open', $n->id) }}" class="sn-row {{ $n->isUnread() ? 'is-unread' : '' }}" data-notification="{{ $n->id }}" @if ($n->isUnread()) data-unread @endif>
                <span class="sn-icon" style="background:{{ $tone[0] }};color:{{ $tone[1] }};">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold" style="color:#222;">{{ $n->title }}</span>
                        @if ($n->isUnread())<span class="h-2 w-2 shrink-0 rounded-full" style="background:#fa4e1c;"></span>@endif
                    </span>
                    @if ($n->body)<span class="mt-0.5 block text-sm" style="color:#57534E;">{{ $n->body }}</span>@endif
                    <span class="mt-1 block text-xs" style="color:#a39890;">{{ $n->created_at->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <p class="px-4 py-12 text-center text-sm" style="color:#a39890;">No notifications yet. Order and store updates will appear here.</p>
        @endforelse
    </div>

    @if ($notifications->hasPages())
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</div>
</x-seller-layout>
