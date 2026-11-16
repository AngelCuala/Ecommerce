@props(['title' => 'Logistics'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — ParcelOps</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hover-effects.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
    {{ $styles ?? '' }}
    <link rel="stylesheet" href="{{ asset('css/password-field.css') }}">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="sidebar__brand">Parcel<span>Ops</span></div>
        <nav>
            <a href="{{ route('admin.logistics.dashboard') }}"            class="nav-item {{ request()->routeIs('admin.logistics.dashboard')         ? 'active' : '' }}">📊 Dashboard</a>
            <a href="{{ route('admin.logistics.riders.index') }}"         class="nav-item {{ request()->routeIs('admin.logistics.riders.*')          ? 'active' : '' }}">🛵 Rider Management</a>
            <a href="{{ route('admin.logistics.pickup-requests.index') }}" class="nav-item {{ request()->routeIs('admin.logistics.pickup-requests.*') ? 'active' : '' }}">📥 Pickup Requests</a>
            <a href="{{ route('admin.logistics.parcels.index') }}"        class="nav-item {{ request()->routeIs('admin.logistics.parcels.*')         ? 'active' : '' }}">📦 Parcels &amp; Sorting</a>
            <a href="{{ route('admin.logistics.deliveries.assign-index') }}" class="nav-item {{ request()->routeIs('admin.logistics.deliveries.assign-index') ? 'active' : '' }}">🗺 Delivery Assignment</a>
            <a href="{{ route('admin.logistics.deliveries.monitor') }}"   class="nav-item {{ request()->routeIs('admin.logistics.deliveries.monitor') ? 'active' : '' }}">🔍 Monitoring</a>
            <a href="{{ route('admin.logistics.reports.index') }}"        class="nav-item {{ request()->routeIs('admin.logistics.reports.*')         ? 'active' : '' }}">📈 Reports</a>
            <a href="{{ route('admin.logistics.chat.index') }}"           class="nav-item {{ request()->routeIs('admin.logistics.chat.*')            ? 'active' : '' }}">💬 Chat</a>
        </nav>
        <div class="sidebar__footer">
            <form method="POST" action="{{ \App\Support\Portal::route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <div class="topbar-left" style="display:flex;align-items:center;gap:8px;min-width:0;">
                <button type="button" class="nav-toggle" data-nav-toggle aria-label="Open menu" aria-expanded="false">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1>{{ $title }}</h1>
            </div>
            <div class="topbar-right" style="display:flex;align-items:center;gap:8px;">@include('partials.theme-toggle')<span class="text-muted">{{ auth()->user()->name ?? 'Admin' }}</span></div>
        </div>
        <div class="content">
            @if(session('success'))
                <div class="flash flash--success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="flash flash--error">{{ session('error') }}</div>
            @endif

            {{ $slot }}
        </div>
    </div>
</div>
<div class="nav-backdrop" data-nav-close></div>
<script src="{{ asset('js/responsive.js') }}?v={{ filemtime(public_path('js/responsive.js')) }}" defer></script>
{{ $scripts ?? '' }}
<script src="{{ asset('js/Logistics.js') }}"></script>
<script src="{{ asset('js/prevent-back.js') }}"></script>
<script src="{{ asset('js/logout-confirm.js') }}?v={{ filemtime(public_path('js/logout-confirm.js')) }}"></script>
<script src="{{ asset('js/upload-progress.js') }}?v={{ filemtime(public_path('js/upload-progress.js')) }}" defer></script>
</body>
</html>
