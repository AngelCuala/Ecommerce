@props(['title' => 'Seller Centre — ALVY', 'active' => ''])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — ALVY Seller</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="{{ asset('js/vendor/tailwindcss.js') }}"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans:    ['"Inter"', 'ui-sans-serif', 'system-ui'],
              display: ['"Nunito"', 'ui-sans-serif', 'system-ui'],
            },
          },
        },
      }
    </script>
    <style>
      *, *::before, *::after { box-sizing: border-box; }
      body { background: #F5F5F5; color: #222222; font-family: 'Inter', ui-sans-serif, system-ui; -webkit-font-smoothing: antialiased; }
      h1,h2,h3,h4,.font-display { font-family: 'Nunito', ui-sans-serif, system-ui; color: #222222; font-weight: 800; }

      .top-bar { height: 3px; background: #fa4e1c; }

      .btn-primary {
        display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
        border-radius:.375rem;background:#fa4e1c;padding:.65rem 1.5rem;
        font-family:'Inter',sans-serif;font-weight:700;font-size:.875rem;
        color:#FFFFFF;border:1px solid transparent;
        box-shadow:0 2px 8px rgba(250,78,28,.28);
        transition:background .18s,transform .15s;
      }
      .btn-primary:hover { background:#d93d0e;transform:translateY(-1px); }

      .btn-outline {
        display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
        border-radius:.375rem;border:1.5px solid #fa4e1c;padding:.65rem 1.5rem;
        font-family:'Inter',sans-serif;font-weight:700;font-size:.875rem;
        color:#fa4e1c;background:transparent;
        transition:background .18s,color .18s;
      }
      .btn-outline:hover { background:#fa4e1c;color:#FFFFFF; }

      .card {
        border-radius:.5rem;background:#fff;
        border:1px solid #EFEFEF;
        box-shadow:0 1px 4px rgba(0,0,0,.04);
        transition:box-shadow .2s,border-color .2s;
      }
      .card:hover { box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:#fdb49e; }

      .input {
        width:100%;border-radius:.375rem;border:1px solid #E0E0E0;
        background:#FFFFFF;padding:.65rem 1rem;
        font-family:'Inter',sans-serif;font-size:.875rem;color:#222222;
        outline:none;transition:border-color .15s,box-shadow .15s;
      }
      .input:focus { border-color:#fa4e1c;box-shadow:0 0 0 3px rgba(250,78,28,.15);background:#fff; }
      .input::placeholder { color:#999999; }

      .section-eyebrow {
        display:block;font-family:'Inter',sans-serif;
        font-size:.6875rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#fa4e1c;
      }

      .nav-active { background:#e8f0f6;color:#fa4e1c;font-weight:700;border-left:3px solid #fa4e1c; }
      .nav-active .nav-dot { background:#fa4e1c; }

      ::-webkit-scrollbar { width:5px; }
      ::-webkit-scrollbar-track { background:#F5F5F5; }
      ::-webkit-scrollbar-thumb { background:#fc8e6e;border-radius:999px; }

      #sidebar-backdrop { transition:opacity .2s; }
      #mobile-sidebar   { transition:transform .25s; }

      @media(prefers-reduced-motion:reduce){ *{ transition-duration:.001ms!important; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/hover-effects.css') }}">
</head>
<body class="min-h-screen" style="background:#F5F5F5;">

<div class="top-bar"></div>

<div class="flex min-h-screen">

    {{-- ══════════════════════════
         DESKTOP SIDEBAR
    ══════════════════════════ --}}
    @php
$navSections = [
            'Store' => [
                'dashboard' => ['route' => 'seller.dashboard',    'label' => 'Dashboard',        'icon' => 'grid'],
                'orders'    => ['route' => 'seller.orders.index', 'label' => 'Order Management', 'icon' => 'orders'],
                'products'  => ['route' => 'seller.books.index',  'label' => 'Inventory',        'icon' => 'box'],
                'reports'   => ['route' => 'seller.reports',      'label' => 'Reports',          'icon' => 'chart'],
                'vouchers'  => ['route' => 'seller.vouchers.index', 'label' => 'Vouchers',       'icon' => 'ticket'],
                'flash'     => ['route' => 'seller.flash-sales.index', 'label' => 'Flash sales', 'icon' => 'ticket'],
            ],
            'General' => [
                'messages'  => ['route' => 'messages.inbox',      'label' => 'Messages',         'icon' => 'chat'],
                'account'   => ['route' => 'seller.account',      'label' => 'Account',          'icon' => 'user'],
            ],
        ];
    @endphp
    <aside class="hidden w-64 shrink-0 flex-col border-r lg:flex"
           style="background:#FFFFFF;border-color:#EFEFEF;">

        {{-- Logo --}}
        <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2.5 px-6 py-6">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-display text-base font-black"
                  style="background:transparent;overflow:hidden;padding:0;"><img src="{{ asset('images/logo-mark.png') }}" alt="ALVY" style="width:100%;height:100%;object-fit:contain;display:block;"></span>
            <div class="leading-tight">
                <span class="font-display text-lg font-extrabold tracking-tight" style="color:#222222;">ALVY</span>
                <span class="block text-[10px] font-semibold uppercase tracking-widest" style="color:#fa4e1c;">Seller Centre</span>
            </div>
        </a>


        @include('partials.panel.sidebar-nav', ['sections' => $navSections, 'active' => $active])

    </aside>

    {{-- ══════════════════════════
         MOBILE DRAWER
    ══════════════════════════ --}}
    <div class="lg:hidden">
        <div id="sidebar-backdrop" class="fixed inset-0 z-40 opacity-0"
             style="display:none;background:rgba(34,34,34,.50);backdrop-filter:blur(3px);"></div>
        <aside id="mobile-sidebar"
               class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col"
               style="display:none;background:#FFFFFF;">
            <div class="flex items-center justify-between px-6 py-6">
                <a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg font-display text-base font-black"
                          style="background:transparent;overflow:hidden;padding:0;"><img src="{{ asset('images/logo-mark.png') }}" alt="ALVY" style="width:100%;height:100%;object-fit:contain;display:block;"></span>
                    <div>
                        <span class="font-display text-lg font-extrabold" style="color:#222222;">ALVY</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-widest" style="color:#fa4e1c;">Seller Centre</span>
                    </div>
                </a>
                <button id="close-sidebar-btn" class="rounded-lg p-1.5" style="color:#999999;" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            @include('partials.panel.sidebar-nav', ['sections' => $navSections, 'active' => $active])
        </aside>
    </div>

    {{-- ══════════════════════════
         MAIN CONTENT
    ══════════════════════════ --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- Topbar --}}
        {{-- Phone-only bar with the menu button; on desktop the sidebar is always visible
             and each page has its own heading. --}}
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b px-4 py-2.5 lg:hidden"
                style="background:rgba(255,255,255,.96);border-color:#EFEFEF;backdrop-filter:blur(12px);">
            <button id="open-sidebar-btn" class="rounded-md p-1.5" style="color:#555555;" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="text-sm font-semibold" style="color:#222;">ALVY Seller Centre</span>
        </header>

        <main class="flex-1 p-5 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Toast notifications (single pop-up, auto-dismiss) --}}
@if (session('success') || session('error'))
    <div id="toast-stack" class="fixed right-4 top-6 z-[100] flex w-full max-w-sm flex-col gap-2 px-2 sm:px-0">
        @if (session('success'))
            <div class="toast flex items-start gap-3 rounded-xl border p-4 shadow-lg"
                 style="background:#fff;border-color:rgba(250,78,28,.30);">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="#fa4e1c" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                <p class="flex-1 text-sm font-semibold" style="color:#d93d0e;">{{ session('success') }}</p>
                <button type="button" data-toast-close class="shrink-0 text-lg leading-none btn-hover-danger" style="color:#9CA3AF;" aria-label="Dismiss">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div class="toast flex items-start gap-3 rounded-xl border p-4 shadow-lg"
                 style="background:#fff;border-color:rgba(220,38,38,.30);">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="#DC2626" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg>
                <p class="flex-1 text-sm font-semibold" style="color:#B91C1C;">{{ session('error') }}</p>
                <button type="button" data-toast-close class="shrink-0 text-lg leading-none btn-hover-danger" style="color:#9CA3AF;" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
    <script src="{{ asset('js/toast.js') }}"></script>
@endif

<script src="{{ asset('js/sidebar-drawer.js') }}"></script>
<script src="{{ asset('js/Seller.js') }}"></script>
<script src="{{ asset('js/prevent-back.js') }}"></script>
</body>
</html>
