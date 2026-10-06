@props(['title' => 'Admin — ALVY', 'active' => ''])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Admin</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans:    ['"Inter"', 'ui-sans-serif', 'system-ui'],
              display: ['"Nunito"', 'ui-sans-serif', 'system-ui'],
            },
            colors: {
              latte:       '#fa4e1c',
              'latte-light':'#fb7048',
              'latte-dark': '#d93d0e',
              mocha:        '#fa4e1c',
              espresso:     '#002b4d',
              parchment:    '#f0f6fa',
              'parchment-dim':'#dce8f0',
              sand:         '#fff1ee',
              rose:         '#fa4e1c',
              ink:          '#002b4d',
              'ink-muted':  '#4a7a94',
              // legacy
              navy:         '#002b4d',
              gold:         '#fa4e1c',
              'gold-dark':  '#d93d0e',
              cream:        '#f0f6fa',
            },
          },
        },
      }
    </script>
    <style>
      *, *::before, *::after { box-sizing: border-box; }
      body { background: #f0f6fa; color: #002b4d; font-family: 'Inter', ui-sans-serif, system-ui; -webkit-font-smoothing: antialiased; }
      h1,h2,h3,h4,.font-display { font-family: 'Nunito', ui-sans-serif, system-ui; color: #002b4d; font-weight: 800; }

      .top-bar { height: 3px; background: #fa4e1c; }

      .btn-gold, .btn-primary {
        display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
        border-radius:.375rem;background:#fa4e1c;padding:.65rem 1.5rem;
        font-family:'Inter',sans-serif;font-weight:700;font-size:.875rem;
        color:#FFFFFF;border:1px solid transparent;
        box-shadow:0 2px 8px rgba(250,78,28,.28);
        transition:background .18s,transform .15s;
      }
      .btn-gold:hover,.btn-primary:hover { background:#d93d0e;transform:translateY(-1px); }
      .btn-gold:disabled,.btn-primary:disabled { opacity:.5;cursor:not-allowed;transform:none; }

      .btn-navy-outline,.btn-outline {
        display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
        border-radius:.375rem;border:1.5px solid #fa4e1c;padding:.65rem 1.5rem;
        font-family:'Inter',sans-serif;font-weight:700;font-size:.875rem;
        color:#fa4e1c;background:transparent;
        transition:background .18s,color .18s;
      }
      .btn-navy-outline:hover,.btn-outline:hover { background:#fa4e1c;color:#FFFFFF; }

      .card {
        border-radius:.5rem;background:#fff;
        border:1px solid #dce8f0;
        box-shadow:0 1px 4px rgba(0,0,0,.04);
        transition:box-shadow .2s,border-color .2s;
      }
      .card:hover { box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:#fdb49e; }

      .input {
        width:100%;border-radius:.375rem;border:1px solid #E0E0E0;
        background:#FFFFFF;padding:.65rem 1rem;
        font-family:'Inter',sans-serif;font-size:.875rem;color:#002b4d;
        outline:none;transition:border-color .15s,box-shadow .15s;
      }
      .input:focus { border-color:#fa4e1c;box-shadow:0 0 0 3px rgba(250,78,28,.15);background:#fff; }
      .input::placeholder { color:#6b90aa; }

      .section-eyebrow {
        display:block;font-family:'Inter',sans-serif;
        font-size:.6875rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#fa4e1c;
      }

      /* Sidebar active nav — Shopee Seller Centre style: white sidebar, orange left rail */
      .nav-active { background:#e8f0f6;color:#fa4e1c;font-weight:700;border-left:3px solid #fa4e1c; }
      .nav-active .nav-dot { background:#fa4e1c; }

      ::-webkit-scrollbar { width:5px; }
      ::-webkit-scrollbar-track { background:#f0f6fa; }
      ::-webkit-scrollbar-thumb { background:#fc8e6e;border-radius:999px; }

      #sidebar-backdrop { transition:opacity .2s; }
      #mobile-sidebar   { transition:transform .25s; }

      @media(prefers-reduced-motion:reduce){ *{ transition-duration:.001ms!important; } }
    </style>
</head>
<body class="min-h-screen" style="background:#f0f6fa;">

<div class="top-bar"></div>

<div class="flex min-h-screen">

    {{-- ══════════════════════════
         DESKTOP SIDEBAR — white, Shopee Seller Centre style
    ══════════════════════════ --}}
    <aside class="hidden w-64 shrink-0 flex-col border-r lg:flex"
           style="background:#FFFFFF;border-color:#dce8f0;color:#002b4d;">

        {{-- Logo --}}
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 px-6 py-6">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-display text-base font-black"
                  style="background:transparent;overflow:hidden;padding:0;"><img src="{{ asset('images/logo.png') }}" alt="ALVY" style="width:140%;height:140%;object-fit:cover;display:block;margin:-20%;transform:scale(1.5);transform-origin:center;"></span>
            <span class="font-display text-lg font-extrabold tracking-tight" style="color:#002b4d;">
                ALVY
            </span>
        </a>

        <div style="height:1px;background:#dce8f0;margin:0 1.5rem;"></div>
        <p class="px-6 pt-4 pb-1 text-[10px] font-bold uppercase tracking-widest" style="color:#8ab0c4;">Navigation</p>

        <nav class="mt-1 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
            @php
                $links = [
                    // Dashboard
                    'dashboard'           => ['route'=>'admin.dashboard',                 'label'=>'Dashboard'],

                    // Users (buyers + sellers)
                    'customers'           => ['route'=>'admin.customers.index',           'label'=>'Buyers'],
                    'sellers'             => ['route'=>'admin.sellers.index',             'label'=>'Sellers'],
                    'users'               => ['route'=>'admin.users.index',               'label'=>'All User Accounts'],

                    // Seller applications & verification
                    'seller-applications' => ['route'=>'admin.seller-applications.index', 'label'=>'Seller Applications'],

                    // Logistics / Sorting Center registrations
                    'sc-applications'     => ['route'=>'admin.sc-applications.index',     'label'=>'Sorting Center Applications'],

                    // Reports & Complaints (seller compliance / disputes)
                    'compliance'          => ['route'=>'admin.compliance.index',          'label'=>'Reports & Complaints'],

                    // Analytics & Reports
                    'analytics'           => ['route'=>'admin.analytics.index',           'label'=>'Analytics & Reports'],

                    // Notifications
                    'notifications'       => ['route'=>'admin.notifications.index',       'label'=>'Notifications'],

                    // Activity Logs
                    'activity'            => ['route'=>'admin.activity.index',            'label'=>'Activity Logs'],

                    // System Settings
                    'settings'            => ['route'=>'admin.settings.index',            'label'=>'System Settings'],

                    // Messages
                    'messages'            => ['route'=>'messages.inbox',                  'label'=>'Messages'],

                    // Admin Profile (security)
                    'account'             => ['route'=>'admin.account.edit',              'label'=>'Admin Profile'],
                ];
            @endphp
            @foreach ($links as $key => $link)
                @php $isActive = $active === $key; @endphp
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition {{ $isActive ? 'nav-active' : '' }}"
                   style="{{ $isActive ? '' : 'color:#1a4d6e;' }}"
                   onmouseover="{{ $isActive ? '' : "this.style.background='#fff5f3';this.style.color='#fa4e1c';" }}"
                   onmouseout="{{ $isActive ? '' : "this.style.background='';this.style.color='#1a4d6e';" }}">

                    {{-- Per-item SVG icon --}}
                    @if ($key === 'dashboard')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    @elseif ($key === 'customers')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><circle cx="12" cy="13" r="2"/></svg>
                    @elseif ($key === 'sellers')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17"/><circle cx="9" cy="20" r="1"/><circle cx="16" cy="20" r="1"/></svg>
                    @elseif ($key === 'seller-applications' || $key === 'sc-applications')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    @elseif ($key === 'users')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 010 7.75M21 21v-2a4 4 0 00-3-3.87"/></svg>
                    @elseif ($key === 'compliance')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0L3.16 16.25A2 2 0 005 19z"/></svg>
                    @elseif ($key === 'analytics')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    @elseif ($key === 'notifications')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @elseif ($key === 'activity')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    @elseif ($key === 'settings')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                    @elseif ($key === 'messages')
                        @php $adminUnread = \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count(); @endphp
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @elseif ($key === 'account')
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                    @else
                        <span class="nav-dot flex h-2 w-2 shrink-0 rounded-full"
                              style="{{ $isActive ? 'background:#fa4e1c;' : 'background:#E0E0E0;' }}"></span>
                    @endif

                    {{-- Label --}}
                    @if ($key === 'messages')
                        {{ $link['label'] }}
                        @if ($adminUnread > 0)
                            <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[9px] font-bold"
                                  style="background:#fa4e1c;color:#fff;">
                                {{ $adminUnread > 9 ? '9+' : $adminUnread }}
                            </span>
                        @endif
                    @else
                        {{ $link['label'] }}
                    @endif
                </a>
            @endforeach
        </nav>

        <div style="height:1px;background:#dce8f0;margin:0 1.5rem;"></div>

        {{-- User footer --}}
        <div class="p-4">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-bold text-sm"
                      style="background:#fff1ee;color:#fa4e1c;">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold" style="color:#002b4d;">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="truncate text-xs" style="color:#6b90aa;">{{ auth()->user()->email ?? 'admin@ALVY.test' }}</p>
                </div>
            </div>
            <div class="mt-3 flex gap-2">
                <form action="{{ route('logout') }}" method="POST" class="flex-1">
                    @csrf
                    <button class="w-full rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            style="background:#f0f6fa;color:#1a4d6e;"
                            onmouseover="this.style.background='rgba(208,2,27,.08)';this.style.color='#D0021B';"
                            onmouseout="this.style.background='#f0f6fa';this.style.color='#1a4d6e';">
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
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
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg font-display text-base font-black" style="background:transparent;overflow:hidden;padding:0;"><img src="{{ asset('images/logo.png') }}" alt="ALVY" style="width:140%;height:140%;object-fit:cover;display:block;margin:-20%;transform:scale(1.5);transform-origin:center;"></span>
                    <span class="font-display text-lg font-extrabold" style="color:#002b4d;">ALVY</span>
                </a>
                <button id="close-sidebar-btn" class="rounded-lg p-1.5" style="color:#6b90aa;" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
                @foreach ($links as $key => $link)
                    @php $isActive = $active === $key; @endphp
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ $isActive ? 'nav-active' : '' }}"
                       style="{{ $isActive ? '' : 'color:#1a4d6e;' }}">
                        @if ($key === 'dashboard')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        @elseif ($key === 'customers')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><circle cx="12" cy="13" r="2"/></svg>
                        @elseif ($key === 'sellers')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17"/><circle cx="9" cy="20" r="1"/><circle cx="16" cy="20" r="1"/></svg>
                        @elseif ($key === 'seller-applications' || $key === 'sc-applications')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        @elseif ($key === 'users')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 010 7.75M21 21v-2a4 4 0 00-3-3.87"/></svg>
                        @elseif ($key === 'compliance')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0L3.16 16.25A2 2 0 005 19z"/></svg>
                        @elseif ($key === 'analytics')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        @elseif ($key === 'notifications')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @elseif ($key === 'activity')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        @elseif ($key === 'settings')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
                        @elseif ($key === 'messages')
                            @php $adminUnread = \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count(); @endphp
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @elseif ($key === 'account')
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        @endif
                        {{ $link['label'] }}
                        @if ($key === 'messages' && $adminUnread > 0)
                            <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[9px] font-bold"
                                  style="background:#fa4e1c;color:#fff;">
                                {{ $adminUnread > 9 ? '9+' : $adminUnread }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </aside>
    </div>

    {{-- ══════════════════════════
         MAIN CONTENT
    ══════════════════════════ --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex items-center gap-4 border-b px-5 py-3.5 lg:px-8"
                style="background:rgba(255,255,255,.94);border-color:#dce8f0;backdrop-filter:blur(12px);">
            <button id="open-sidebar-btn" class="rounded-lg p-2 lg:hidden" style="color:#1a4d6e;" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div>
                <p class="section-eyebrow leading-none">ALVY Admin</p>
                <h1 class="font-display text-xl font-extrabold leading-tight" style="color:#002b4d;">{{ $title }}</h1>
            </div>
            <div class="ml-auto flex items-center gap-3">
            </div>
        </header>

        <main class="flex-1 p-5 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Toast notifications (pop-up, auto-dismiss) --}}
@if (session('success') || session('error'))
    <div id="toast-stack" class="fixed right-4 top-6 z-[100] flex w-full max-w-sm flex-col gap-2 px-2 sm:px-0">
        @if (session('success'))
            <div class="toast flex items-start gap-3 rounded-xl border p-4 shadow-lg"
                 style="background:#fff;border-color:rgba(250,78,28,.30);">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="#fa4e1c" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                <p class="flex-1 text-sm font-semibold" style="color:#d93d0e;">{{ session('success') }}</p>
                <button type="button" onclick="this.closest('.toast').remove()" class="shrink-0 text-lg leading-none" style="color:#9CA3AF;" aria-label="Dismiss">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div class="toast flex items-start gap-3 rounded-xl border p-4 shadow-lg"
                 style="background:#fff;border-color:rgba(220,38,38,.30);">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="#DC2626" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg>
                <p class="flex-1 text-sm font-semibold" style="color:#B91C1C;">{{ session('error') }}</p>
                <button type="button" onclick="this.closest('.toast').remove()" class="shrink-0 text-lg leading-none" style="color:#9CA3AF;" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
    <script src="{{ asset('js/toast.js') }}"></script>
@endif

<script src="{{ asset('js/sidebar-drawer.js') }}"></script>
</body>
</html>