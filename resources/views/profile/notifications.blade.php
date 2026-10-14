<x-layout title="Notifications — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    @include('profile._page-header', ['title' => 'Notifications', 'subtitle' => 'Order updates, announcements and reminders.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        @include('profile._sidebar')

        <div class="min-w-0 flex-1">
            @if (session('success'))<div class="pf-alert pf-alert-success mb-4">{{ session('success') }}</div>@endif

            @php $unread = auth()->user()->notifications()->whereNull('read_at')->count(); @endphp
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm pf-muted">{{ $unread }} unread</p>
                @if ($unread)
                    <form action="{{ route('notifications.read-all') }}" method="POST">@csrf
                        <button class="text-sm font-semibold" style="color:#fa4e1c;">Mark all as read</button>
                    </form>
                @endif
            </div>

            <div class="pf-card divide-y overflow-hidden" style="--tw-divide-opacity:1;">
                @forelse ($notifications as $n)
                    @php
                        $dot = match ($n->type) { 'success' => '#059669', 'warning' => '#D97706', 'promo' => '#7C3AED', default => '#2563EB' };
                    @endphp
                    <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 px-5 py-4 transition hover:bg-orange-50/40"
                       style="border-color:#F7F3F0;{{ $n->isUnread() ? 'background:#FFF8F4;' : '' }}">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" style="background:{{ $n->isUnread() ? $dot : '#E7E5E4' }};"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $n->isUnread() ? 'font-bold' : 'font-medium' }}" style="color:#222;">{{ $n->title }}</span>
                            <span class="mt-0.5 block text-sm" style="color:#57534E;">{{ $n->body }}</span>
                            <span class="mt-1 block text-xs pf-muted">{{ $n->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                @empty
                    <p class="p-10 text-center text-sm pf-muted">No notifications yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $notifications->links() }}</div>
        </div>
    </div>
</div>
</div>
</x-layout>
