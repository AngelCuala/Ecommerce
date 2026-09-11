@props(['product'])

@php
    $hasDiscount = isset($product->original_price) && $product->original_price > $product->price;
    $discountPercent = $hasDiscount
        ? round((($product->original_price - $product->price) / $product->original_price) * 100)
        : 0;
    $outOfStock = ($product->stock ?? 1) <= 0;
@endphp

<div class="group relative flex flex-col overflow-hidden rounded-sm bg-white border border-gray-100 shadow-sm transition duration-200 hover:shadow-md hover:-translate-y-0.5">

    {{-- Discount badge (top-left, Shopee ribbon style) --}}
    @if ($hasDiscount && !$outOfStock)
        <span class="absolute left-0 top-0 z-10 px-2 py-1 text-[11px] font-bold text-white"
              style="background:#002b4d;">
            -{{ $discountPercent }}%
        </span>
    @endif

    {{-- Out of stock overlay --}}
    @if ($outOfStock)
        <div class="absolute inset-0 z-10 flex items-center justify-center bg-white/60">
            <span class="rounded-sm px-3 py-1 text-xs font-bold uppercase tracking-wide text-white"
                  style="background:rgba(0,0,0,.55);">
                Out of stock
            </span>
        </div>
    @endif

    {{-- Cover image --}}
    <a href="{{ isset($product->slug) ? route('products.show', $product->slug) : '#' }}"
       class="relative block aspect-square w-full overflow-hidden bg-gray-50">
        <img src="{{ !empty($product->image) ? asset('storage/' . $product->image) : 'https://placehold.co/400x400/f5f5f5/999?text=' . urlencode($product->title) }}"
             alt="{{ $product->title }}"
             loading="lazy"
             class="h-full w-full object-cover transition duration-300 ease-out group-hover:scale-[1.05]">
    </a>

    <div class="flex flex-1 flex-col gap-1 p-2.5">

        {{-- Title --}}
        <a href="{{ isset($product->slug) ? route('products.show', $product->slug) : '#' }}"
           class="text-[13px] leading-snug text-gray-800 line-clamp-2 min-h-[34px] transition-colors hover:text-[#fa4e1c]">
            {{ $product->title }}
        </a>

        {{-- Rating + Sold --}}
        <div class="flex items-center gap-1 text-[11px] text-gray-400">
            @if (!empty($product->rating))
                <span class="flex items-center gap-0.5" style="color:#fa4e1c;">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1 1 5.8L10 14.85l-5.21 2.67 1-5.8-4.21-4.1 5.82-.85L10 1.5z"/>
                    </svg>
                    {{ number_format($product->rating, 1) }}
                </span>
                <span>|</span>
            @endif
            <span>{{ number_format($product->sold ?? 0) }} sold</span>
        </div>

        {{-- Price row --}}
        <div class="mt-1 flex items-end justify-between">
            <div class="flex flex-col">
                <span class="text-base font-semibold" style="color:#fa4e1c;">
                    ₱{{ number_format($product->price, 2) }}
                </span>
                @if ($hasDiscount)
                    <span class="text-[11px] text-gray-400 line-through">
                        ₱{{ number_format($product->original_price, 2) }}
                    </span>
                @endif
            </div>

            @if (!$outOfStock)
                <form action="{{ route('cart.store', $product->id) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex h-7 w-7 items-center justify-center rounded-full text-white shadow transition duration-150 hover:brightness-110 active:scale-95"
                            style="background:#002b4d;"
                            title="Add to cart" aria-label="Add {{ $product->title }} to cart">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>