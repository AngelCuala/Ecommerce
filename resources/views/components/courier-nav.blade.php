@props(['active' => ''])

@php
    $links = [
        'dashboard' => ['route'=>'courier.dashboard',  'label'=>'Dashboard',   'icon'=>'🏠'],
        'history'   => ['route'=>'courier.history',    'label'=>'History',     'icon'=>'📋'],
        'profit'    => ['route'=>'courier.profit',     'label'=>'Earnings',    'icon'=>'₱'],
    ];
@endphp

<div style="background:#002b4d;">
    <div class="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 py-2.5 sm:px-6 lg:px-8">
        <span class="mr-3 flex items-center gap-1.5 text-sm font-extrabold uppercase tracking-wide whitespace-nowrap" style="color:#FFFFFF;">
            🛵 Courier
        </span>

        @foreach ($links as $key => $link)
            <a href="{{ route($link['route']) }}"
               class="flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition"
               style="{{ $active === $key
                   ? 'background:#FFFFFF;color:#fa4e1c;box-shadow:0 1px 4px rgba(0,0,0,.15);'
                   : 'background:rgba(255,255,255,.12);color:#FFFFFF;' }}"
               onmouseover="{{ $active === $key ? '' : "this.style.background='rgba(255,255,255,.22)';" }}"
               onmouseout="{{ $active === $key ? '' : "this.style.background='rgba(255,255,255,.12)';" }}">
                <span>{{ $link['icon'] }}</span> {{ $link['label'] }}
            </a>
        @endforeach

        <a href="{{ route('messages.inbox') }}"
           class="flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition"
           style="background:rgba(255,255,255,.12);color:#FFFFFF;"
           onmouseover="this.style.background='rgba(255,255,255,.22)';"
           onmouseout="this.style.background='rgba(255,255,255,.12)';">
            💬 Messages
        </a>

        <a href="{{ route('home') }}"
           class="ml-auto flex items-center gap-1 rounded-full px-4 py-1.5 text-sm font-semibold whitespace-nowrap transition"
           style="background:rgba(255,255,255,.12);color:#FFFFFF;"
           onmouseover="this.style.background='rgba(255,255,255,.22)';"
           onmouseout="this.style.background='rgba(255,255,255,.12)';">
            ← Store
        </a>
    </div>
</div>