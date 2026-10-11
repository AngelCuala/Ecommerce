<x-layout title="Notifications — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'Notifications', 'subtitle' => 'Order updates and important account activity.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            <div class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="pf-card-title">Notifications</h2>
                        <p class="text-xs pf-muted">Order updates and important account activity.</p>
                    </div>
                </div>

                @php
                    $icons = [
                        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                        'box'   => '<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
                        'truck' => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
                        'check' => '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                        'x'     => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
                        'bell'  => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>',
                    ];
                    $iconColors = [
                        'clock' => '#B45309', 'box' => '#6B4C3B', 'truck' => '#C2410C',
                        'check' => '#059669', 'x' => '#DC2626', 'bell' => '#8a7a70',
                    ];
                    $iconBgs = [
                        'clock' => '#FFFBEB', 'box' => '#F7F1ED', 'truck' => '#fff1ee',
                        'check' => '#ECFDF5', 'x' => '#FEF2F2', 'bell' => '#F7F1ED',
                    ];
                @endphp

                <div class="space-y-2">
                @forelse ($notifications as $n)
                    <a href="{{ $n->kind === 'order' ? route('profile.orders') : route('profile.notifications') }}"
                       class="relative flex items-start gap-4 rounded-xl border p-3.5 transition hover:border-[#F0E4DC] hover:bg-[#FDF8F5] {{ $n->is_unread ? 'border-[rgba(250,78,28,.18)] bg-[#fff5f3]' : 'border-transparent' }}">

                        @if ($n->is_unread)
                            <span class="absolute bottom-3 left-0 top-3 w-1 rounded-r-full" style="background:#fa4e1c;"></span>
                        @endif

                        {{-- Icon --}}
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                              style="background:{{ $iconBgs[$n->icon] ?? '#F7F1ED' }};">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                                 style="color:{{ $iconColors[$n->icon] ?? '#8a7a70' }};">
                                {!! $icons[$n->icon] ?? $icons['bell'] !!}
                            </svg>
                        </span>

                        {{-- Text --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="text-sm {{ $n->is_unread ? 'font-extrabold' : 'font-bold' }}" style="color:#222;">{{ $n->title }}</p>
                                @if ($n->kind === 'order')
                                    <span class="pf-badge pf-badge-gray">Order #{{ str_pad($n->order_id, 6, '0', STR_PAD_LEFT) }}</span>
                                @else
                                    <span class="pf-badge uppercase">Announcement</span>
                                @endif
                                @if ($n->is_unread)
                                    <span class="ml-auto h-2 w-2 shrink-0 rounded-full" style="background:#fa4e1c;box-shadow:0 0 0 3px rgba(250,78,28,.15);"></span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm leading-snug pf-text">{{ $n->body }}</p>
                            @if ($n->item_name)
                                <p class="mt-1 truncate text-xs pf-muted">{{ $n->item_name }}</p>
                            @endif
                            <p class="mt-1.5 inline-flex items-center gap-1 text-[11px] pf-muted">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                                {{ $n->at->diffForHumans() }}
                            </p>
                        </div>

                        {{-- Thumbnail --}}
                        @if ($n->thumbnail)
                            <img src="{{ asset('storage/'.$n->thumbnail) }}" alt=""
                                 class="h-12 w-12 shrink-0 rounded-lg object-cover" style="border:1px solid #F0E4DC;">
                        @endif
                    </a>
                @empty
                    <div class="flex flex-col items-center gap-3 py-14 text-center">
                        <span class="pf-icon h-16 w-16 rounded-2xl">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                            </svg>
                        </span>
                        <p class="pf-card-title mt-1">No notifications yet</p>
                        <p class="max-w-sm text-sm pf-muted">Order updates will appear here once you make a purchase.</p>
                        <a href="{{ route('home') }}" class="pf-btn mt-2">
                            Start Shopping
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</x-layout>
