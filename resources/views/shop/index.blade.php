<x-layout :title="request('search') ? '&quot;'.request('search').'&quot; — ALVY' : 'Search — ALVY'">

<div style="background:#F5F5F5;min-height:80vh;">

    {{-- ── Search results header bar (Shopee-style orange strip) ── --}}
    <div style="background:#fff;border-bottom:1px solid #EFEFEF;">
        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
            <p class="text-sm" style="color:#757575;">
                Search results for
                <strong style="color:#fa4e1c;">"{{ request('search') }}"</strong>
                —
                <span style="color:#222;">{{ $products->count() }} item{{ $products->count() !== 1 ? 's' : '' }} found</span>
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">

        {{-- ── Sort bar (Shopee-style) ── --}}
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-sm px-4 py-3"
             style="background:#FAFAFA;border:1px solid #EFEFEF;">
            <span class="text-sm font-medium" style="color:#555555;">Sort By</span>

            <form method="GET" class="flex flex-wrap items-center gap-2" id="sort-form">
                <input type="hidden" name="search" value="{{ request('search') }}">

                @foreach ([
                    'newest'     => 'Relevance',
                    'price_low'  => 'Price: Low to High',
                    'price_high' => 'Price: High to Low',
                ] as $val => $label)
                    <button type="submit" name="sort" value="{{ $val }}"
                            class="rounded px-4 py-1.5 text-sm transition"
                            style="{{ request('sort','newest') === $val
                                ? 'background:#fa4e1c;color:#fff;'
                                : 'background:#fff;color:#555555;border:1px solid #E0E0E0;' }}">
                        {{ $label }}
                    </button>
                @endforeach

                {{-- Price range --}}
                <div class="ml-auto flex items-center gap-2">
                    <span class="text-xs" style="color:#999;">Price</span>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs" style="color:#999;">₱</span>
                        <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min"
                               class="w-20 rounded border py-1.5 pl-5 pr-2 text-xs focus:outline-none"
                               style="border-color:#E0E0E0;">
                    </div>
                    <span class="text-xs" style="color:#999;">–</span>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs" style="color:#999;">₱</span>
                        <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max"
                               class="w-20 rounded border py-1.5 pl-5 pr-2 text-xs focus:outline-none"
                               style="border-color:#E0E0E0;">
                    </div>
                    <button type="submit"
                            class="rounded px-3 py-1.5 text-xs font-semibold transition"
                            style="background:#fa4e1c;color:#fff;">Apply</button>
                </div>
            </form>
        </div>

        {{-- ── Product grid ── --}}
        @if ($products->isEmpty())
            <div class="flex flex-col items-center gap-5 py-24 text-center">
                <span class="text-6xl">🔍</span>
                <div>
                    <p class="text-xl font-bold" style="color:#222222;">No results for "{{ request('search') }}"</p>
                    <p class="mt-2 text-sm" style="color:#999999;">Try checking your spelling or use more general terms.</p>
                </div>
                <a href="{{ route('categories.page') }}"
                   class="mt-2 rounded-sm px-8 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                   style="background:#002b4d;">Browse Categories</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($products as $product)
                    <a href="{{ route('products.show', $product->slug) }}"
                       class="group flex flex-col overflow-hidden rounded-sm bg-white transition hover:shadow-md"
                       style="border:1px solid #EFEFEF;">

                        {{-- Product image --}}
                        <div class="relative aspect-square w-full overflow-hidden" style="background:#F5F5F5;">
                            <img src="{{ $product->primaryImage ?? 'https://placehold.co/200x200/F5F5F5/CCCCCC?text=No+Image' }}"
                                 alt="{{ $product->title }}"
                                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">

                            @if ($product->hasDiscount())
                                <span class="absolute left-0 top-2 rounded-r-full px-2 py-0.5 text-[10px] font-bold text-white"
                                      style="background:#002b4d;">
                                    -{{ $product->discount_percent }}%
                                </span>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex flex-1 flex-col p-2.5">
                            <p class="line-clamp-2 text-xs leading-snug" style="color:#222222;">{{ $product->title }}</p>

                            <div class="mt-auto pt-2">
                                <p class="text-sm font-bold" style="color:#fa4e1c;">
                                    ₱{{ number_format($product->effective_price, 2) }}
                                </p>
                                @if ($product->hasDiscount())
                                    <p class="text-[10px] line-through" style="color:#BBBBBB;">
                                        ₱{{ number_format($product->price, 2) }}
                                    </p>
                                @endif
                                <p class="mt-1 text-[10px]" style="color:#999999;">
                                    {{ $product->stock > 0 ? number_format($product->stock).' in stock' : 'Out of stock' }}
                                </p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>

</x-layout>
