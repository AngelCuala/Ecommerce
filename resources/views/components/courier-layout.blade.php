@props(['title' => 'Courier — ALVY', 'active' => ''])
@php
    // Riders (motorcycle, tricycle, or a 4-wheeler for hub-to-hub trips) see "Rider"; only direct couriers see "Courier".
    $portalLabel = auth()->check() && \App\Models\Rider::where('user_id', auth()->id())->exists() ? 'Rider' : 'Courier';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $portalLabel }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    {{-- Tailwind CDN powers the shared floating chat widget's utility classes;
         courier.css (loaded after) owns the portal's own sidebar/page styling. --}}
    <script src="{{ asset('js/vendor/tailwindcss.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/courier.css') }}?v={{ filemtime(public_path('css/courier.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/hover-effects.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/password-field.css') }}">
</head>
<body>

@php
    $u = auth()->user();
    $courierUnread = auth()->check()
        ? \App\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count()
        : 0;

    $links = [
        'dashboard'     => ['route' => 'courier.dashboard',     'label' => 'Dashboard',     'icon' => 'dashboard'],
        'history'       => ['route' => 'courier.history',       'label' => 'Delivery History', 'icon' => 'history'],
        'profit'        => ['route' => 'courier.profit',        'label' => 'Earnings',      'icon' => 'earnings'],
        'notifications' => ['route' => 'courier.notifications', 'label' => 'Notifications', 'icon' => 'bell'],
        'account'       => ['route' => 'courier.account',       'label' => 'Account',       'icon' => 'user'],
    ];
    // Riders of a logistics company also get the hub jobs (pickups and deliveries) from the app.
    if (auth()->check() && \App\Models\Rider::where('user_id', auth()->id())->where('application_status', 'approved')->exists()) {
        // A hub rider's own pages; the direct-courier pages (Delivery History, Earnings) only if they also did direct deliveries.
        $direct = \App\Models\Delivery::whereHas('courier', fn ($q) => $q->where('user_id', auth()->id()))->exists();
        $links = array_slice($links, 0, 1, true) + [
            'rider'          => ['route' => 'courier.rider',          'label' => 'Hub Jobs',       'icon' => 'parcel'],
            'rider-history'  => ['route' => 'courier.rider.history',  'label' => 'Job History',    'icon' => 'history'],
            'rider-earnings' => ['route' => 'courier.rider.earnings', 'label' => 'Pay & COD',      'icon' => 'earnings', 'also' => ['rider-cod']],
        ] + array_diff_key(array_slice($links, 1, null, true), $direct ? [] : ['history' => 1, 'profit' => 1]);
    }
    // A 4-wheel rider (car, van, truck): the same rider pages, for hub-to-hub trips instead of buyer deliveries.
    $truck = auth()->check() ? \App\Http\Controllers\Courier\TruckDutyController::truckCourier() : null;
    if ($truck) {
        $links = [
            'trips'         => ['route' => 'courier.trips',         'label' => 'Hub Jobs',    'icon' => 'parcel'],
            'trips-history' => ['route' => 'courier.trips.history', 'label' => 'Job History', 'icon' => 'history'],
            'trips-pay'     => ['route' => 'courier.trips.pay',     'label' => 'Rider Pay',   'icon' => 'earnings'],
        ] + array_intersect_key($links, ['notifications' => 1, 'account' => 1]);
    }
@endphp

<div class="top-bar"></div>

<div class="shell">

    {{-- ══════════ DESKTOP SIDEBAR ══════════ --}}
    <aside class="cx-sidebar">
        <a href="{{ route('courier.dashboard') }}" class="cx-brand">
            <span class="cx-brand-logo"><img src="{{ asset('images/logo-mark.png') }}" alt="ALVY"></span>
            <span class="cx-brand-name">ALVY</span>
        </a>
        <div class="cx-divider"></div>
        <p class="cx-eyebrow">{{ $portalLabel }}</p>

        <nav class="cx-nav">
            @foreach ($links as $key => $link)
                <a href="{{ route($link['route']) }}" class="cx-nav-link {{ $active === $key || in_array($active, $link['also'] ?? [], true) ? 'active' : '' }}">
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
                <span class="cx-avatar" style="overflow:hidden;">
                    <img data-photo-preview src="{{ $u?->profile_photo_url }}" alt="" style="width:100%;height:100%;object-fit:cover;{{ $u?->profile_photo_url ? '' : 'display:none;' }}">
                    @unless ($u?->profile_photo_url)<span data-photo-initial>{{ strtoupper(substr($u->name ?? 'C', 0, 1)) }}</span>@endunless
                </span>
                <div class="min-w-0">
                    <div class="cx-user-name">{{ $u->name ?? 'Courier' }}</div>
                    <div class="cx-user-mail">{{ $u->email ?? '' }}</div>
                </div>
            </div>
        </div>
    </aside>

    {{-- ══════════ MOBILE DRAWER ══════════ --}}
    <div id="cx-backdrop" class="cx-backdrop"></div>
    <aside id="cx-drawer" class="cx-drawer">
        <div class="cx-drawer-head">
            <span class="cx-brand-logo"><img src="{{ asset('images/logo-mark.png') }}" alt="ALVY"></span>
            <span class="cx-brand-name">ALVY</span>
            <button id="cx-close-sidebar" class="cx-drawer-close" aria-label="Close menu">
                @include('courier.partials.icon', ['name' => 'cross', 'size' => 20, 'sw' => 2])
            </button>
        </div>
        <nav class="cx-nav">
            @foreach ($links as $key => $link)
                <a href="{{ route($link['route']) }}" class="cx-nav-link {{ $active === $key || in_array($active, $link['also'] ?? [], true) ? 'active' : '' }}">
                    @include('courier.partials.icon', ['name' => $link['icon'], 'size' => 17])
                    <span>{{ $link['label'] }}</span>
                    @if ($key === 'notifications' && $courierUnread > 0)
                        <span class="cx-nav-badge">{{ $courierUnread > 9 ? '9+' : $courierUnread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </aside>

    {{-- ══════════ MAIN ══════════ --}}
    <div class="cx-main">
        <header class="cx-topbar">
            <button id="cx-open-sidebar" class="cx-hamburger" aria-label="Menu">
                @include('courier.partials.icon', ['name' => 'menu', 'size' => 22, 'sw' => 2])
            </button>
            <div>
                <p class="cx-eyebrow-top">ALVY {{ $portalLabel }}</p>
                <p class="cx-title">{{ $title }}</p>
            </div>
            <div class="cx-topbar-right" style="display:flex;align-items:center;gap:8px;">
                @if ($truck)
                    {{-- Online / Offline on every page (like a rider); while Online, an open page keeps them Online (ping every minute). --}}
                    <a href="{{ route('courier.trips') }}" class="cx-duty-pill {{ $truck->on_duty ? 'is-on' : '' }}" data-duty-pill
                       data-on="{{ $truck->on_duty ? 1 : 0 }}" data-ping="{{ route('courier.trips.ping') }}">
                        <i></i><span data-duty-label>{{ $truck->isSuspended() ? 'Suspended' : ($truck->on_duty ? 'Online' : 'Offline') }}</span>
                    </a>
                @endif
                @include('partials.theme-toggle'){{ $topbar ?? '' }}
            </div>
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
                <button type="button" class="cx-toast-x btn-hover-danger" data-toast-close aria-label="Dismiss">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div class="cx-toast is-error toast">
                <span style="color:#dc2626;">@include('courier.partials.icon', ['name' => 'cross', 'size' => 20])</span>
                <p>{{ session('error') }}</p>
                <button type="button" class="cx-toast-x btn-hover-danger" data-toast-close aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
    <script src="{{ asset('js/toast.js') }}"></script>
@endif

<script src="{{ asset('js/courier-drawer.js') }}"></script>
<script>
// Phones show each table row as a card: label every cell with its column heading (see .cx-table in courier.css).
document.querySelectorAll('.cx-table').forEach(function (table) {
    var heads = Array.from(table.querySelectorAll('thead th')).map(function (th) { return th.textContent.trim(); });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.from(tr.children).forEach(function (td, i) { if (!td.hasAttribute('colspan') && heads[i]) td.setAttribute('data-label', heads[i]); });
    });
});
</script>
<script src="{{ asset('js/Courier.js') }}"></script>
@if ($truck)
<style>
    .cx-duty-pill { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border-radius: 999px; border: 1px solid #e2e8f0; background: #f1f5f9;
                    font-size: 12.5px; font-weight: 700; color: #64748b; text-decoration: none; white-space: nowrap; }
    .cx-duty-pill i { width: 8px; height: 8px; border-radius: 999px; background: #94a3b8; }
    .cx-duty-pill.is-on { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
    .cx-duty-pill.is-on i { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.2); animation: cx-duty-blink 1.6s ease-in-out infinite; }
    @keyframes cx-duty-blink { 50% { opacity: .45; } }
</style>
<script>
// While On Duty, tell ALVY every minute that this courier is still here (with the location when the browser allows it),
// so the company keeps seeing them On Duty and can assign trips. With every page closed they go Offline after 5 minutes.
(function () {
    var pill = document.querySelector('[data-duty-pill]');
    if (!pill || pill.dataset.on !== '1') return;
    var token = document.querySelector('meta[name="csrf-token"]').content, timer;
    function send(pos) {
        var body = new FormData();
        if (pos) { body.append('lat', pos.coords.latitude); body.append('lng', pos.coords.longitude); }
        fetch(pill.dataset.ping, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }, body: body, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                pill.querySelector('[data-duty-label]').textContent = d.on_duty ? 'Online' : 'Offline';
                pill.classList.toggle('is-on', !!d.on_duty);
                if (!d.on_duty) { pill.dataset.on = '0'; clearInterval(timer); }
            }).catch(function () {});
    }
    function ping() {
        if (document.hidden) return;   // the tab in front does it
        if (navigator.geolocation) navigator.geolocation.getCurrentPosition(send, function () { send(null); }, { maximumAge: 60000, timeout: 8000 });
        else send(null);
    }
    timer = setInterval(ping, 60000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) ping(); });
    ping();
})();
</script>
@endif

{{-- Floating chat popup (same widget the other portals use) --}}
@include('partials.chat-widget')

@stack('scripts')
<script src="{{ asset('js/prevent-back.js') }}"></script>
<script src="{{ asset('js/logout-confirm.js') }}?v={{ filemtime(public_path('js/logout-confirm.js')) }}"></script>
<script src="{{ asset('js/upload-progress.js') }}?v={{ filemtime(public_path('js/upload-progress.js')) }}" defer></script>
</body>
</html>
