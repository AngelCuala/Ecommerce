<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — LogiSort</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sc.css') }}">
    @stack('styles')
</head>
<body>
<div class="shell">

    {{-- ── Sidebar ── --}}
    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="sidebar-brand-name">LogiSort</div>
            <div class="sidebar-brand-sub">Sorting Center</div>
        </div>

        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
            <div>
                <div class="sidebar-user-name">{{ auth()->user()->name ?? 'Admin' }}</div>
                <div class="sidebar-user-role">{{ ucfirst(auth()->user()->role ?? 'admin') }}</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('sc.dashboard') }}" class="nav-link {{ request()->routeIs('sc.dashboard') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
            <a href="{{ route('sc.riders') }}" class="nav-link {{ request()->routeIs('sc.riders') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Rider Management
            </a>
            <a href="{{ route('sc.pickup-requests') }}" class="nav-link {{ request()->routeIs('sc.pickup-requests') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 3l-4 4-4-4"/></svg>
                Pickup Requests
            </a>
            <a href="{{ route('sc.incoming-parcels') }}" class="nav-link {{ request()->routeIs('sc.incoming-parcels') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
                Incoming Parcels
            </a>
            <a href="{{ route('sc.parcel-sorting') }}" class="nav-link {{ request()->routeIs('sc.parcel-sorting') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                Parcel Sorting
            </a>
            <a href="{{ route('sc.delivery-assignment') }}" class="nav-link {{ request()->routeIs('sc.delivery-assignment') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                Delivery Assignment
            </a>
            <a href="{{ route('sc.delivery-monitoring') }}" class="nav-link {{ request()->routeIs('sc.delivery-monitoring') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Delivery Monitoring
            </a>
            <a href="{{ route('sc.reports') }}" class="nav-link {{ request()->routeIs('sc.reports') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Reports
            </a>
            <a href="{{ route('sc.chat') }}" class="nav-link {{ request()->routeIs('sc.chat') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Chat / Messaging
            </a>
            <a href="{{ route('sc.account') }}" class="nav-link {{ request()->routeIs('sc.account') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Account Management
            </a>
        </nav>

        <div class="sidebar-divider"></div>

        <div class="sidebar-footer">
            <div class="sidebar-footer-name">{{ auth()->user()->name ?? 'System Admin' }}</div>
            <div class="sidebar-footer-email">{{ auth()->user()->email ?? '' }}</div>
            <form method="POST" action="{{ route('sc.logout') }}">
                @csrf
                <button class="btn btn-ghost btn-sm" style="width:100%;justify-content:flex-start;gap:6px;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Logout
                </button>
            </form>
        </div>

    </aside>

    {{-- ── Main ── --}}
    <div class="main">
        <div class="topbar">
            <div class="topbar-left">
                <span class="header-icon">@yield('icon', '🏠')</span>
                @yield('title', 'Dashboard')
            </div>
            <div class="topbar-right">@yield('topbar-right')</div>
        </div>

        <div class="content">
            @if(session('success'))
                <div style="padding:10px 22px 0;"><div class="flash flash-success">{{ session('success') }}</div></div>
            @endif
            @if(session('error'))
                <div style="padding:10px 22px 0;"><div class="flash flash-error">{{ session('error') }}</div></div>
            @endif
            @if($errors->any())
                <div style="padding:10px 22px 0;"><div class="flash flash-error">{{ $errors->first() }}</div></div>
            @endif
            @yield('content')
        </div>
    </div>

</div>
@stack('scripts')
</body>
</html>
