<x-layout title="Notifications — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1">

            <div class="rounded-2xl p-6 sm:p-7" style="background:#fff;border:1px solid #eef2f6;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full" style="background:#FFF1E6;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#fa4e1c;">
                            <path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold" style="color:#002b4d;">Notifications</h2>
                        <p class="text-xs" style="color:#6b90aa;">Order updates and important account activity.</p>
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
                        'check' => '#059669', 'x' => '#DC2626', 'bell' => '#6b90aa',
                    ];
                @endphp

                @forelse ($notifications as $n)
                    <a href="{{ $n->kind === 'order' ? route('profile.orders') : route('profile.notifications') }}"
                       class="flex items-start gap-4 rounded-xl p-3.5 transition"
                       style="{{ $n->is_unread ? 'background:#FFF9F5;' : '' }}"
                       onmouseover="this.style.background='#f6f9fc';"
                       onmouseout="this.style.background='{{ $n->is_unread ? '#FFF9F5' : '' }}';">

                        {{-- Icon --}}
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                              style="background:rgba(0,0,0,.04);">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                                 style="color:{{ $iconColors[$n->icon] ?? '#6b90aa' }};">
                                {!! $icons[$n->icon] ?? $icons['bell'] !!}
                            </svg>
                        </span>

                        {{-- Text --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-semibold text-sm" style="color:#002b4d;">{{ $n->title }}</p>
                                @if ($n->kind === 'order')
                                    <span class="text-[11px]" style="color:#9db3c4;">· Order #{{ str_pad($n->order_id, 6, '0', STR_PAD_LEFT) }}</span>
                                @else
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                          style="background:#FFF1E6;color:#fa4e1c;">Announcement</span>
                                @endif
                                @if ($n->is_unread)
                                    <span class="ml-auto h-2 w-2 shrink-0 rounded-full" style="background:#fa4e1c;"></span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-sm leading-snug" style="color:#54728a;">{{ $n->body }}</p>
                            @if ($n->item_name)
                                <p class="mt-1 text-xs truncate" style="color:#9db3c4;">{{ $n->item_name }}</p>
                            @endif
                            <p class="mt-1 text-[11px]" style="color:#c2d1dc;">{{ $n->at->diffForHumans() }}</p>
                        </div>

                        {{-- Thumbnail --}}
                        @if ($n->thumbnail)
                            <img src="{{ asset('storage/'.$n->thumbnail) }}" alt=""
                                 class="h-12 w-12 shrink-0 rounded-lg object-cover" style="border:1px solid #eef2f6;">
                        @endif
                    </a>
                    @if (! $loop->last)
                        <div style="height:1px;background:#f0f4f8;margin:0 .5rem;"></div>
                    @endif
                @empty
                    <div class="flex flex-col items-center gap-3 py-16 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full" style="background:#f6f9fc;">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#9db3c4;">
                                <path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                            </svg>
                        </span>
                        <p class="font-semibold text-sm" style="color:#002b4d;">No notifications yet</p>
                        <p class="text-sm" style="color:#6b90aa;">Order updates will appear here once you make a purchase.</p>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</div>
</x-layout>
