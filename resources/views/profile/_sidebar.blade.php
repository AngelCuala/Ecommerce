{{-- Profile sidebar navigation --}}
@include('profile._styles')
@php
    $navItems = [
        'profile.show'          => ['icon' => 'user',  'label' => 'My Profile'],
        'profile.orders'        => ['icon' => 'box',   'label' => 'My Orders'],
        'profile.notifications' => ['icon' => 'bell',  'label' => 'Notifications'],
        'profile.addresses'     => ['icon' => 'pin',   'label' => 'Addresses'],
        'profile.security'      => ['icon' => 'lock',  'label' => 'Accounts & Security'],
        'profile.settings'      => ['icon' => 'cog',   'label' => 'Settings'],
    ];

    $navIcons = [
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',
        'box'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'bell' => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>',
        'pin'  => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'cog'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    ];
    $sbUser = auth()->user();
@endphp

<aside class="w-full shrink-0 lg:sticky lg:top-24 lg:w-72">
    <div class="pf-card overflow-hidden">
        {{-- Cover + avatar --}}
        <div class="h-20" style="background:linear-gradient(120deg,#fa4e1c 0%,#ff8a5b 45%,#002b4d 130%);"></div>
        <div class="-mt-10 flex items-end gap-3 px-5">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl font-display text-2xl font-extrabold text-white"
                 style="background:#002b4d;border:4px solid #fff;box-shadow:0 8px 20px -10px rgba(0,0,0,.4);">
                @if ($sbUser->profile_photo_path)
                    <img src="{{ asset('storage/'.$sbUser->profile_photo_path) }}" class="h-full w-full object-cover" alt="Avatar">
                @else
                    {{ strtoupper(substr($sbUser->name, 0, 1)) }}
                @endif
            </div>
        </div>
        <div class="px-5 pb-4 pt-3">
            <p class="truncate font-display text-lg font-extrabold" style="color:#222;">{{ $sbUser->name }}</p>
            <p class="truncate text-xs pf-muted">{{ $sbUser->email }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                <span class="pf-badge">{{ ucfirst($sbUser->role ?? 'Buyer') }}</span>
                @if ($sbUser->created_at)
                    <span class="pf-badge pf-badge-gray">Since {{ $sbUser->created_at->format('M Y') }}</span>
                @endif
            </div>
        </div>

        {{-- Nav: horizontal pills on mobile, vertical list on desktop --}}
        <nav class="pf-nav pf-nav-scroll flex gap-1 overflow-x-auto border-t p-2 lg:flex-col lg:overflow-visible" style="border-color:#F0E4DC;">
            @foreach ($navItems as $routeName => $item)
                @php $active = request()->routeIs($routeName); @endphp
                <a href="{{ route($routeName) }}"
                   class="{{ $active ? 'is-active' : '' }} flex shrink-0 items-center gap-3 whitespace-nowrap rounded-xl px-2.5 py-2 text-sm font-medium transition">
                    <span class="pf-nav-icon">
                        <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            {!! $navIcons[$item['icon']] !!}
                        </svg>
                    </span>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    <svg class="hidden h-4 w-4 opacity-40 lg:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                </a>
            @endforeach
        </nav>
    </div>
</aside>
