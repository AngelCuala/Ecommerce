@php
    $cartCount   = auth()->check()
        ? \App\Models\CartItem::where('user_id', auth()->id())->sum('quantity')
        : 0;
    $unreadMsgs  = auth()->check()
        ? \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count()
        : 0;
@endphp

{{-- Main header --}}
<header class="sticky top-0 z-50" style="background:#002b4d;">

    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">

        {{-- Mobile trigger --}}
        <button type="button" id="mobile-nav-trigger" aria-expanded="false" aria-controls="mobile-nav-panel"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl md:hidden"
                style="color:#fff;"
                onmouseover="this.style.background='rgba(255,255,255,.15)';"
                onmouseout="this.style.background='';"
                aria-label="Open menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl overflow-hidden"
                  style="background:transparent;">
                <img src="{{ asset('images/logo.png') }}" alt="ALVY"
                     style="width:100%;height:100%;object-fit:cover;transform:scale(1.5);transform-origin:center;">
            </span>
            <span class="hidden font-display text-xl font-bold tracking-tight sm:inline" style="color:#fff;">
                ALVY
            </span>
        </a>

        {{-- Search (Shopee: white rounded bar, orange button, on-brand accent underline) --}}
        <form action="{{ route('shop.index') }}" method="GET" class="hidden flex-1 md:flex">
            <label for="nav-search" class="sr-only">Search</label>
            <div class="relative w-full max-w-2xl">
                <input id="nav-search" type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search products, brands, categories…"
                       style="width:100%;border-radius:4px;border:2px solid #fff;background:#fff;padding:.55rem 3.25rem .55rem 1rem;font-family:'Jost',sans-serif;font-size:.875rem;color:#002b4d;outline:none;">
                <button type="submit" aria-label="Search"
                        class="absolute right-0 top-0 flex h-full w-11 items-center justify-center rounded-r-[2px]"
                        style="background:#002b4d;"
                        onmouseover="this.style.background='#E14F00';"
                        onmouseout="this.style.background='#fa4e1c';">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" style="color:#fff;">
                        <path fill-rule="evenodd" d="M9 3a6 6 0 104.472 10.03l3.75 3.75a.75.75 0 101.06-1.06l-3.75-3.75A6 6 0 009 3zm-4.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </form>

        {{-- Right side --}}
        <div class="ml-auto flex shrink-0 items-center gap-1">

            {{-- Messages / Notifications --}}
            @auth
                <a href="{{ route('messages.inbox') }}" title="Messages" aria-label="Messages"
                   class="relative hidden h-9 w-9 items-center justify-center rounded-xl transition sm:flex"
                   style="color:#fff;"
                   onmouseover="this.style.background='rgba(255,255,255,.15)';"
                   onmouseout="this.style.background='';">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/>
                    </svg>
                    @if ($unreadMsgs > 0)
                        <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold"
                              style="background:#fff;color:#fa4e1c;">{{ $unreadMsgs > 9 ? '9+' : $unreadMsgs }}</span>
                    @endif
                </a>
            @endauth

            {{-- Cart --}}
            <a href="{{ route('cart.index') }}" title="Cart" aria-label="Cart"
               class="relative flex h-9 w-9 items-center justify-center rounded-xl transition"
               style="color:#fff;"
               onmouseover="this.style.background='rgba(255,255,255,.15)';"
               onmouseout="this.style.background='';">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h1.6l1.7 10.4a2 2 0 002 1.6h8.6a2 2 0 002-1.6L20.4 7H6"/>
                    <circle cx="9.5" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                    <circle cx="17" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                </svg>
                @if ($cartCount > 0)
                    <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold"
                          style="background:#fff;color:#fa4e1c;">{{ $cartCount }}</span>
                @endif
            </a>

            {{-- Auth-aware section --}}
            @auth
                <div class="relative ml-1" id="user-menu-wrap">
                    <button id="user-menu-btn" type="button"
                            class="flex items-center gap-2 rounded-full px-2 py-1 text-sm font-medium transition"
                            style="color:#fff;"
                            onmouseover="this.style.background='rgba(255,255,255,.15)';"
                            onmouseout="this.style.background='';"
                            aria-haspopup="true" aria-expanded="false">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold"
                              style="background:#fff;color:#fa4e1c;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span class="hidden max-w-[100px] truncate sm:inline">{{ auth()->user()->name }}</span>
                        <svg class="h-3.5 w-3.5 opacity-80" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    <div id="user-menu-panel" hidden
                         class="absolute right-0 z-50 mt-2 w-44 overflow-hidden rounded-xl shadow-soft"
                         style="background:#fff;border:1px solid #dce8f0;">
                        <a href="{{ route('profile.show') }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                           style="color:#002b4d;"
                           onmouseover="this.style.background='#FFF1E6';"
                           onmouseout="this.style.background='';">
                            <svg class="h-4 w-4 opacity-50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            My Profile
                        </a>
                        @if (auth()->user()->isSeller())
                        <a href="{{ route('seller.dashboard') }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                           style="color:#002b4d;"
                           onmouseover="this.style.background='#FFF1E6';"
                           onmouseout="this.style.background='';">
                            🏪 Seller Dashboard
                        </a>
                        @endif
                        @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                           style="color:#002b4d;"
                           onmouseover="this.style.background='#FFF1E6';"
                           onmouseout="this.style.background='';">
                            ⚙️ Admin Panel
                        </a>
                        @endif
                        <a href="{{ route('messages.inbox') }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                           style="color:#002b4d;"
                           onmouseover="this.style.background='#FFF1E6';"
                           onmouseout="this.style.background='';">
                            💬 My Messages
                        </a>
                        <div style="height:1px;background:#dce8f0;margin:0 .75rem;"></div>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2 px-4 py-2.5 text-sm transition"
                                    style="color:#d93d0e;"
                                    onmouseover="this.style.background='#FEF2F2';"
                                    onmouseout="this.style.background='';">
                                <svg class="h-4 w-4 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}"
                   class="ml-1 rounded-full px-4 py-1.5 text-sm font-semibold transition"
                   style="background:#fff;color:#fa4e1c;"
                   onmouseover="this.style.background='#FFE3CC';"
                   onmouseout="this.style.background='#fff';">Sign In</a>
                <a href="{{ route('register') }}"
                   class="hidden rounded-full px-4 py-1.5 text-sm font-semibold transition sm:inline-block"
                   style="border:1px solid rgba(255,255,255,.6);color:#fff;"
                   onmouseover="this.style.background='rgba(255,255,255,.12)';"
                   onmouseout="this.style.background='';">Sign Up</a>
            @endauth
        </div>
    </div>

    {{-- Mobile panel --}}
    <div id="mobile-nav-panel" hidden class="border-t md:hidden" style="border-color:rgba(255,255,255,.2);background:#fa4e1c;">
        <div class="space-y-4 px-4 py-4">
            <form action="{{ route('shop.index') }}" method="GET">
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search…"
                           style="width:100%;border-radius:4px;border:2px solid #fff;background:#fff;padding:.6rem 3rem .6rem 1rem;font-size:.875rem;color:#002b4d;outline:none;">
                    <button type="submit" style="position:absolute;right:0;top:0;height:100%;width:2.75rem;background:#fa4e1c;border-radius:0 2px 2px 0;display:flex;align-items:center;justify-content:center;" aria-label="Search">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" style="color:#fff;"><path fill-rule="evenodd" d="M9 3a6 6 0 104.472 10.03l3.75 3.75a.75.75 0 101.06-1.06l-3.75-3.75A6 6 0 009 3zm-4.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </form>
            <div class="flex flex-wrap gap-2 text-xs font-medium" style="color:#fff;">
                @foreach (\App\Models\Category::orderBy('name')->take(8)->get() as $cat)
                    <a href="{{ route('shop.index', ['category' => $cat->name]) }}"
                       class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
            @auth
                <div class="flex gap-2">
                    <a href="{{ route('profile.show') }}" class="flex-1 rounded-xl py-2.5 text-center text-sm font-semibold" style="background:rgba(255,255,255,.15);color:#fff;">Profile</a>
                    <form action="{{ route('logout') }}" method="POST" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full rounded-xl py-2.5 text-center text-sm font-semibold" style="background:rgba(255,255,255,.15);color:#fff;">Sign Out</button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="block rounded-xl py-2.5 text-center text-sm font-semibold" style="background:#fff;color:#fa4e1c;">Sign In</a>
                <a href="{{ route('register') }}" class="block rounded-xl py-2.5 text-center text-sm font-semibold" style="border:1px solid rgba(255,255,255,.5);color:#fff;">Create Account</a>
            @endauth
        </div>
    </div>
</header>

<script>
  (function(){
    var btn = document.getElementById('mobile-nav-trigger');
    var pnl = document.getElementById('mobile-nav-panel');
    if (btn && pnl) {
      btn.addEventListener('click', function(e){
        e.stopPropagation();
        pnl.hidden = !pnl.hidden;
        btn.setAttribute('aria-expanded', String(!pnl.hidden));
      });
      document.addEventListener('click', function(e){
        if (!pnl.hidden && !pnl.contains(e.target) && e.target !== btn) pnl.hidden = true;
      });
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && !pnl.hidden) { pnl.hidden = true; btn.focus(); }
      });
    }

    var uBtn = document.getElementById('user-menu-btn');
    var uPnl = document.getElementById('user-menu-panel');
    if (uBtn && uPnl) {
      uBtn.addEventListener('click', function(e){
        e.stopPropagation();
        uPnl.hidden = !uPnl.hidden;
        uBtn.setAttribute('aria-expanded', String(!uPnl.hidden));
      });
      document.addEventListener('click', function(e){
        if (!uPnl.hidden && !uPnl.contains(e.target) && e.target !== uBtn) uPnl.hidden = true;
      });
      document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && !uPnl.hidden) { uPnl.hidden = true; uBtn.focus(); }
      });
    }
  })();
</script>