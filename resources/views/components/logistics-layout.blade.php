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
    {{ $styles ?? '' }}
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="sidebar__brand">Parcel<span>Ops</span></div>
        <nav>
            <a href="{{ route('logistics.dashboard') }}"            class="nav-item {{ request()->routeIs('logistics.dashboard')         ? 'active' : '' }}">📊 Dashboard</a>
            <a href="{{ route('logistics.riders.index') }}"         class="nav-item {{ request()->routeIs('logistics.riders.*')          ? 'active' : '' }}">🛵 Rider Management</a>
            <a href="{{ route('logistics.pickup-requests.index') }}" class="nav-item {{ request()->routeIs('logistics.pickup-requests.*') ? 'active' : '' }}">📥 Pickup Requests</a>
            <a href="{{ route('logistics.parcels.index') }}"        class="nav-item {{ request()->routeIs('logistics.parcels.*')         ? 'active' : '' }}">📦 Parcels &amp; Sorting</a>
            <a href="{{ route('logistics.deliveries.assign-index') }}" class="nav-item {{ request()->routeIs('logistics.deliveries.assign-index') ? 'active' : '' }}">🗺 Delivery Assignment</a>
            <a href="{{ route('logistics.deliveries.monitor') }}"   class="nav-item {{ request()->routeIs('logistics.deliveries.monitor') ? 'active' : '' }}">🔍 Monitoring</a>
            <a href="{{ route('logistics.reports.index') }}"        class="nav-item {{ request()->routeIs('logistics.reports.*')         ? 'active' : '' }}">📈 Reports</a>
            <a href="{{ route('logistics.chat.index') }}"           class="nav-item {{ request()->routeIs('logistics.chat.*')            ? 'active' : '' }}">💬 Chat</a>
            <a href="{{ route('logistics.account.edit') }}"         class="nav-item {{ request()->routeIs('logistics.account.*')         ? 'active' : '' }}">👤 Account</a>
        </nav>
        <div class="sidebar__footer">
            <form method="POST" action="{{ route('logistics.logout') }}">
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
</body>
</html>
