<x-layout title="ALVY — Online Shopping">

{{-- ═══════════════════════════════════
     HERO BANNER
═══════════════════════════════════ --}}
<section style="background:linear-gradient(135deg,#002b4d 0%,#003d6b 100%);">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid items-center gap-8 lg:grid-cols-2">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-widest"
                      style="background:rgba(255,255,255,.2);color:#fff;">
                    <span class="h-1.5 w-1.5 rounded-full animate-pulse" style="background:#fff;"></span>
                    Shop Now
                </span>
                <h1 class="mt-4 text-4xl font-extrabold leading-tight sm:text-5xl" style="color:#fff;font-family:'Nunito',sans-serif;">
                    Discover Products<br>You'll Love
                </h1>
                <p class="mt-4 max-w-md text-base" style="color:rgba(255,255,255,.8);">
                    Thousands of products from trusted sellers. Great deals, fast delivery, easy returns.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('shop.index') }}"
                       class="rounded-full px-8 py-3 text-sm font-bold transition"
                       style="background:#fff;color:#fa4e1c;"
                       onmouseover="this.style.background='#fff1ee';"
                       onmouseout="this.style.background='#fff';">
                        Shop Now
                    </a>
                    @guest
                        <a href="{{ route('register') }}"
                           class="rounded-full border px-8 py-3 text-sm font-bold transition"
                           style="border-color:rgba(255,255,255,.5);color:#fff;"
                           onmouseover="this.style.background='rgba(255,255,255,.12)';"
                           onmouseout="this.style.background='';">
                            Join Free
                        </a>
                    @endguest
                </div>
            </div>
            <div class="hidden lg:flex justify-end">
                {{-- Hero image carousel --}}
                <div id="hero-carousel" class="relative rounded-2xl shadow-2xl overflow-hidden"
                     style="width:500px;height:320px;">

                    {{-- Slides --}}
                    <div class="carousel-slide absolute inset-0 transition-opacity duration-700 opacity-100">
                        <img src="{{ asset('images/photo1.png') }}"
                             class="h-full w-full object-cover rounded-2xl"
                             alt="ALVY Shop">
                    </div>
                    <div class="carousel-slide absolute inset-0 transition-opacity duration-700 opacity-0">
                        <img src="{{ asset('images/photo2.png') }}"
                             class="h-full w-full object-cover rounded-2xl"
                             alt="Shop Everything">
                    </div>

                    {{-- Prev button --}}
                    <button onclick="heroCarouselPrev()"
                            class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full transition"
                            style="background:rgba(0,0,0,.35);color:#fff;"
                            onmouseover="this.style.background='rgba(0,0,0,.6)';"
                            onmouseout="this.style.background='rgba(0,0,0,.35)';"
                            aria-label="Previous">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    {{-- Next button --}}
                    <button onclick="heroCarouselNext()"
                            class="absolute right-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full transition"
                            style="background:rgba(0,0,0,.35);color:#fff;"
                            onmouseover="this.style.background='rgba(0,0,0,.6)';"
                            onmouseout="this.style.background='rgba(0,0,0,.35)';"
                            aria-label="Next">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    {{-- Dots --}}
                    <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-2">
                        <button onclick="heroCarouselGo(0)" id="hero-dot-0"
                                class="h-2 w-2 rounded-full transition"
                                style="background:#fff;" aria-label="Slide 1"></button>
                        <button onclick="heroCarouselGo(1)" id="hero-dot-1"
                                class="h-2 w-2 rounded-full transition"
                                style="background:rgba(255,255,255,.4);" aria-label="Slide 2"></button>
                    </div>
                </div>

                <script>
                (function () {
                    var slides = document.querySelectorAll('#hero-carousel .carousel-slide');
                    var dots   = [document.getElementById('hero-dot-0'), document.getElementById('hero-dot-1')];
                    var current = 0;
                    var timer;

                    function show(idx) {
                        slides[current].style.opacity = '0';
                        dots[current].style.background = 'rgba(255,255,255,.4)';
                        current = (idx + slides.length) % slides.length;
                        slides[current].style.opacity = '1';
                        dots[current].style.background = '#fff';
                    }

                    window.heroCarouselNext = function () { clearInterval(timer); show(current + 1); autoPlay(); };
                    window.heroCarouselPrev = function () { clearInterval(timer); show(current - 1); autoPlay(); };
                    window.heroCarouselGo  = function (i) { clearInterval(timer); show(i); autoPlay(); };

                    function autoPlay() { timer = setInterval(function () { show(current + 1); }, 4000); }
                    autoPlay();
                })();
                </script>
            </div>
        </div>
    </div>
</section>

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

{{-- ═══════════════════════════════════
     TRUST BAR
═══════════════════════════════════ --}}
<section style="background:#fff;border-top:1px solid #EFEFEF;border-bottom:1px solid #EFEFEF;">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                ['Free Shipping','On qualifying orders'],
                ['Secure Payment','100% protected'],
                ['Easy Returns','30-day returns'],
                ['24/7 Support','Always here to help'],
            ] as [$title, $sub])
                <div class="flex items-center gap-3">
                    <div>
                        <p class="text-sm font-bold" style="color:#222222;">{{ $title }}</p>
                        <p class="text-xs" style="color:#999999;">{{ $sub }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

</x-layout>
