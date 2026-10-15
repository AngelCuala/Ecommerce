@props(['product'])

@php
    // Products have a base price plus an optional sale price or discount %.
    $isProduct = $product instanceof \App\Models\Product;
    $price = $isProduct ? $product->effective_price : (float) $product->price;
    $hasDiscount = $isProduct && $product->hasDiscount() && $price < $product->price;
    $discountPercent = $hasDiscount ? (int) round(($product->price - $price) / $product->price * 100) : 0;
    $outOfStock = ($product->stock ?? 1) <= 0;
    $sold = (int) ($product->sold ?? 0);
    $url = isset($product->slug) ? route('products.show', $product->slug) : '#';
    $categoryName = $isProduct && $product->relationLoaded('category') ? $product->category?->name : null;
    // One query per page for the hearts, not one per card.
    $wishedIds = auth()->check() ? once(fn () => \App\Models\WishlistItem::where('user_id', auth()->id())->pluck('product_id')->all()) : [];
    $wished = in_array($product->id, $wishedIds);
    // Stores on vacation and option price ranges: one query per page, not one per card.
    $vacationSellers = once(fn () => \App\Models\SellerApplication::where('is_on_vacation', true)->pluck('user_id')->all());
    $onVacation = $isProduct && in_array($product->seller_id, $vacationSellers);
    $variantPrices = once(fn () => \App\Models\ProductVariation::where('price', '>', 0)->groupBy('product_id')
        ->selectRaw('product_id, MIN(price) as min_price, MAX(price) as max_price')->get()->keyBy('product_id'));
    $range = null;
    if ($isProduct && ($vp = $variantPrices->get($product->id)) && (float) $vp->min_price != (float) $vp->max_price && ! $product->runningFlashSale()) {
        $range = [$product->priceFor((float) $vp->min_price), $product->priceFor((float) $vp->max_price)];
    }
@endphp

@once
<style>
    .pc-card { position: relative; display: flex; flex-direction: column; overflow: hidden; border-radius: .625rem; background: #fff; border: 1px solid #ECE7E3; transition: border-color .2s, box-shadow .25s, transform .25s cubic-bezier(.2,.7,.2,1); }
    .pc-card:hover { border-color: #D6CFC9; box-shadow: 0 10px 24px rgba(28,25,23,.09); transform: translateY(-3px); }
    .pc-media { position: relative; display: block; aspect-ratio: 1 / 1; overflow: hidden; background: #fff; border-bottom: 1px solid #F2EEEA; }
    .pc-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; padding: .75rem; transition: transform .3s ease; }
    .pc-card:hover .pc-media img { transform: scale(1.06); }
    .pc-tag { position: absolute; left: .5rem; top: .5rem; z-index: 10; border-radius: .25rem; background: #FFF1EB; color: #E0431A; padding: .1rem .4rem; font-size: .6875rem; font-weight: 700; }
    .wishlist-btn { color: #a8a29e; }
    .wishlist-btn:hover, .wishlist-btn.is-wished { color: #fa4e1c; }
    .wishlist-btn { transition: color .15s, transform .15s; }
    .wishlist-btn:active { transform: scale(.85); }
    .wishlist-btn.is-wished svg { fill: #fa4e1c; animation: pc-heart .45s cubic-bezier(.2,.7,.2,1.4); }
    @keyframes pc-heart { 0% { transform: scale(.6); } 60% { transform: scale(1.25); } 100% { transform: scale(1); } }
    .pc-cart { display: flex; height: 2rem; width: 2rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: .375rem; border: 1px solid #E7E5E4; color: #57534E; transition: background .15s, border-color .15s, color .15s; }
    .pc-cart { transition: background .15s, border-color .15s, color .15s, transform .12s; }
    .pc-cart:hover { background: #fa4e1c; border-color: #fa4e1c; color: #fff; }
    .pc-cart:active { transform: scale(.88); }
    .pc-card:hover .pc-tag { animation: pc-nudge .4s ease; }
    @keyframes pc-nudge { 50% { transform: translateY(-2px); } }
    @media (prefers-reduced-motion: reduce) { .pc-card, .pc-card:hover, .pc-card:hover .pc-media img { transform: none; } .wishlist-btn.is-wished svg, .pc-card:hover .pc-tag { animation: none; } }
</style>
@endonce

<div class="pc-card group">

    {{-- Wishlist (signed-out buyers are sent to sign in) --}}
    <form action="{{ route('wishlist.toggle', $product->id) }}" method="POST" class="absolute right-1.5 top-1.5 z-20">
        @csrf
        <input type="hidden" name="state" value="{{ $wished ? 'off' : 'on' }}">
        <button type="submit"
                class="wishlist-btn {{ $wished ? 'is-wished' : '' }} flex h-8 w-8 items-center justify-center rounded-full bg-white/90 transition"
                title="{{ $wished ? 'Remove from wishlist' : 'Add to wishlist' }}" aria-pressed="{{ $wished ? 'true' : 'false' }}"
                aria-label="{{ $wished ? 'Remove' : 'Add' }} {{ $product->title }} {{ $wished ? 'from' : 'to' }} wishlist"
                onclick="if (this.dataset.busy) return false; this.dataset.busy = 1;">
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
            </svg>
        </button>
    </form>

    {{-- Image --}}
    <a href="{{ $url }}" class="pc-media">
        @if ($hasDiscount && ! $outOfStock)
            <span class="pc-tag">-{{ $discountPercent }}%</span>
        @endif
        <img src="{{ !empty($product->image) ? asset('storage/' . $product->image) : 'https://placehold.co/400x400/f5f5f5/999?text=' . urlencode($product->title) }}"
             alt="{{ $product->title }}" loading="lazy"
             @class(['opacity-50' => $outOfStock])>
    </a>

    <div class="flex flex-1 flex-col p-3">
        @if ($categoryName)
            <p class="truncate text-[11px]" style="color:#a39890;">{{ $categoryName }}</p>
        @endif

        <a href="{{ $url }}" class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm leading-5 transition-colors hover:text-[#fa4e1c]" style="color:#222;">
            {{ $product->title }}
        </a>

        @if (!empty($product->rating) || $sold > 0)
            <div class="mt-1 flex items-center gap-1.5 text-[11px]" style="color:#8a7f78;">
                @if (!empty($product->rating))
                    <span class="flex items-center gap-0.5">
                        <svg class="h-3 w-3" fill="#F59E0B" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1 1 5.8L10 14.85l-5.21 2.67 1-5.8-4.21-4.1 5.82-.85L10 1.5z"/></svg>
                        {{ number_format($product->rating, 1) }}
                    </span>
                @endif
                @if (!empty($product->rating) && $sold > 0)<span>&middot;</span>@endif
                @if ($sold > 0)<span>{{ number_format($sold) }} sold</span>@endif
            </div>
        @endif

        {{-- Price + cart --}}
        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
            <div class="min-w-0">
                @if ($range)
                    <p class="text-sm font-bold leading-tight" style="color:#fa4e1c;">₱{{ number_format($range[0], 0) }} – ₱{{ number_format($range[1], 0) }}</p>
                @else
                    <p class="text-base font-bold leading-tight" style="color:#fa4e1c;">₱{{ number_format($price, 2) }}</p>
                @endif
                @if ($hasDiscount && ! $range)
                    <p class="text-[11px] line-through" style="color:#a8a29e;">₱{{ number_format($product->price, 2) }}</p>
                @endif
            </div>

            @if ($onVacation)
                <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold" style="background:#FFFBEB;color:#B45309;">On vacation</span>
            @elseif ($outOfStock)
                <span class="text-[11px] font-medium" style="color:#8a7f78;">Sold out</span>
            @else
                <form action="{{ route('cart.store', $product->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="pc-cart" title="Add to cart" aria-label="Add {{ $product->title }} to cart">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
