<x-admin-layout title="Notifications" active="notifications">

<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-sm" style="color:#6b90aa;">
            {{ $unreadCount }} unread {{ Str::plural('notification', $unreadCount) }}
        </p>
    </div>
    @if ($unreadCount > 0)
        <form action="{{ route('admin.notifications.read-all') }}" method="POST">
            @csrf
            <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">
                Mark all as read
            </button>
        </form>
    @endif
</div>

<div class="space-y-3">
    @forelse ($notifications as $n)
        @php $unread = is_null($n->read_at); @endphp
        <div class="card flex items-start gap-4 p-4"
             style="{{ $unread ? 'border-left:3px solid #fa4e1c;' : '' }}">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                 style="background:{{ $unread ? '#fff1ee' : '#f0f6fa' }};">
                <svg class="h-5 w-5" fill="none" stroke="{{ $unread ? '#fa4e1c' : '#6b90aa' }}" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <p class="font-semibold" style="color:#002b4d;">{{ $n->title }}</p>
                    @if ($unread)
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" style="background:#fff1ee;color:#fa4e1c;">NEW</span>
                    @endif
                </div>
                @if ($n->body)
                    <p class="mt-0.5 text-sm" style="color:#555;">{{ $n->body }}</p>
                @endif
                <p class="mt-1 text-xs" style="color:#6b90aa;">{{ $n->created_at->diffForHumans() }}</p>
            </div>
            @if ($n->link)
                <a href="{{ $n->link }}" class="shrink-0 text-xs font-semibold" style="color:#fa4e1c;">Open</a>
            @endif
        </div>
    @empty
        <div class="card p-10 text-center">
            <p class="text-sm" style="color:#6b90aa;">You have no notifications.</p>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $notifications->links() }}</div>

</x-admin-layout>
