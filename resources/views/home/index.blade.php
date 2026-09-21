<x-layout title="ALVY — Online Shopping">

{{-- ═══════════════════════════════════
     HERO — matches the reference layout:
     play badge · centered headline · avatar stack,
     then a 5-column staggered collage with a CTA pill,
     then a quote row with a numbered index.
     Panels without a real product photo are left as
     blank placeholders. Wider side margins throughout.
═══════════════════════════════════ --}}
<section class="relative overflow-hidden" style="background:#FFFFFF;">
    <div class="mx-auto max-w-6xl px-6 py-14 sm:px-10 lg:px-16 lg:py-20">

        {{-- Headline row: play badge / headline / avatar stack --}}
        <div class="hero-in flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-between" style="--d:0ms;">

            {{-- Circular "learn more" badge with rotating label --}}
            <div class="hidden shrink-0 sm:block" style="width:96px;height:96px;">
                <div class="relative" style="width:96px;height:96px;">
                    <svg class="spin-slow" width="96" height="96" viewBox="0 0 100 100">
                        <defs>
                            <path id="heroCirclePath" d="M 50,50 m -38,0 a 38,38 0 1,1 76,0 a 38,38 0 1,1 -76,0" />
                        </defs>
                        <text font-size="7.2" letter-spacing="1.5" style="fill:#222222;font-family:'Nunito',sans-serif;">
                            <textPath href="#heroCirclePath" startOffset="0%">
                                LEARN ABOUT ALVY &#8226; SEE HOW IT WORKS &#8226;
                            </textPath>
                        </text>
                    </svg>
                    <span class="absolute inset-0 m-auto flex items-center justify-center rounded-full"
                          style="width:34px;height:34px;background:#002b4d;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg>
                    </span>
                </div>
            </div>

            <h1 class="max-w-2xl text-center text-[2.25rem] font-extrabold leading-[1.08] sm:text-5xl lg:text-6xl"
                style="color:#222222;font-family:'Nunito',sans-serif;letter-spacing:-0.02em;">
                Bringing More,<br>To Your Cart
            </h1>

            {{-- Avatar stack (blank placeholders) --}}
            <div class="hidden shrink-0 items-center sm:flex" style="margin-right:-6px;">
                <span class="block rounded-full" style="width:34px;height:34px;background:#F5F5F5;border:2px solid #fff;box-shadow:0 0 0 1px #E0E0E0;margin-right:-10px;z-index:1;"></span>
                <span class="block rounded-full" style="width:34px;height:34px;background:#F5F5F5;border:2px solid #fff;box-shadow:0 0 0 1px #E0E0E0;margin-right:-10px;z-index:2;"></span>
                <span class="flex items-center justify-center rounded-full text-xs font-bold" style="width:34px;height:34px;background:#fa4e1c;color:#fff;border:2px solid #fff;z-index:3;">+</span>
            </div>
        </div>

        {{-- Collage: 5 columns, staggered heights, blank panels where there's no photo --}}
        <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-5 sm:gap-4">

            {{-- Col 1: tall photo + short photo below --}}
            <div class="flex flex-col gap-4">
                <div class="hero-in photo-card" style="--d:80ms;height:220px;">
                    <img src="{{ asset('images/photo3.png') }}" alt="ALVY Shop">
                </div>
                <div class="hero-in photo-card" style="--d:120ms;height:110px;">
                    <img src="{{ asset('images/photo4.png') }}" alt="Featured">
                </div>
            </div>

            {{-- Col 2: full height photo --}}
            <div class="hero-in photo-card" style="--d:160ms;height:346px;">
                <img src="{{ asset('images/photo5.png') }}" alt="Featured">
            </div>

            {{-- Col 3: landscape photo + CTA pill --}}
            <div class="flex flex-col justify-center">
                <div class="hero-in photo-card" style="--d:200ms;height:200px;">
                    <img src="{{ asset('images/photo7.png') }}" alt="Featured">
                </div>
                <a href="{{ route('shop.index') }}"
                   class="hero-in mt-3 flex items-center justify-center gap-2 rounded-full py-3 text-sm font-bold transition"
                   style="--d:240ms;background:#002b4d;color:#fff;"
                   onmouseover="this.style.background='#003d6b';" onmouseout="this.style.background='#002b4d';">
                    Explore Collections
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H8M17 7v9"/>
                    </svg>
                </a>
            </div>

            {{-- Col 4: full height photo --}}
            <div class="hero-in photo-card" style="--d:280ms;height:346px;">
                <img src="{{ asset('images/photo6.png') }}" alt="Shop Everything">
            </div>

            {{-- Col 5: tall photo + short photo below --}}
            <div class="flex flex-col gap-4">
                <div class="hero-in photo-card" style="--d:320ms;height:220px;">
                    <img src="{{ asset('images/photo8.png') }}" alt="Featured">
                </div>
                <div class="hero-in photo-card" style="--d:360ms;height:110px;">
                    <img src="{{ asset('images/photo2.png') }}" alt="Featured">
                </div>
            </div>
        </div>

        {{-- Quote + numbered index row --}}
        <div class="hero-in mt-14 flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between" style="--d:400ms;">
            <blockquote class="max-w-md">
                <span class="block text-5xl leading-none" style="color:#E0E0E0;font-family:Georgia,serif;">&#8220;</span>
                <p class="-mt-3 text-sm leading-relaxed" style="color:#555555;">
                    ALVY's are where all your needs are found.
                </p>
            </blockquote>
        </div>
    </div>
</section>

<style>
    /* one orchestrated hero reveal, staggered by --d */
    .hero-in {
        opacity: 0;
        transform: translateY(18px) scale(.98);
        animation: heroIn .7s cubic-bezier(.16,1,.3,1) forwards;
        animation-delay: var(--d, 0ms);
    }
    @keyframes heroIn {
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Flat photo cards with hover lift + image zoom */
    .photo-card {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        cursor: pointer;
        transition: transform .35s cubic-bezier(.16,1,.3,1),
                    box-shadow .35s cubic-bezier(.16,1,.3,1);
    }
    .photo-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .55s cubic-bezier(.16,1,.3,1);
    }
    .photo-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 40px rgba(0,0,0,.18);
    }
    .photo-card:hover img {
        transform: scale(1.08);
    }

    /* slow rotation on the circular "learn more" label; the play icon stays fixed */
    .spin-slow {
        animation: spinSlow 18s linear infinite;
    }
    @keyframes spinSlow {
        to { transform: rotate(360deg); }
    }

    @media (prefers-reduced-motion: reduce) {
        .hero-in { opacity: 1; transform: none; animation: none; }
        .photo-card, .photo-card img { transition: none; }
        .photo-card:hover { transform: none; box-shadow: none; }
        .photo-card:hover img { transform: none; }
        .spin-slow { animation: none; }
    }
</style>

{{-- ═══════════════════════════════════
     CATEGORY PILLS
═══════════════════════════════════ --}}
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <h2 class="mb-5 text-lg font-extrabold" style="color:#222222;font-family:'Nunito',sans-serif;">Shop by Category</h2>
    <div class="overflow-x-auto">
        <div class="flex items-center gap-2 pb-1" style="min-width:max-content;">
            <a href="{{ route('shop.index') }}"
               class="rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
               style="background:#fa4e1c;color:#fff;">
                All
            </a>
            @foreach (array_slice(\App\Http\Controllers\CategoryPageController::catalog(), 0, 5) as $cat)
                <a href="{{ route('categories.show', $cat['slug']) }}"
                   class="flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
                   style="background:#F5F5F5;color:#555555;border:1px solid #E0E0E0;"
                   onmouseover="this.style.borderColor='#fa4e1c';this.style.color='#fa4e1c';this.style.background='#fff1ee';"
                   onmouseout="this.style.borderColor='#E0E0E0';this.style.color='#555555';this.style.background='#F5F5F5';">
                    <span>{{ $cat['icon'] }}</span>
                    {{ $cat['name'] }}
                </a>
            @endforeach
            <a href="{{ route('categories.page') }}"
               class="rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
               style="background:#fff1ee;color:#fa4e1c;border:1px solid #fa4e1c;">
                View All →
            </a>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════
     PRODUCTS GRID
═══════════════════════════════════ --}}
<section class="mx-auto max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">

    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-lg font-extrabold" style="color:#222222;font-family:'Nunito',sans-serif;">Featured Products</h2>
        <a href="{{ route('shop.index') }}"
           class="text-sm font-semibold transition" style="color:#fa4e1c;"
           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
            See all →
        </a>
    </div>

    @php
        $bestSellerIds = \App\Models\OrderItem::select('book_id')
            ->selectRaw('SUM(quantity) as total_sold')
            ->groupBy('book_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->pluck('book_id')
            ->toArray();
    @endphp

    @if ($featured->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed py-24 text-center"
             style="border-color:#E0E0E0;">
            <span class="text-5xl">🛍️</span>
            <p class="font-bold text-lg" style="color:#222222;">No products yet</p>
            <p class="text-sm" style="color:#999999;">Products from sellers will appear here.</p>
            @auth
                @if (auth()->user()->isSeller())
                    <a href="{{ route('seller.books.create') }}" class="btn-gold">+ Add Your First Product</a>
                @endif
            @endauth
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach ($featured as $product)
                <div class="relative">
                    @if (in_array($product->id, $bestSellerIds))
                        <span class="absolute left-2 top-2 z-20 rounded px-2 py-0.5 text-[10px] font-bold uppercase shadow"
                              style="background:#fa4e1c;color:#fff;">
                            🔥 Best Seller
                        </span>
                    @endif
                    <x-product-card :product="$product" />
                </div>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('shop.index') }}"
               class="inline-flex items-center gap-2 rounded-full border px-10 py-3 text-sm font-bold transition"
               style="border-color:#fa4e1c;color:#fa4e1c;"
               onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
               onmouseout="this.style.background='';this.style.color='#fa4e1c';">
                See All Products
            </a>
        </div>
    @endif
</section>

{{-- Divider between Featured Products and Trending Now --}}
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <hr style="border:none;border-top:1px solid #E0E0E0;">
</div>

{{-- ═══════════════════════════════════
     TRENDING NOW
═══════════════════════════════════ --}}
<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h2 class="font-display text-2xl font-extrabold" style="color:#002b4d;">
                Trending Now
                <span style="display:inline-block;width:32px;height:3px;background:#fa4e1c;border-radius:2px;margin-left:10px;vertical-align:middle;"></span>
            </h2>
            <p class="mt-1 text-sm" style="color:#6b90aa;">The products everyone's shopping right now.</p>
        </div>
        <a href="{{ route('shop.index') }}"
           class="hidden shrink-0 items-center gap-1 text-sm font-semibold transition sm:inline-flex"
           style="color:#fa4e1c;"
           onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">
            View All
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
        </a>
    </div>

    @if ($bestSellers->count())
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($bestSellers as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    @else
        <div class="rounded-2xl border py-16 text-center" style="border-color:#cfdce8;background:#fff;">
            <p class="text-sm" style="color:#6b90aa;">No trending products yet. Check back soon!</p>
        </div>
    @endif
</section>

</x-layout>