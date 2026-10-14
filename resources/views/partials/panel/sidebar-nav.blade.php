{{--
    Seller/admin sidebar menu.
    @include('partials.panel.sidebar-nav', [
        'sections' => ['Menu' => ['dashboard' => ['route' => 'seller.dashboard', 'label' => 'Dashboard', 'icon' => 'grid'], …]],
        'active'   => $active,
    ])
--}}
@php
    $icons = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'orders'   => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'box'      => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'chat'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',
        'users'    => '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 3.13a4 4 0 0 1 0 7.75M21 21v-2a4 4 0 0 0-3-3.87"/>',
        'bag'      => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'store'    => '<path d="M3 9l1-5h16l1 5M4 9h16v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9zM9 13h6"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
        'truck'    => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'flag'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
        'bell'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'ticket'   => '<path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 0 0-2 2v3a2 2 0 1 1 0 4v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a2 2 0 1 1 0-4V7a2 2 0 0 0-2-2H5z"/>',
        'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1.08-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    ];
    $unread = \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count();
@endphp

@once
<style>
    .sb-section { padding: 1rem .75rem .35rem; font-size: .65rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #b5a79d; }
    .sb-link { position: relative; display: flex; align-items: center; gap: .7rem; border-radius: .8rem; padding: .45rem .55rem; font-size: .875rem; font-weight: 500; color: #4a3f38; transition: background .15s, color .15s; }
    .sb-link:hover { background: #fff6f2; color: #222; }
    .sb-link:hover .sb-icon { background: #fff1ee; color: #fa4e1c; }
    .sb-link.is-active { background: #fff1ee; color: #fa4e1c; font-weight: 700; }
    .sb-link.is-active::before { content: ''; position: absolute; left: -.75rem; top: 22%; bottom: 22%; width: 3px; border-radius: 0 3px 3px 0; background: #fa4e1c; }
    .sb-icon { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 2rem; height: 2rem; border-radius: .6rem; background: #F7F2EE; color: #8a7a70; transition: all .15s; }
    .sb-link.is-active .sb-icon { background: #fa4e1c; color: #fff; box-shadow: 0 6px 14px -6px rgba(250, 78, 28, .7); }
    .sb-badge { margin-left: auto; display: inline-flex; align-items: center; justify-content: center; min-width: 1.25rem; height: 1.25rem; padding: 0 .35rem; border-radius: 9999px; background: #fa4e1c; color: #fff; font-size: .65rem; font-weight: 700; }
</style>
@endonce

<nav class="flex-1 overflow-y-auto px-3 pb-6">
    @foreach ($sections as $sectionLabel => $links)
        <p class="sb-section">{{ $sectionLabel }}</p>
        <div class="space-y-0.5">
            @foreach ($links as $key => $link)
                @php $isActive = $active === $key; @endphp
                <a href="{{ route($link['route']) }}" class="sb-link {{ $isActive ? 'is-active' : '' }}" @if ($isActive) aria-current="page" @endif>
                    <span class="sb-icon">
                        <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icons[$link['icon']] ?? $icons['grid'] !!}</svg>
                    </span>
                    <span class="truncate">{{ $link['label'] }}</span>
                    @if (($link['icon'] ?? '') === 'chat' && $unread > 0)
                        <span class="sb-badge">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @elseif (! empty($link['badge']))
                        <span class="sb-badge">{{ $link['badge'] > 9 ? '9+' : $link['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
