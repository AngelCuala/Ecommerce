@php
    $cartCount   = auth()->check()
        // Number of different products (by product ID) in the cart, not the total quantity.
        ? \App\Models\CartItem::where('user_id', auth()->id())->distinct()->count('product_id')
        : 0;
    $unreadMsgs  = auth()->check()
        ? \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count()
        : 0;
    $unreadNotifs = auth()->check()
        ? \App\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count()
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
            <span class="flex h-9 w-9 items-center justify-center rounded-lg"
                  style="background:#fff;padding:4px;">
                <img src="{{ asset('images/logo-mark.png') }}" alt="ALVY"
                     style="width:100%;height:100%;object-fit:contain;display:block;">
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
                {{-- Notifications --}}
                <a href="{{ route('profile.notifications') }}" title="Notifications" aria-label="Notifications"
                   class="relative hidden h-9 w-9 items-center justify-center rounded-xl transition sm:flex"
                   style="color:#fff;"
                   onmouseover="this.style.background='rgba(255,255,255,.15)';"
                   onmouseout="this.style.background='';">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>
                    </svg>
                    @if ($unreadNotifs > 0)
                        <span class="absolute -right-1 -top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full px-1 text-[9px] font-bold"
                              style="background:#fa4e1c;color:#fff;">{{ $unreadNotifs > 9 ? '9+' : $unreadNotifs }}</span>
                    @endif
                </a>
                {{-- Voucher Center --}}
                <a href="{{ route('vouchers.center') }}" title="Voucher Center" aria-label="Voucher Center"
                   class="relative hidden h-9 w-9 items-center justify-center rounded-xl transition sm:flex"
                   style="color:#fff;"
                   onmouseover="this.style.background='rgba(255,255,255,.15)';"
                   onmouseout="this.style.background='';">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </a>
                {{-- Wishlist --}}
                <a href="{{ route('wishlist.index') }}" title="Wishlist" aria-label="Wishlist"
                   class="relative hidden h-9 w-9 items-center justify-center rounded-xl transition sm:flex"
                   style="color:#fff;"
                   onmouseover="this.style.background='rgba(255,255,255,.15)';"
                   onmouseout="this.style.background='';">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z"/>
                    </svg>
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
                        <span class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full text-[11px] font-bold"
                              style="background:#fff;color:#fa4e1c;" data-nav-avatar>
                            <img data-photo-preview src="{{ auth()->user()->profile_photo_url }}" alt=""
                                 class="h-full w-full object-cover" @unless (auth()->user()->profile_photo_url) style="display:none;" @endunless>
                            @unless (auth()->user()->profile_photo_url)
                                <span data-photo-initial>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            @endunless
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
                        <a href="{{ route('profile.orders') }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm transition"
                           style="color:#002b4d;"
                           onmouseover="this.style.background='#FFF1E6';"
                           onmouseout="this.style.background='';">
                            <svg class="h-4 w-4 opacity-50" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            My Purchases
                        </a>
                    </div>
                </div>
            @else
                {{-- One account button: click to sign in; hover / focus shows sign in and create account. --}}
                <style>
                    .nav-guest { position: relative; margin-left: .25rem; }
                    .nav-guest-btn { display: inline-flex; align-items: center; gap: .55rem; border-radius: 999px; padding: .3rem .9rem .3rem .3rem;
                                     background: #fff; color: #fa4e1c; font-size: .875rem; font-weight: 700; transition: box-shadow .15s, transform .15s; }
                    .nav-guest-btn:hover { box-shadow: 0 0 0 4px rgba(255,255,255,.18); }
                    .nav-guest-ico { display: flex; height: 1.75rem; width: 1.75rem; align-items: center; justify-content: center; border-radius: 999px;
                                     background: linear-gradient(135deg, #fa4e1c, #ff7a45); color: #fff; }
                    .nav-guest-chev { transition: transform .15s; }
                    .nav-guest:hover .nav-guest-chev, .nav-guest:focus-within .nav-guest-chev { transform: rotate(180deg); }
                    .nav-guest-pop { position: absolute; right: 0; top: 100%; z-index: 60; width: 16rem; padding-top: .6rem;
                                     opacity: 0; visibility: hidden; transform: translateY(-4px); transition: opacity .15s, transform .15s, visibility .15s; }
                    .nav-guest:hover .nav-guest-pop, .nav-guest:focus-within .nav-guest-pop { opacity: 1; visibility: visible; transform: none; }
                    .nav-guest-card { border-radius: 1rem; background: #fff; padding: 1rem; box-shadow: 0 18px 40px -16px rgba(0,43,77,.45); border: 1px solid #F0E4DC; }
                    @media (hover: none) { .nav-guest-pop { display: none; } }   /* touch: the button goes straight to sign in */
                </style>
                <div class="nav-guest">
                    <a href="{{ route('login') }}" class="nav-guest-btn" aria-haspopup="true" data-nav-signin>
                        <span class="nav-guest-ico" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-1a6 6 0 00-6-6h-4a6 6 0 00-6 6v1M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                        </span>
                        Sign In
                        <svg class="nav-guest-chev hidden h-3.5 w-3.5 sm:block" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </a>
                    <div class="nav-guest-pop">
                        <div class="nav-guest-card">
                            <p class="text-sm font-bold" style="color:#222;">Welcome to ALVY</p>
                            <p class="mt-0.5 text-xs" style="color:#8a7a70;">Sign in to check out, track orders and save your wishlist.</p>
                            <a href="{{ route('login') }}" class="mt-3 block rounded-xl py-2.5 text-center text-sm font-bold text-white" style="background:linear-gradient(135deg,#fa4e1c,#ff7a45);">Sign In</a>
                            <p class="mt-3 text-center text-xs" style="color:#8a7a70;">New to ALVY?
                                <a href="{{ route('register') }}" class="font-bold hover:underline" style="color:#fa4e1c;">Create an account</a>
                            </p>
                        </div>
                    </div>
                </div>
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
                <a href="{{ route('shop.index', ['sort' => 'newest']) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">New Arrivals</a>
                <a href="{{ route('shop.index', ['search' => "Women's Apparel"]) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">Women</a>
                <a href="{{ route('shop.index', ['search' => "Men's Apparel"]) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">Men</a>
                <a href="{{ route('shop.index', ['search' => 'Accessories']) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">Accessories</a>
                <a href="{{ route('shop.index', ['search' => 'Shoes']) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">Shoes</a>
                <a href="{{ route('shop.index', ['search' => 'Bags']) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.15);">Bags</a>
                <a href="{{ route('shop.index', ['sort' => 'price_low']) }}" class="rounded-full px-3 py-1" style="background:rgba(255,255,255,.25);font-weight:700;">Sale</a>
            </div>
            @auth
                <a href="{{ route('profile.show') }}" class="block rounded-xl py-2.5 text-center text-sm font-semibold" style="background:rgba(255,255,255,.15);color:#fff;">Profile</a>
            @else
                <a href="{{ route('login') }}" class="block rounded-xl py-2.5 text-center text-sm font-semibold" style="background:#fff;color:#fa4e1c;">Sign In</a>
                <a href="{{ route('register') }}" class="block rounded-xl py-2.5 text-center text-sm font-semibold" style="border:1px solid rgba(255,255,255,.5);color:#fff;">Create Account</a>
            @endauth
        </div>
    </div>
</header>

<script src="{{ asset('js/nav.js') }}?v={{ filemtime(public_path('js/nav.js')) }}"></script>
