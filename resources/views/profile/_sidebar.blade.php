{{-- Profile sidebar navigation --}}
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
@endphp

<aside class="w-full lg:w-64 shrink-0">
    {{-- Profile card --}}
    <div class="mb-4 rounded-2xl p-5 flex items-center gap-3.5"
         style="background:#fff;border:1px solid #eef2f6;box-shadow:0 1px 3px rgba(0,0,0,.04);">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full font-display text-lg font-bold"
             style="background:#002b4d;color:#fff;">
            @if (auth()->user()->profile_photo_path)
                <img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" class="h-12 w-12 rounded-full object-cover" alt="Avatar">
            @else
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            @endif
        </div>
        <div class="min-w-0">
            <p class="font-semibold text-sm truncate" style="color:#002b4d;">{{ auth()->user()->name }}</p>
            <p class="text-[11px] truncate" style="color:#9db3c4;">{{ ucfirst(auth()->user()->role ?? 'Buyer') }}</p>
        </div>
    </div>

    {{-- Nav links --}}
    <nav class="rounded-2xl p-2" style="background:#fff;border:1px solid #eef2f6;box-shadow:0 1px 3px rgba(0,0,0,.04);">
        @foreach ($navItems as $routeName => $item)
            @php $active = request()->routeIs($routeName); @endphp
            <a href="{{ route($routeName) }}"
               class="relative flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition"
               style="{{ $active ? 'background:#FFF1E6;color:#fa4e1c;font-weight:600;' : 'color:#54728a;' }}"
               @if (!$active)
               onmouseover="this.style.background='#f6f9fc';this.style.color='#002b4d';"
               onmouseout="this.style.background='';this.style.color='#54728a';"
               @endif>
                @if ($active)
                    <span class="absolute left-0 top-1/2 -translate-y-1/2 h-5 w-1 rounded-r-full" style="background:#fa4e1c;"></span>
                @endif
                <svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    {!! $navIcons[$item['icon']] !!}
                </svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>
