<x-layout title="ALVY — Online Shopping">
@php
    // Side tiles link to the two categories with the most items (fallback: first two).
    $promoCats = $shortcuts->sortByDesc('count')->take(2)->values();
    // Category photos (Unsplash License) in public/images/categories: <slug>.jpg square, <slug>-wide.jpg for the banner tiles.
    $photo = fn ($slug, $wide = false) => asset('images/categories/' . $slug . ($wide ? '-wide' : '') . '.jpg');
    // Banner carousel slides (photos: Unsplash License, public/images/banners).
    $slides = [
        ['img' => 'shop',    'pos' => 'center 30%', 'eyebrow' => 'Shop local', 'title' => 'Bringing more to your', 'mark' => 'cart', 'sub' => 'Everyday finds from local sellers, delivered to your barangay.', 'url' => route('categories.page')],
        ['img' => 'fashion', 'pos' => 'center 50%', 'eyebrow' => 'Apparel', 'title' => 'Refresh your', 'mark' => 'wardrobe', 'sub' => 'Tops, dresses and outerwear from shops near you.', 'url' => route('categories.show', 'womens-apparel')],
        ['img' => 'tech',    'pos' => 'center 60%', 'eyebrow' => 'Electronics', 'title' => 'Gadgets for', 'mark' => 'every day', 'sub' => 'Laptops, phones, audio and the accessories that go with them.', 'url' => route('categories.show', 'electronics-and-gadgets')],
        ['img' => 'home',    'pos' => 'center 60%', 'eyebrow' => 'Home & Garden', 'title' => 'Make home a little', 'mark' => 'cozier', 'sub' => 'Furniture, decor and plants to make a space your own.', 'url' => route('categories.show', 'home-and-garden')],
    ];
@endphp
<style>
    .hm-section-title { font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.125rem; font-weight: 800; color: #222; }
    .hm-link { font-size: .875rem; font-weight: 600; color: #fa4e1c; white-space: nowrap; }
    .hm-link:hover { text-decoration: underline; }
    .hm-tile { position: relative; display: block; overflow: hidden; border-radius: .75rem; background: #F5F2EF; }
    .hm-tile img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
    .hm-tile:hover img { transform: scale(1.03); }
    .hm-shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.55) 100%); }
    .hm-cat { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: .75rem .25rem; border-radius: .75rem; text-align: center; transition: background .15s; }
    .hm-cat:hover { background: #fff; }
    .hm-cat-img { display: block; width: 4.75rem; height: 4.75rem; overflow: hidden; border-radius: 9999px; background: #F5F2EF; border: 1px solid #ECE7E3; transition: transform .25s cubic-bezier(.2,.7,.2,1), box-shadow .25s, border-color .25s; }
    .hm-cat-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s cubic-bezier(.2,.7,.2,1); }
    .hm-cat:hover .hm-cat-img { transform: translateY(-4px); border-color: #fff; box-shadow: 0 0 0 2px #fa4e1c, 0 8px 18px rgba(28,25,23,.12); }
    .hm-cat:hover .hm-cat-img img { transform: scale(1.12); }
    .hm-cat-name { transition: color .15s; }
    .hm-cat:hover .hm-cat-name { color: #fa4e1c !important; }
    .hm-link .hm-arrow { display: inline-block; }
    .hm-link:hover .hm-arrow { transform: translateX(3px); }
    /* Section titles get a short orange bar that grows in */
    .hm-section-title { position: relative; padding-bottom: .35rem; }
    .hm-section-title::after { content: ""; position: absolute; left: 0; bottom: 0; height: 3px; width: 1.75rem; border-radius: 3px; background: #fa4e1c; transform-origin: left; transition: transform .6s cubic-bezier(.2,.7,.2,1) .15s; }
    .js-rv [data-rv]:not(.is-in) .hm-section-title::after { transform: scaleX(0); }
    /* Hand-drawn style underline under the headline keyword */
    .hm-mark { position: relative; white-space: nowrap; }
    .hm-mark svg { position: absolute; left: -2%; bottom: -.3em; width: 104%; height: .5em; overflow: visible; }
    .hm-mark path { stroke-dasharray: 120; stroke-dashoffset: 120; animation: hm-draw .8s cubic-bezier(.6,0,.2,1) .55s forwards; }
    @keyframes hm-draw { to { stroke-dashoffset: 0; } }
    /* Scroll reveal (only when JS is running, so content never stays hidden) */
    .js-rv [data-rv] { opacity: 0; transform: translateY(18px); transition: opacity .6s ease, transform .6s cubic-bezier(.2,.7,.2,1); }
    .js-rv [data-rv].is-in { opacity: 1; transform: none; }
    .js-rv [data-rv-items] > * { opacity: 0; transform: translateY(14px) scale(.98); }
    .js-rv [data-rv].is-in [data-rv-items] > * { animation: hm-pop-in .5s cubic-bezier(.2,.7,.2,1) both; animation-delay: calc(var(--i, 0) * 45ms + .1s); }
    @keyframes hm-pop-in { to { opacity: 1; transform: none; } }
    /* Banner: photo settles in, copy rises in order, side tiles follow */
    @keyframes hm-settle { from { transform: scale(1.06); } to { transform: scale(1); } }
    @keyframes hm-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    @keyframes hm-drift { from { transform: scale(1) translate(0, 0); } to { transform: scale(1.06) translate(-1.5%, -1%); } }
    /* Banner carousel: crossfade, slow zoom on the active photo, copy rises in, dots show time left */
    .hm-car { isolation: isolate; }
    .hm-slide { position: absolute; inset: 0; opacity: 0; visibility: hidden; transition: opacity .9s ease, visibility 0s linear .9s; }
    .hm-slide.is-active { z-index: 1; opacity: 1; visibility: visible; transition: opacity .9s ease; }
    .hm-car .hm-slide img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transform: scale(1.1); transition: transform 7.5s cubic-bezier(.2,.6,.3,1); }
    .hm-car .hm-slide.is-active img { transform: scale(1); }
    .hm-car-shade { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(24,16,10,.7) 0%, rgba(24,16,10,.35) 42%, rgba(24,16,10,0) 72%); }
    .hm-slide [data-c] { opacity: 0; transform: translateY(16px); transition: opacity .5s ease, transform .6s cubic-bezier(.2,.7,.2,1); }
    .hm-slide.is-active [data-c] { opacity: 1; transform: none; transition-delay: calc(.25s + var(--c) * .1s); }
    .hm-slide .hm-mark path { animation: none; }
    .hm-slide.is-active .hm-mark path { animation: hm-draw .8s cubic-bezier(.6,0,.2,1) .75s forwards; }
    .hm-car-btn { position: absolute; top: 50%; z-index: 5; display: flex; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: 9999px; background: rgba(255,255,255,.9); color: #222; box-shadow: 0 2px 8px rgba(0,0,0,.15); opacity: 0; transform: translateY(-50%) scale(.9); transition: opacity .2s, transform .2s, background .15s; }
    .hm-car:hover .hm-car-btn, .hm-car-btn:focus-visible { opacity: 1; transform: translateY(-50%) scale(1); }
    .hm-car-btn:hover { background: #fff; color: #fa4e1c; }
    @media (hover: none) { .hm-car-btn { display: none; } }
    .hm-dot { position: relative; height: 4px; width: 1.25rem; overflow: hidden; border-radius: 9999px; background: rgba(255,255,255,.4); transition: width .35s cubic-bezier(.2,.7,.2,1), background .2s; }
    .hm-dot:hover { background: rgba(255,255,255,.7); }
    .hm-dot.is-active { width: 2.75rem; }
    .hm-dot span { position: absolute; inset: 0; transform: scaleX(0); transform-origin: left; background: #fff; }
    .hm-dot.is-active span { animation: hm-fill 6s linear forwards; }
    .hm-car.is-paused .hm-dot.is-active span { animation-play-state: paused; }
    @keyframes hm-fill { to { transform: scaleX(1); } }
    .hm-in { animation: hm-rise .6s cubic-bezier(.2,.7,.2,1) both; animation-delay: var(--d, 0s); }
    .hm-arrow { transition: transform .2s ease; }
    .hm-tile:hover .hm-arrow, .hm-sell:hover .hm-arrow { transform: translateX(4px); }
    .hm-cta { transition: background-color .15s; }
    .hm-tile:hover .hm-cta { background: #e2400f !important; }
    @media (prefers-reduced-motion: reduce) {
        .hm-tile img { transition: none; } .hm-tile:hover img { transform: none; }
        .hm-in, .hm-mark path, .hm-slide.is-active .hm-mark path { animation: none; }
        .hm-slide, .hm-slide.is-active, .hm-slide [data-c], .hm-car .hm-slide img { transition: none; } .hm-car .hm-slide img { transform: none; }
        .hm-dot.is-active span { animation: none; transform: scaleX(1); } .hm-mark path { stroke-dashoffset: 0; } .hm-arrow { transition: none; }
        .js-rv [data-rv], .js-rv [data-rv-items] > * { opacity: 1; transform: none; transition: none; animation: none !important; }
        .hm-section-title::after { transition: none; } .js-rv [data-rv]:not(.is-in) .hm-section-title::after { transform: none; }
    }
</style>

<div class="mx-auto max-w-7xl space-y-10 px-4 py-6 sm:px-6 lg:px-8">

    {{-- ═══ Banner ═══ --}}
    <section class="grid gap-3 lg:h-[22rem] lg:grid-cols-3 lg:grid-rows-2">
        <div class="hm-tile hm-car h-64 sm:h-72 lg:col-span-2 lg:row-span-2 lg:h-auto" data-carousel
             role="region" aria-roledescription="carousel" aria-label="Featured">
            @foreach ($slides as $n => $sl)
                <a href="{{ $sl['url'] }}" class="hm-slide {{ $n === 0 ? 'is-active' : '' }}" data-slide
                   aria-roledescription="slide" aria-label="{{ $n + 1 }} of {{ count($slides) }}" @if ($n) tabindex="-1" aria-hidden="true" @endif>
                    <img src="{{ asset('images/banners/' . $sl['img'] . '.jpg') }}" alt="" style="object-position:{{ $sl['pos'] }};">
                    <div class="hm-car-shade"></div>
                    <div class="absolute bottom-10 left-5 max-w-[20rem] sm:bottom-12 sm:left-8 sm:max-w-sm">
                        <p data-c style="--c:0;color:#ffcbb8;" class="text-xs font-semibold uppercase tracking-wider">{{ $sl['eyebrow'] }}</p>
                        <{{ $n === 0 ? 'h1' : 'p' }} data-c style="--c:1;color:#fff;" class="mt-1 font-display text-2xl font-extrabold leading-tight sm:text-3xl">
                            {{ $sl['title'] }} <span class="hm-mark">{{ $sl['mark'] }}<svg viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><path d="M2 7 C 25 2, 60 2, 98 5" fill="none" stroke="#fa4e1c" stroke-width="3.5" stroke-linecap="round"/></svg></span>
                        </{{ $n === 0 ? 'h1' : 'p' }}>
                        <p data-c style="--c:2;color:rgba(255,255,255,.88);" class="mt-3 text-sm">{{ $sl['sub'] }}</p>
                        <span data-c style="--c:3;background:#fa4e1c;" class="hm-cta mt-4 inline-flex w-max items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold text-white">
                            Shop now
                            <svg class="hm-arrow h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </div>
                </a>
            @endforeach

            <button type="button" class="hm-car-btn left-3" data-car-prev aria-label="Previous slide">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6"/></svg>
            </button>
            <button type="button" class="hm-car-btn right-3" data-car-next aria-label="Next slide">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>
            <div class="absolute bottom-4 left-5 z-[5] flex items-center gap-1.5 sm:left-8">
                @foreach ($slides as $n => $sl)
                    <button type="button" class="hm-dot {{ $n === 0 ? 'is-active' : '' }}" data-car-dot="{{ $n }}" aria-label="Show slide {{ $n + 1 }}"><span></span></button>
                @endforeach
            </div>
        </div>
        @foreach ($promoCats as $i => $cat)
            <a href="{{ route('categories.show', $cat->slug) }}" class="hm-tile hm-in hidden h-40 sm:block lg:h-auto" style="--d:{{ .25 + $i * .1 }}s;">
                <img src="{{ $photo($cat->slug, true) }}" alt="{{ $cat->name }}">
                <div class="hm-shade" style="background:linear-gradient(180deg,rgba(0,0,0,0) 30%,rgba(0,0,0,.65) 100%);"></div>
                <div class="absolute bottom-4 left-4">
                    <p class="font-display text-lg font-bold leading-tight" style="color:#fff;">{{ $cat->name }}</p>
                    <p class="mt-0.5 text-sm" style="color:rgba(255,255,255,.85);">{{ $cat->count }} {{ \Illuminate\Support\Str::plural('item', $cat->count) }} &middot; Shop now <span class="hm-arrow inline-block">&rarr;</span></p>
                </div>
            </a>
        @endforeach
    </section>

    {{-- ═══ Categories ═══ --}}
    <section data-rv>
        <div class="mb-3 flex items-baseline justify-between">
            <h2 class="hm-section-title">Categories</h2>
            <a href="{{ route('categories.page') }}" class="hm-link">All categories <span class="hm-arrow">&rarr;</span></a>
        </div>
        <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 md:grid-cols-6" data-rv-items>
            @foreach ($shortcuts as $cat)
                <a href="{{ route('categories.show', $cat->slug) }}" class="hm-cat">
                    <span class="hm-cat-img">
                        <img src="{{ $photo($cat->slug) }}" alt="" loading="lazy">
                    </span>
                    <span class="hm-cat-name text-xs font-medium leading-tight sm:text-sm" style="color:#222;">{{ $cat->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ═══ New arrivals ═══ --}}
    <section data-rv>
        <div class="mb-3 flex items-baseline justify-between">
            <h2 class="hm-section-title">New arrivals</h2>
            <a href="{{ route('categories.page') }}" class="hm-link">See all <span class="hm-arrow">&rarr;</span></a>
        </div>
        @if ($featured->isEmpty())
            <div class="rounded-xl border bg-white px-6 py-14 text-center" style="border-color:#ECE7E3;">
                <p class="font-medium" style="color:#222;">No products yet</p>
                <p class="mt-1 text-sm" style="color:#8a7f78;">Products from sellers will appear here.</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6" data-rv-items>
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ Best sellers ═══ --}}
    @if ($trending->isNotEmpty())
        <section data-rv>
            <div class="mb-3 flex items-baseline justify-between">
                <h2 class="hm-section-title">Best sellers</h2>
                <a href="{{ route('categories.page') }}" class="hm-link">See all <span class="hm-arrow">&rarr;</span></a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6" data-rv-items>
                @foreach ($trending as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══ Sell on ALVY (guests only) ═══ --}}
    @guest
        <section data-rv class="flex flex-col items-start justify-between gap-4 rounded-xl border bg-white px-6 py-5 sm:flex-row sm:items-center" style="border-color:#ECE7E3;">
            <div>
                <h2 class="hm-section-title">Have something to sell?</h2>
                <p class="mt-0.5 text-sm" style="color:#8a7f78;">Open a shop on ALVY and reach buyers near you.</p>
            </div>
            <a href="{{ route('seller.register') }}" class="hm-sell inline-flex items-center gap-1.5 rounded-md border px-4 py-2 text-sm font-semibold transition hover:bg-[#fa4e1c] hover:text-white" style="border-color:#fa4e1c;color:#fa4e1c;">Start selling <span class="hm-arrow">&rarr;</span></a>
        </section>
    @endguest
</div>
<script>
// Banner carousel: auto-advances every 6s (the active dot fills up to show time left).
// Hover, focus or a hidden tab pauses it; reduced-motion users get no autoplay.
(function () {
    var car = document.querySelector('[data-carousel]');
    if (!car) return;
    var slides = car.querySelectorAll('[data-slide]');
    var dots = car.querySelectorAll('[data-car-dot]');
    var current = 0, swiped = false;
    var DUR = 6000, remaining = DUR, startedAt = 0, timer = null, holds = 0;
    var auto = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function schedule() {
        clearTimeout(timer);
        if (!auto || holds > 0 || document.hidden) return;
        startedAt = Date.now();
        timer = setTimeout(function () { go(current + 1); }, remaining);
    }
    function pause() {
        holds++;
        car.classList.add('is-paused');
        if (timer) { clearTimeout(timer); timer = null; remaining = Math.max(0, remaining - (Date.now() - startedAt)); }
    }
    function resume() {
        holds = Math.max(0, holds - 1);
        if (holds === 0) { car.classList.remove('is-paused'); schedule(); }
    }

    function go(n) {
        n = (n + slides.length) % slides.length;
        if (n === current) return;
        slides[current].classList.remove('is-active');
        slides[current].setAttribute('aria-hidden', 'true');
        slides[current].tabIndex = -1;
        dots[current].classList.remove('is-active');
        current = n;
        slides[n].classList.add('is-active');
        slides[n].removeAttribute('aria-hidden');
        slides[n].removeAttribute('tabindex');
        dots[n].classList.add('is-active');
        remaining = DUR;
        schedule();
    }

    dots.forEach(function (d) {
        d.addEventListener('click', function () { go(+d.dataset.carDot); });
    });
    car.querySelector('[data-car-prev]').addEventListener('click', function () { go(current - 1); });
    car.querySelector('[data-car-next]').addEventListener('click', function () { go(current + 1); });

    car.addEventListener('mouseenter', pause);
    car.addEventListener('mouseleave', resume);
    car.addEventListener('focusin', pause);
    car.addEventListener('focusout', resume);
    document.addEventListener('visibilitychange', function () { document.hidden ? pause() : resume(); });
    car.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') go(current - 1);
        if (e.key === 'ArrowRight') go(current + 1);
    });

    // Touch swipe
    var startX = null;
    car.addEventListener('pointerdown', function (e) { startX = e.clientX; swiped = false; });
    car.addEventListener('pointerup', function (e) {
        if (startX === null) return;
        var dx = e.clientX - startX;
        if (Math.abs(dx) > 40) { swiped = true; go(current + (dx < 0 ? 1 : -1)); }
        startX = null;
    });
    car.addEventListener('click', function (e) { if (swiped) { e.preventDefault(); swiped = false; } }, true);

    schedule();
})();
// Scroll reveal: sections fade up as they enter the viewport; their grid items cascade in.
(function () {
    if (!('IntersectionObserver' in window)) return;
    document.documentElement.classList.add('js-rv');
    document.querySelectorAll('[data-rv-items]').forEach(function (grid) {
        Array.prototype.forEach.call(grid.children, function (el, i) { el.style.setProperty('--i', i); });
    });
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    document.querySelectorAll('[data-rv]').forEach(function (el) { io.observe(el); });
})();
</script>
</x-layout>
