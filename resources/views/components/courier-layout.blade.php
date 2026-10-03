@props(['title' => 'Courier — ALVY', 'active' => ''])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Courier</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    {{-- Tailwind CDN powers the shared floating chat widget's utility classes;
         courier.css (loaded after) owns the portal's own sidebar/page styling. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/courier.css') }}">
    @stack('styles')
</head>
<body>

@php
    $u = auth()->user();
    $courierUnread = auth()->check()
        ? \App\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count()
        : 0;

    $links = [
        'dashboard'     => ['route' => 'courier.dashboard',     'label' => 'Dashboard',     'icon' => 'dashboard'],
        'history'       => ['route' => 'courier.history',       'label' => 'History',       'icon' => 'history'],
        'profit'        => ['route' => 'courier.profit',        'label' => 'Earnings',      'icon' => 'earnings'],
        'notifications' => ['route' => 'courier.notifications', 'label' => 'Notifications', 'icon' => 'bell'],
        'account'       => ['route' => 'courier.account',       'label' => 'Account',       'icon' => 'user'],
    ];
@endphp

<div class="top-bar"></div>

<div class="shell">

    {{-- ══════════ DESKTOP SIDEBAR ══════════ --}}
    <aside class="cx-sidebar">
        <a href="{{ route('courier.dashboard') }}" class="cx-brand">
            <span class="cx-brand-logo"><img src="{{ asset('images/logo.png') }}" alt="ALVY"></span>
            <span class="cx-brand-name">ALVY</span>
        </a>
        <div class="cx-divider"></div>
        <p class="cx-eyebrow">Courier</p>

        <nav class="cx-nav">
            @foreach ($links as $key => $link)
                <a href="{{ route($link['route']) }}" class="cx-nav-link {{ $active === $key ? 'active' : '' }}">
                    @include('courier.partials.icon', ['name' => $link['icon'], 'size' => 17])
                    <span>{{ $link['label'] }}</span>
                    @if ($key === 'notifications' && $courierUnread > 0)
                        <span class="cx-nav-badge">{{ $courierUnread > 9 ? '9+' : $courierUnread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="cx-foot">
            <div class="cx-user">
                <span class="cx-avatar">{{ strtoupper(substr($u->name ?? 'C', 0, 1)) }}</span>
                <div class="min-w-0">
                    <div class="cx-user-name">{{ $u->name ?? 'Courier' }}</div>
                    <div class="cx-user-mail">{{ $u->email ?? '' }}</div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="cx-logout">
                    @include('courier.partials.icon', ['name' => 'logout', 'size' => 14, 'sw' => 2])
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- ══════════ MOBILE DRAWER ══════════ --}}
    <div id="cx-backdrop" class="cx-backdrop"></div>
    <aside id="cx-drawer" class="cx-drawer">
        <div class="cx-drawer-head">
            <span class="cx-brand-logo"><img src="{{ asset('images/logo.png') }}" alt="ALVY"></span>
            <span class="cx-brand-name">ALVY</span>
            <button id="cx-close-sidebar" class="cx-drawer-close" aria-label="Close menu">
                @include('courier.partials.icon', ['name' => 'cross', 'size' => 20, 'sw' => 2])
            </button>
        </div>
        <nav class="cx-nav">
            @foreach ($links as $key => $link)
                <a href="{{ route($link['route']) }}" class="cx-nav-link {{ $active === $key ? 'active' : '' }}">
                    @include('courier.partials.icon', ['name' => $link['icon'], 'size' => 17])
                    <span>{{ $link['label'] }}</span>
                    @if ($key === 'notifications' && $courierUnread > 0)
                        <span class="cx-nav-badge">{{ $courierUnread > 9 ? '9+' : $courierUnread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="cx-foot">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="cx-logout">
                    @include('courier.partials.icon', ['name' => 'logout', 'size' => 14, 'sw' => 2])
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- ══════════ MAIN ══════════ --}}
    <div class="cx-main">
        <header class="cx-topbar">
            <button id="cx-open-sidebar" class="cx-hamburger" aria-label="Menu">
                @include('courier.partials.icon', ['name' => 'menu', 'size' => 22, 'sw' => 2])
            </button>
            <div>
                <p class="cx-eyebrow-top">ALVY Courier</p>
                <p class="cx-title">{{ $title }}</p>
            </div>
            <div class="cx-topbar-right">{{ $topbar ?? '' }}</div>
        </header>

        <main class="cx-content">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Toasts --}}
@if (session('success') || session('error'))
    <div id="toast-stack" class="cx-toast-stack">
        @if (session('success'))
            <div class="cx-toast is-success toast">
                <span style="color:#fa4e1c;">@include('courier.partials.icon', ['name' => 'check', 'size' => 20])</span>
                <p>{{ session('success') }}</p>
                <button type="button" class="cx-toast-x" onclick="this.closest('.cx-toast').remove()" aria-label="Dismiss">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div class="cx-toast is-error toast">
                <span style="color:#dc2626;">@include('courier.partials.icon', ['name' => 'cross', 'size' => 20])</span>
                <p>{{ session('error') }}</p>
                <button type="button" class="cx-toast-x" onclick="this.closest('.cx-toast').remove()" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
    <script src="{{ asset('js/toast.js') }}"></script>
@endif

<script src="{{ asset('js/courier-drawer.js') }}"></script>

{{-- Floating chat popup (same widget the other portals use) --}}
@include('partials.chat-widget')

@stack('scripts')
</body>
</html>
