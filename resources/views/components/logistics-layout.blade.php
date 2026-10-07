@props(['title' => 'Logistics'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — ParcelOps</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hover-effects.css') }}">
    {{ $styles ?? '' }}
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
            <a href="{{ route('admin.logistics.account.edit') }}"         class="nav-item {{ request()->routeIs('admin.logistics.account.*')         ? 'active' : '' }}">👤 Account</a>
        </nav>
        <div class="sidebar__footer">
            <form method="POST" action="{{ route('admin.logistics.logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </div>
    </aside>

    <div class="main">
        <div class="topbar">
            <h1>{{ $title }}</h1>
            <div class="text-muted">{{ auth()->user()->name ?? 'Admin' }}</div>
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
{{ $scripts ?? '' }}
<script src="{{ asset('js/Logistics.js') }}"></script>
<script src="{{ asset('js/prevent-back.js') }}"></script>
</body>
</html>