<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — LogiSort</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sc.css') }}?v={{ filemtime(public_path('css/sc.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/hover-effects.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/password-field.css') }}">
</head>
<body>
<div class="shell">

    {{-- ── Sidebar ── --}}
    <aside class="sidebar">

        @php
            $me = auth()->user();
            $meInitials = collect(preg_split('/\s+/', trim($me->name ?? 'A')))->filter()->reject(fn ($w) => strtoupper($w) === 'ALVY')->take(2)
                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: mb_strtoupper(mb_substr($me->name ?? 'A', 0, 1));
        @endphp
        <a href="{{ route('sc.dashboard') }}" class="sb-brand" aria-label="LogiSort dashboard">
            <img src="{{ asset('images/logo-mark.png') }}" alt="" class="sb-brand-logo">
            <span class="sb-brand-text">
                <span class="sb-brand-name">LogiSort <small>by ALVY</small></span>
                <span class="sb-brand-sub">{{ $me->assigned_municipality ?: 'Sorting center' }}</span>
            </span>
        </a>

        <a href="{{ route('sc.account') }}" class="sb-user {{ request()->routeIs('sc.account*') ? 'is-active' : '' }}" title="{{ $me->name }} · Account">
            <span class="sb-user-avatar">@if ($me?->profile_photo_url)<img src="{{ $me->profile_photo_url }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">@else{{ $meInitials }}@endif<i aria-hidden="true"></i></span>
            <span class="sb-user-text">
                <span class="sb-user-name">{{ $me->name ?? 'Sorting center' }}</span>
                <span class="sb-user-role">
                    <span class="sb-pill">{{ ucfirst(str_replace('_', ' ', $me->role ?? 'sorting_center')) }}</span>
                </span>
            </span>
            <svg class="sb-user-go" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
        </a>

        <nav class="sidebar-nav">
            <p class="nav-section">Overview</p>
            <a href="{{ route('sc.dashboard') }}" class="nav-link {{ request()->routeIs('sc.dashboard') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Dashboard
            </a>
            <a href="{{ route('sc.tracking') }}" class="nav-link {{ request()->routeIs('sc.tracking*', 'sc.shipments') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h12"/><circle cx="19" cy="17" r="2"/></svg>
                Shipment Board
            </a>
            <p class="nav-section">Parcels</p>
            <a href="{{ route('sc.pickup-requests') }}" class="nav-link {{ request()->routeIs('sc.pickup-requests') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 3l-4 4-4-4"/></svg>
                Pickup Requests
            </a>
            <a href="{{ route('sc.parcel-sorting') }}" class="nav-link {{ request()->routeIs('sc.parcel-sorting') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
                Parcel Sorting
            </a>
            <a href="{{ route('sc.delivery-assignment') }}" class="nav-link {{ request()->routeIs('sc.delivery-assignment', 'sc.dispatch*', 'sc.delivery-monitoring') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                Deliveries
            </a>
            @php
                $navMe = auth()->id();
                $failedWaiting = \App\Models\Parcel::where('current_sorting_center_id', $navMe)->where('status', 'failed')->count();
                $returnsWaiting = \App\Models\ReturnShipment::where('sorting_center_id', $navMe)->whereIn('status', ['requested', 'picked_up', 'at_hub'])->count();
            @endphp
            <a href="{{ route('sc.failed-deliveries') }}" class="nav-link {{ request()->routeIs('sc.failed-deliveries') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M15 9l-6 6M9 9l6 6"/></svg>
                Failed Deliveries
                @if ($failedWaiting)<span class="badge badge-red" style="margin-left:auto;">{{ $failedWaiting }}</span>@endif
            </a>
            <a href="{{ route('sc.returns') }}" class="nav-link {{ request()->routeIs('sc.returns') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/></svg>
                Return Items
                @if ($returnsWaiting)<span class="badge badge-red" style="margin-left:auto;">{{ $returnsWaiting }}</span>@endif
            </a>
            @php $rtsWaiting = \App\Models\Parcel::where('current_sorting_center_id', auth()->id())->where('status', 'return_to_sender')->whereNull('returned_at')->whereNull('rts_rider_id')->count(); @endphp
            <a href="{{ route('sc.rts') }}" class="nav-link {{ request()->routeIs('sc.rts') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14L4 9l5-5M4 9h11a5 5 0 010 10h-3"/></svg>
                Return to Seller
                @if ($rtsWaiting)<span class="badge badge-red" style="margin-left:auto;">{{ $rtsWaiting }}</span>@endif
            </a>
            <a href="{{ route('sc.trips') }}" class="nav-link {{ request()->routeIs('sc.trips*') ? 'active' : '' }}">
                @php
                    $tripsToAccept = \App\Models\LinehaulTrip::where('destination_sorting_center_id', auth()->id())->where('sorting_center_id', '!=', auth()->id())->where('handover_status', 'pending')->count();
                    $tripsOnRoad = \App\Models\LinehaulTrip::involving((int) auth()->id())->whereIn('status', ['loading', 'in_transit'])->count();
                @endphp
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>
                Linehaul Trips
                @if($tripsToAccept > 0)
                    <span style="margin-left:auto;background:var(--accent-orange);color:#fff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;" title="Incoming trips to accept">{{ $tripsToAccept }}</span>
                @elseif($tripsOnRoad > 0)
                    <span style="margin-left:auto;background:var(--accent-blue);color:#fff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;" title="Trips on the road">{{ $tripsOnRoad }}</span>
                @endif
            </a>
            <p class="nav-section">Riders</p>
            <a href="{{ route('sc.riders') }}" class="nav-link {{ request()->routeIs('sc.riders', 'sc.courier-applications.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                @php $pendingCouriers = \App\Models\Courier::where('sorting_center_id', auth()->id())->where('status', 'pending')->count(); @endphp
                Riders
                @if($pendingCouriers > 0)
                    <span style="margin-left:auto;background:var(--accent-orange);color:#fff;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;" title="Courier applications waiting">{{ $pendingCouriers }}</span>
                @endif
            </a>
            <p class="nav-section">Money</p>
            <a href="{{ route('sc.remittances') }}" class="nav-link {{ request()->routeIs('sc.remittances*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                COD Remittances
            </a>
            <a href="{{ route('sc.rider-pay') }}" class="nav-link {{ request()->routeIs('sc.rider-pay*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h15a3 3 0 013 3v7a3 3 0 01-3 3H6a3 3 0 01-3-3V7zm0 0l2.5-3h11L18 7M16.5 13.5h.01"/></svg>
                Rider Pay
                @php $cashoutsWaiting = \App\Models\RiderWalletEntry::where('sorting_center_id', auth()->id())->where('type', 'cashout')->where('status', 'pending')->count(); @endphp
                @if ($cashoutsWaiting)<span class="badge badge-red" style="margin-left:auto;" title="GCash cash-outs to send">{{ $cashoutsWaiting }}</span>@endif
            </a>
            <a href="{{ route('sc.commissions') }}" class="nav-link {{ request()->routeIs('sc.commissions') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                Commission &amp; Earnings
            </a>
            <a href="{{ route('sc.rates') }}" class="nav-link {{ request()->routeIs('sc.rates*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                Zones &amp; Rates
            </a>
            <p class="nav-section">Hub</p>
            <a href="{{ route('sc.areas') }}" class="nav-link {{ request()->routeIs('sc.areas') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Coverage Areas
            </a>
            <a href="{{ route('sc.reports') }}" class="nav-link {{ request()->routeIs('sc.reports', 'sc.activity') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Reports
            </a>
            <a href="{{ route('sc.chat') }}" class="nav-link {{ request()->routeIs('sc.chat') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Chat / Messaging
            </a>
        </nav>

        <div class="sidebar-divider"></div>

        <div class="sidebar-footer">
            <div class="sidebar-footer-name">{{ auth()->user()->name ?? 'System Admin' }}</div>
            <div class="sidebar-footer-email">{{ auth()->user()->email ?? '' }}</div>
        </div>

    </aside>

    {{-- ── Main ── --}}
    <div class="main">
        <div class="topbar">
            <div class="topbar-left">
                <button type="button" class="nav-toggle" data-nav-toggle aria-label="Open menu" aria-expanded="false">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="header-icon">@include('sc.partials.icon', ['name' => trim($__env->yieldContent('icon', 'dashboard')), 'size' => 19])</span>
                @yield('title', 'Dashboard')
            </div>
            <div class="topbar-right" style="display:flex;align-items:center;gap:8px;">@include('partials.theme-toggle')@yield('topbar-right')</div>
        </div>

        <div class="content">
            @yield('content')
        </div>
    </div>

</div>
<div class="nav-backdrop" data-nav-close></div>
<script src="{{ asset('js/responsive.js') }}?v={{ filemtime(public_path('js/responsive.js')) }}" defer></script>
{{-- Messages after an action pop up (top right) instead of sitting above the page. --}}
@include('sc.partials.toasts')
@stack('scripts')
<script src="{{ asset('js/prevent-back.js') }}"></script>
<script src="{{ asset('js/logout-confirm.js') }}?v={{ filemtime(public_path('js/logout-confirm.js')) }}"></script>
<script src="{{ asset('js/upload-progress.js') }}?v={{ filemtime(public_path('js/upload-progress.js')) }}" defer></script>
</body>
</html>
