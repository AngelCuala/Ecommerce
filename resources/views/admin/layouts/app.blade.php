<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — LogiSort</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>
<body>
<div class="shell">

    {{-- ── Sidebar ── --}}
    <aside class="sidebar">

        {{-- Brand --}}
        <div class="sidebar__brand">
            <div class="sidebar__brand-name">LogiSort</div>
            <div class="sidebar__brand-sub">Sorting Center</div>
        </div>

        {{-- User --}}
        <div class="sidebar__user">
            <div class="sidebar__avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
            <div class="sidebar__user-info">
                <div class="sidebar__user-name">{{ auth()->user()->name ?? 'Admin' }}</div>
                <div class="sidebar__user-role">{{ ucfirst(auth()->user()->role ?? 'admin') }}</div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav>
            <a href="{{ route('admin.logistics-dashboard') }}"
               class="nav-item {{ request()->routeIs('admin.logistics-dashboard') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>

            <a href="{{ route('admin.riders.index') }}"
               class="nav-item {{ request()->routeIs('admin.riders.*') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Rider Management
            </a>

            <a href="{{ route('admin.pickup-requests.index') }}"
               class="nav-item {{ request()->routeIs('admin.pickup-requests.*') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 3l-4 4-4-4"/></svg>
                Pickup Requests
            </a>

            <a href="{{ route('admin.parcels.index') }}"
               class="nav-item {{ request()->routeIs('admin.parcels.index') || request()->routeIs('admin.parcels.show') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Incoming Parcels
            </a>

            <a href="{{ route('admin.parcels.sorting') }}"
               class="nav-item {{ request()->routeIs('admin.parcels.sorting') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                Parcel Sorting
            </a>

            <a href="{{ route('admin.deliveries.assign-index') }}"
               class="nav-item {{ request()->routeIs('admin.deliveries.assign-index') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                Delivery Assignment
            </a>

            <a href="{{ route('admin.deliveries.monitor') }}"
               class="nav-item {{ request()->routeIs('admin.deliveries.monitor') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Delivery Monitoring
            </a>

            <a href="{{ route('admin.parcels-reports.index') }}"
               class="nav-item {{ request()->routeIs('admin.parcels-reports.*') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Reports
            </a>

            <a href="{{ route('admin.chat.index') }}"
               class="nav-item {{ request()->routeIs('admin.chat.*') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Chat / Messaging
            </a>

            <a href="{{ route('admin.account.edit') }}"
               class="nav-item {{ request()->routeIs('admin.account.*') ? 'active' : '' }}">
                <svg class="nav-item__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Account Management
            </a>
        </nav>

        <div class="sidebar__divider"></div>

        {{-- Footer --}}
        <div class="sidebar__footer">
            <div class="sidebar__footer-user">{{ auth()->user()->name ?? '' }}<br>{{ auth()->user()->email ?? '' }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn--ghost btn--sm" style="width:100%;justify-content:flex-start;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Logout
                </button>
            </form>
        </div>

    </aside>

    {{-- ── Main ── --}}
    <div class="main">

        {{-- Topbar --}}
        <div class="topbar">
            <div class="topbar__title">
                @yield('topbar-icon', '')
                @yield('title', 'Dashboard')
            </div>
            <div class="topbar__right">
                @yield('topbar-actions')
            </div>
        </div>

        {{-- Content --}}
        <div class="content">
            @if(session('success'))
                <div class="flash flash--success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="flash flash--error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="flash flash--error">{{ $errors->first() }}</div>
            @endif

            @yield('content')
        </div>

    </div>
</div>
@stack('scripts')
</body>
</html>
