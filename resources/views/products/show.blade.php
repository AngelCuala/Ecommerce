<x-layout :title="$product->title . ' — ALVY'">
@php
    $inStock = $product->inStock();
    $hasDiscount = $product->hasDiscount() && $product->effective_price < $product->price;
    $pct = $hasDiscount && $product->price > 0 ? (int) round(100 - ($product->effective_price / $product->price * 100)) : 0;
    $maxQty = max(1, min($product->totalStock(), $allowance ?? PHP_INT_MAX));
    $variantData = $product->variations->map(fn ($v) => [
        'id' => $v->id, 'name' => $v->name,
        'parts' => array_map('trim', explode('/', $v->name)),
        'price' => $v->price > 0 ? $product->priceFor((float) $v->price) : $product->effective_price,
        'base'  => $v->price > 0 ? (float) $v->price : (float) $product->price,
        'stock' => (int) $v->stock,
    ])->values();
    $groups = $product->variationGroups();
    $groupLabels = $product->variationGroupLabels();

    $allImages = collect();
    if ($product->image) {
        $allImages->push((object) ['url' => asset('storage/' . $product->image), 'label' => 'Cover']);
    }
    foreach ($product->images as $img) {
        $allImages->push((object) ['url' => $img->url(), 'label' => $img->label ?? '']);
    }
    $mainImage = $allImages->first()->url ?? ($product->primaryImage ?? 'https://placehold.co/700x700/f5f5f5/999?text=' . urlencode($product->title));

    $details = array_filter([
        'Product ID'  => $product->product_code ?? null,
        'Category'    => $product->category->name ?? null,
        'Subcategory' => $product->subcategory ?? null,
        'Brand'       => $product->brand ?? $product->author ?? null,
        'SKU'         => $product->sku ?? null,
        // Legacy book-specific fields (only shown when present)
        'Format'      => $product->format ?? null,
        'Language'    => $product->language ?? null,
        'Publisher'   => $product->publisher ?? null,
        'Year'        => $product->publication_year ?? null,
        'ISBN'        => $product->isbn ?? null,
        'Edition'     => $product->edition ?? null,
    ], fn ($v) => ! is_null($v) && $v !== '');
    // Category-specific specs stored on the product.
    $specs = is_array($product->specs) ? array_filter($product->specs, fn ($v) => $v !== null && $v !== '') : [];
@endphp
<style>
    .pd-card { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; }
    .pd-thumb { overflow: hidden; border-radius: .5rem; border: 1.5px solid #ECE7E3; background: #fff; transition: border-color .15s; }
    .pd-thumb:hover { border-color: #D6CFC9; }
    .pd-thumb.is-active { border-color: #fa4e1c; }
    .pd-qty button { width: 2.5rem; height: 2.75rem; color: #57534E; transition: background .12s; }
    .pd-qty button:hover { background: #F5F2EF; }
    .pd-qty input::-webkit-outer-spin-button, .pd-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .pd-qty input { -moz-appearance: textfield; }
    .pd-btn { display: inline-flex; flex: 1; align-items: center; justify-content: center; gap: .5rem; height: 2.75rem; border-radius: .5rem; font-size: .875rem; font-weight: 600; transition: background .15s, transform .1s; }
    .pd-btn:active:not(:disabled) { transform: scale(.98); }
    .pd-btn:disabled { opacity: .5; cursor: not-allowed; }
    .pd-btn-line { border: 1.5px solid #fa4e1c; color: #fa4e1c; background: #fff; }
    .pd-btn-line:hover:not(:disabled) { background: #FFF5F1; }
    .pd-btn-solid { background: #fa4e1c; color: #fff; }
    .pd-btn-solid:hover:not(:disabled) { background: #e2400f; }
    .variation-btn { border: 1px solid #E7E5E4; border-radius: .5rem; padding: .45rem .8rem; font-size: .875rem; color: #44403C; background: #fff; transition: border-color .12s, color .12s; }
    .variation-btn:hover:not(:disabled) { border-color: #fa4e1c; }
    .variation-btn.is-active { border-color: #fa4e1c; background: #FFF5F1; color: #fa4e1c; }
    .variation-btn:disabled { opacity: .45; cursor: not-allowed; }
    #main-img { transition: opacity .2s ease; }
</style>

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <nav class="mb-5 flex flex-wrap items-center gap-1.5 text-xs" style="color:#8a7f78;" aria-label="Breadcrumb">
        <a href="{{ route('home') }}" class="hover:underline">Home</a>
        <span>/</span>
        @if ($product->category)
            @php $catSlug = collect(\App\Http\Controllers\CategoryPageController::catalog())->firstWhere('name', $product->category->name)['slug'] ?? null; @endphp
            @if ($catSlug)<a href="{{ route('categories.show', $catSlug) }}" class="hover:underline">{{ $product->category->name }}</a>@else<span>{{ $product->category->name }}</span>@endif
            <span>/</span>
        @endif
        <span class="truncate" style="color:#222;">{{ $product->title }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_440px] lg:gap-10">

        {{-- Gallery --}}
        <div class="lg:sticky lg:top-24 lg:self-start">
            <div class="pd-card relative aspect-square overflow-hidden">
                @if ($hasDiscount)
                    <span class="absolute left-3 top-3 z-10 rounded px-2 py-0.5 text-xs font-bold" style="background:#FFF1EB;color:#E0431A;">-{{ $pct }}%</span>
                @endif
                <img id="main-img" src="{{ $mainImage }}" alt="{{ $product->title }}"
                     class="absolute inset-0 h-full w-full object-contain p-6">
            </div>

            @if ($allImages->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-6">
                    @foreach ($allImages as $i => $img)
                        <button type="button" class="pd-thumb {{ $i === 0 ? 'is-active' : '' }}" data-src="{{ $img->url }}"
                                aria-label="Show photo {{ $i + 1 }}">
                            <img src="{{ $img->url }}" alt="" class="aspect-square w-full object-contain p-1" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="min-w-0">
            @if ($product->category)
                <p class="text-xs font-semibold uppercase tracking-wider" style="color:#a39890;">{{ $product->category->name }}</p>
            @endif
            <h1 class="mt-1 font-display text-2xl font-extrabold leading-tight sm:text-3xl" style="color:#222;">{{ $product->title }}</h1>

            @php $byline = $product->brand ?: $product->author; @endphp
            <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm" style="color:#8a7f78;">
                @if ($byline)<span>by <span class="font-medium" style="color:#44403C;">{{ $byline }}</span></span>@endif
                @if ($byline && ($sold > 0 || !empty($product->rating_avg)))<span>&middot;</span>@endif
                @if (!empty($product->rating_avg))
                    <span class="flex items-center gap-1">
                        <svg class="h-3.5 w-3.5" fill="#F59E0B" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1 1 5.8L10 14.85l-5.21 2.67 1-5.8-4.21-4.1 5.82-.85L10 1.5z"/></svg>
                        {{ number_format($product->rating_avg, 1) }} ({{ $product->rating_count ?? 0 }})
                    </span>
                    @if ($sold > 0)<span>&middot;</span>@endif
                @endif
                @if ($sold > 0)<span>{{ number_format($sold) }} sold</span>@endif
            </div>

            {{-- Price --}}
            <div class="mt-5 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span id="pd-price" class="font-display text-3xl font-extrabold" style="color:#fa4e1c;">₱{{ number_format($product->effective_price, 2) }}</span>
                @if ($hasDiscount)
                    <span id="pd-base" class="text-base line-through" style="color:#a8a29e;">₱{{ number_format($product->price, 2) }}</span>
                    <span id="pd-pct" class="text-sm font-semibold" style="color:#E0431A;">-{{ $pct }}%</span>
                @endif
            </div>
            @if ($flash)
                <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg px-3 py-2 text-sm text-white" style="background:linear-gradient(90deg,#fa4e1c,#f97316);">
                    <strong>⚡ FLASH SALE</strong>
                    <span>Ends in <span id="pd-countdown" data-ends="{{ $flash->ends_at->toIso8601String() }}">{{ $flash->ends_at->diffForHumans(null, true) }}</span></span>
                    <span class="ml-auto text-xs">{{ $flash->remaining() }} of {{ $flash->quantity }} left at this price · then ₱{{ number_format($product->regular_price, 2) }}</span>
                </div>
            @elseif ($upcoming)
                <div class="mt-3 flex flex-wrap items-center gap-3 rounded-lg border px-3 py-2 text-sm" style="border-color:#FDBA9B;background:#FFF8F4;color:#9A3412;">
                    <span>⚡ Flash sale <strong>{{ $upcoming->starts_at->format('M j, g:i A') }}</strong>: ₱{{ number_format($upcoming->flash_price, 2) }} for the first {{ $upcoming->quantity }}</span>
                    @auth
                        <form action="{{ route('flash-sales.remind', $upcoming) }}" method="POST" class="ml-auto">@csrf
                            <button class="rounded-md px-3 py-1 text-xs font-semibold {{ $reminded ? 'border' : 'text-white' }}" style="{{ $reminded ? 'border-color:#fa4e1c;color:#fa4e1c;' : 'background:#fa4e1c;' }}">{{ $reminded ? '✓ Reminder set' : 'Remind me' }}</button>
                        </form>
                    @endauth
                </div>
            @endif
            @if ($hasDiscount && $product->sale_ends_at && ! $flash)
                <p class="mt-2 inline-flex items-center gap-2 rounded-md px-2.5 py-1 text-xs font-semibold" style="background:#FFF1EB;color:#C2410C;">
                    Sale ends in <span id="pd-countdown" data-ends="{{ $product->sale_ends_at->toIso8601String() }}">{{ $product->sale_ends_at->diffForHumans(null, true) }}</span>
                </p>
            @endif

            {{-- Stock --}}
            <p class="mt-2 flex items-center gap-2 text-sm">
                <span class="h-2 w-2 rounded-full" style="background:{{ $inStock ? '#16A34A' : '#DC2626' }};"></span>
                @if ($inStock)
                    <span style="color:#44403C;">In stock</span>
                    <span style="color:#a39890;">&middot; <span id="pd-stock">{{ $product->totalStock() }}</span> available</span>
                    @if ($product->purchase_limit)
                        <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold" style="background:#EFF6FF;color:#1D4ED8;">Limit {{ $product->purchase_limit }} per buyer{{ $allowance !== null && $allowance < $product->purchase_limit ? ' · ' . $allowance . ' left for you' : '' }}</span>
                    @endif
                @else
                    <span style="color:#B91C1C;">Out of stock</span>
                @endif
            </p>

            <div class="my-5 h-px" style="background:#ECE7E3;"></div>

            @if ($vouchers->isNotEmpty())
                <div class="mb-5">
                    <p class="mb-2 text-sm font-semibold" style="color:#222;">Store vouchers</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($vouchers as $v)
                            <div class="flex items-center gap-2 rounded-md border border-dashed px-2.5 py-1.5 text-xs" style="border-color:#FDBA9B;background:#FFF8F4;">
                                <span><strong style="color:#C2410C;">{{ $v->discountLabel() }}</strong>@if ($v->min_spend > 0) <span style="color:#8a7f78;">min ₱{{ number_format($v->min_spend, 0) }}</span>@endif</span>
                                @if (in_array($v->id, $claimed))
                                    <span class="font-semibold" style="color:#059669;">Claimed</span>
                                @else
                                    <form action="{{ route('vouchers.claim', $v) }}" method="POST">@csrf
                                        <button class="rounded px-2 py-0.5 font-semibold text-white" style="background:#fa4e1c;">Claim</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Purchase --}}
            <form action="{{ route('cart.store', $product->id) }}" method="POST" id="pd-form">
                @csrf

                @if ($product->variations->isNotEmpty())
                    <input type="hidden" name="variation_id" id="pd-variation" value="{{ old('variation_id') }}">
                    @foreach ($groups as $gi => $values)
                        <div class="mb-4">
                            <p class="mb-2 text-sm font-semibold" style="color:#222;">{{ $groupLabels[$gi] }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($values as $value)
                                    <button type="button" class="variation-btn" data-group="{{ $gi }}" data-value="{{ $value }}">{{ $value }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p id="pd-variation-msg" class="-mt-2 mb-3 hidden text-xs font-semibold" style="color:#B91C1C;">Please select a variation.</p>
                @endif

                <p class="mb-2 text-sm font-semibold" style="color:#222;">Quantity</p>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="pd-qty flex items-center overflow-hidden rounded-lg border bg-white" style="border-color:#E7E5E4;">
                        <button type="button" aria-label="Decrease" data-qty="-1">&minus;</button>
                        <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="{{ $maxQty }}" step="1" id="pd-qty" aria-label="Quantity"
                               class="h-11 w-12 border-0 bg-transparent text-center text-sm font-semibold focus:outline-none" style="color:#222;">
                        <button type="button" aria-label="Increase" data-qty="1">+</button>
                    </div>
                    <button type="button" id="pd-wish" onclick="document.getElementById('pd-wish-form').submit()"
                            class="flex h-11 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium" style="border-color:#E7E5E4;color:{{ $wished ? '#fa4e1c' : '#57534E' }};"
                            aria-pressed="{{ $wished ? 'true' : 'false' }}">
                        <svg class="h-4 w-4" fill="{{ $wished ? '#fa4e1c' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 000-7.78z"/></svg>
                        {{ $wished ? 'In wishlist' : 'Wishlist' }}
                    </button>
                </div>
                <p id="pd-qty-msg" class="mt-1 hidden text-xs font-semibold" style="color:#B91C1C;"></p>

                <div class="mt-4 flex gap-3">
                    <button type="submit" name="action" value="cart" class="pd-btn pd-btn-line" {{ $inStock ? '' : 'disabled' }}>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h1.6l1.7 10.4a2 2 0 002 1.6h8.6a2 2 0 002-1.6L20.4 7H6"/>
                            <circle cx="9.5" cy="20" r="1.3" fill="currentColor" stroke="none"/><circle cx="17" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                        </svg>
                        Add to cart
                    </button>
                    <button type="submit" name="action" value="buy_now" class="pd-btn pd-btn-solid" {{ $inStock ? '' : 'disabled' }}>
                        Buy now
                    </button>
                </div>
            </form>
            <form id="pd-wish-form" action="{{ route('wishlist.toggle', $product->id) }}" method="POST" class="hidden">@csrf
                <input type="hidden" name="state" value="{{ $wished ? 'off' : 'on' }}">
            </form>

            {{-- Seller --}}
            @if ($product->seller)
                <div class="pd-card mt-6 flex flex-wrap items-center gap-3 p-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold" style="background:#F5F2EF;color:#57534E;">
                        {{ strtoupper(substr($product->seller->storeName(), 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs" style="color:#a39890;">Sold by</p>
                        <a href="{{ route('stores.show', $product->seller->id) }}" class="truncate text-sm font-semibold hover:underline" style="color:#222;">{{ $product->seller->storeName() }}</a>
                    </div>
                    @auth
                        <form action="{{ route('messages.new') }}" method="POST">@csrf
                            <input type="hidden" name="receiver_id" value="{{ $product->seller->id }}">
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button class="rounded-md border px-3 py-1.5 text-xs font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">Chat</button>
                        </form>
                    @endauth
                    <a href="{{ route('stores.show', $product->seller->id) }}" class="rounded-md border px-3 py-1.5 text-xs font-semibold" style="border-color:#E7E5E4;color:#57534E;">View store</a>
                </div>
            @endif

            {{-- Description --}}
            @if ($product->description)
                <section class="mt-6">
                    <h2 class="text-sm font-semibold" style="color:#222;">Description</h2>
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed" style="color:#57534E;">{{ $product->description }}</p>
                </section>
            @endif

            {{-- Details --}}
            @if (count($details) || count($specs))
                <section class="mt-6">
                    <h2 class="text-sm font-semibold" style="color:#222;">Product details</h2>
                    <dl class="pd-card mt-2 divide-y text-sm" style="--tw-divide-opacity:1;">
                        @foreach (array_merge($details, $specs) as $label => $val)
                            <div class="flex justify-between gap-4 px-4 py-2.5" style="border-color:#F2EEEA;">
                                <dt style="color:#8a7f78;">{{ $label }}</dt>
                                <dd class="text-right font-medium" style="color:#222;">{{ $val }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <p class="mt-5 text-xs" style="color:#a39890;">
                Questions about returns? See our
                <a href="{{ route('policies.show', 'return_refund_policy') }}" class="underline hover:text-[#fa4e1c]">return &amp; refund policy</a>.
            </p>
        </div>
    </div>

    {{-- Ratings & reviews --}}
    <section class="mt-14" id="reviews">
        <h2 class="font-display text-lg font-extrabold" style="color:#222;">Ratings &amp; reviews</h2>
        @if ($product->rating_count > 0)
            <div class="pd-card mt-3 flex flex-wrap items-center gap-6 p-5">
                <div class="text-center">
                    <p class="font-display text-4xl font-extrabold" style="color:#fa4e1c;">{{ number_format($product->rating_avg, 1) }}</p>
                    <p class="text-xs" style="color:#8a7f78;">out of 5 · {{ $product->rating_count }} {{ \Illuminate\Support\Str::plural('review', $product->rating_count) }}</p>
                </div>
                <div class="min-w-[12rem] flex-1 space-y-1">
                    @for ($star = 5; $star >= 1; $star--)
                        @php $n = (int) ($ratingBreakdown[$star] ?? 0); @endphp
                        <div class="flex items-center gap-2 text-xs" style="color:#57534E;">
                            <span class="w-8">{{ $star }} ★</span>
                            <span class="h-1.5 flex-1 overflow-hidden rounded-full" style="background:#F2EEEA;"><span class="block h-full" style="width:{{ round($n / $product->rating_count * 100) }}%;background:#F59E0B;"></span></span>
                            <span class="w-6 text-right">{{ $n }}</span>
                        </div>
                    @endfor
                </div>
            </div>
            <div class="mt-3 space-y-3">
                @foreach ($reviews as $review)
                    @php $who = $review->user->name ?? 'Buyer'; @endphp
                    <article class="pd-card p-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold" style="color:#222;">{{ mb_substr($who, 0, 1) . str_repeat('*', max(2, mb_strlen($who) - 2)) . mb_substr($who, -1) }}</span>
                            <span style="color:#F59E0B;">{{ str_repeat('★', $review->rating) }}<span style="color:#E7E5E4;">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            <span class="text-xs" style="color:#a39890;">{{ $review->created_at->format('M j, Y') }}@if ($review->variation_name) · {{ $review->variation_name }}@endif @if ($review->edit_count) · edited @endif</span>
                        </div>
                        @if ($review->comment)<p class="mt-2 whitespace-pre-line text-sm" style="color:#44403C;">{{ $review->comment }}</p>@endif
                        @if ($review->photo_path)
                            <a href="{{ asset('storage/' . $review->photo_path) }}" target="_blank"><img src="{{ asset('storage/' . $review->photo_path) }}" alt="Review photo" class="mt-2 h-24 w-24 rounded-md border object-cover" style="border-color:#ECE7E3;" loading="lazy"></a>
                        @endif
                        @if ($review->seller_reply)
                            <div class="mt-3 rounded-md px-3 py-2 text-sm" style="background:#F5F2EF;color:#44403C;"><strong>Seller:</strong> {{ $review->seller_reply }}</div>
                        @endif
                    </article>
                @endforeach
                <div>{{ $reviews->fragment('reviews')->links() }}</div>
            </div>
        @else
            <p class="mt-2 text-sm" style="color:#8a7f78;">No reviews yet. Buyers can rate this item after their order is completed.</p>
        @endif
    </section>

    {{-- Related --}}
    @if ($related->count())
        <section class="mt-14">
            <div class="mb-3 flex items-baseline justify-between">
                <h2 class="font-display text-lg font-extrabold" style="color:#222;">You may also like</h2>
                @if (!empty($catSlug))
                    <a href="{{ route('categories.show', $catSlug) }}" class="text-sm font-semibold hover:underline" style="color:#fa4e1c;">See more</a>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($related as $r)
                    <x-product-card :product="$r" />
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
window.PD = { variants: @json($variantData), groups: {{ count($groups) }}, maxQty: {{ $maxQty }}, allowance: @json($allowance) };
</script>
<script src="{{ asset('js/product-page.js') }}?v=3"></script>
<script>
// Gallery thumbnails and quantity stepper
(function () {
    var main = document.getElementById('main-img');
    document.querySelectorAll('.pd-thumb').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pd-thumb').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            main.style.opacity = 0;
            setTimeout(function () { main.src = btn.dataset.src; main.style.opacity = 1; }, 150);
        });
    });
    document.querySelectorAll('[data-qty]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            var v = (+input.value || 1) + (+btn.dataset.qty);
            input.value = Math.min(Math.max(v, +input.min || 1), +input.max || v);
        });
    });
})();
</script>
</x-layout>
