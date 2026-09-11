<x-layout :title="$product->title . ' — ALVY'">
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <nav class="mb-8 flex flex-wrap items-center gap-1.5 text-xs" style="color:rgba(107,76,59,.5);" aria-label="Breadcrumb">
        <a href="{{ route('home') }}" onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='rgba(107,76,59,.5)';">Home</a>
        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L10.168 10 7.23 6.29a.75.75 0 111.04-1.08l3.5 3.75a.75.75 0 010 1.08l-3.5 3.75a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
        <a href="{{ route('shop.index') }}" onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='rgba(107,76,59,.5)';">Shop</a>
        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L10.168 10 7.23 6.29a.75.75 0 111.04-1.08l3.5 3.75a.75.75 0 010 1.08l-3.5 3.75a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
        <span style="color:#1a4d6e;">{{ $product->title }}</span>
    </nav>

    <div class="grid gap-12 lg:grid-cols-[1fr_460px]">

        {{-- ══════════════════════════
             IMAGE GALLERY
        ══════════════════════════ --}}
        <div class="flex flex-col gap-4">
            {{-- Main image --}}
            <div class="overflow-hidden rounded-2xl shadow-soft" style="background:#F3EDE4;">
                <img id="main-img"
                     src="{{ $product->primaryImage ?? 'https://placehold.co/700x940/4A2C17/FAF7F0?text=' . urlencode($product->title) }}"
                     alt="{{ $product->title }}"
                     class="aspect-[3/4] w-full object-cover transition duration-300">
            </div>

            {{-- Thumbnails --}}
            @php
                $allImages = collect();
                if ($product->image) {
                    $allImages->push((object)['url' => asset('storage/'.$product->image), 'label' => 'Cover']);
                }
                foreach ($product->images as $img) {
                    $allImages->push((object)['url' => $img->url(), 'label' => $img->label ?? '']);
                }
            @endphp

            @if ($allImages->count() > 1)
                <div class="grid grid-cols-5 gap-2">
                    @foreach ($allImages as $i => $img)
                        <button type="button"
                                onclick="document.getElementById('main-img').src='{{ $img->url }}';
                                         document.querySelectorAll('.thumb-btn').forEach(b=>b.style.borderColor='#cfdce8');
                                         this.style.borderColor='#fa4e1c';"
                                class="thumb-btn overflow-hidden rounded-lg border-2 transition"
                                style="border-color:{{ $i === 0 ? '#fa4e1c' : '#cfdce8' }};"
                                title="{{ $img->label }}">
                            <img src="{{ $img->url }}"
                                 alt="{{ $img->label }}"
                                 class="aspect-square w-full object-cover">
                        </button>
                    @endforeach
                </div>
                @if ($allImages->count() > 0)
                    <p class="text-center text-xs" style="color:#6b90aa;">
                        {{ $allImages->count() }} photo{{ $allImages->count() > 1 ? 's' : '' }} · click to view
                    </p>
                @endif
            @endif
        </div>

        {{-- ══════════════════════════
             DETAILS PANEL
        ══════════════════════════ --}}
        <div class="flex flex-col">

            {{-- Category & format tags --}}
            <div class="flex flex-wrap gap-2">
                @if ($product->category)
                    <span class="rounded px-3 py-1 text-[11px] font-semibold uppercase tracking-wide"
                          style="background:rgba(250,78,28,.1);color:#fa4e1c;">
                        {{ $product->category->name }}
                    </span>
                @endif
                <span class="rounded px-3 py-1 text-[11px] font-semibold uppercase tracking-wide"
                      style="background:#F3EDE4;color:#1a4d6e;">
                    {{ $product->format ?? 'Paperback' }}
                </span>
                @if ($product->edition)
                    <span class="rounded px-3 py-1 text-[11px] font-semibold"
                          style="background:#F3EDE4;color:#1a4d6e;">
                        {{ $product->edition }} Edition
                    </span>
                @endif
            </div>

            <h1 class="mt-3 font-display text-4xl font-bold leading-tight" style="color:#002b4d;">
                {{ $product->title }}
            </h1>
            <p class="mt-1.5 text-sm" style="color:#1a4d6e;">
                by <strong>{{ $product->author }}</strong>
                @if ($product->publisher) · {{ $product->publisher }} @endif
                @if ($product->publication_year) · {{ $product->publication_year }} @endif
            </p>

            {{-- Rating row (Shopee-style, if you track ratings — placeholder shown only if available) --}}
            @if (isset($product->rating_avg) && $product->rating_avg)
                <div class="mt-2 flex items-center gap-2 text-sm">
                    <span style="color:#fa4e1c;">
                        {{ str_repeat('★', round($product->rating_avg)) }}{{ str_repeat('☆', 5 - round($product->rating_avg)) }}
                    </span>
                    <span style="color:#6b90aa;">{{ number_format($product->rating_avg, 1) }} · {{ $product->rating_count ?? 0 }} ratings</span>
                </div>
            @endif

            {{-- Price (Shopee-style: bold orange price block) --}}
            <div class="mt-6 rounded-xl px-4 py-3" style="background:#FFF6EE;">
                <span class="align-middle text-sm font-medium" style="color:#fa4e1c;">₱</span>
                <span class="font-display text-4xl font-bold align-middle" style="color:#fa4e1c;">
                    {{ number_format($product->price, 2) }}
                </span>
            </div>

            {{-- Stock / Availability --}}
            @php $inStock = $product->inStock(); @endphp
            <p class="mt-3 flex items-center gap-1.5 text-sm font-semibold"
               style="color:{{ $inStock ? '#059669' : '#DC2626' }};">
                @if ($inStock)
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                    In Stock — {{ $product->stock }} available
                @else
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M18 6 6 18M6 6l12 12"/></svg>
                    Out of Stock
                @endif
            </p>

            {{-- Description --}}
            @if ($product->description)
                <p class="mt-5 text-base leading-relaxed" style="color:#1a4d6e;">
                    {{ $product->description }}
                </p>
            @endif

            {{-- Add to cart / Buy now (Shopee dual-button pattern) --}}
            <form action="{{ route('cart.store', $product->id) }}" method="POST" class="mt-7">
                @csrf
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center overflow-hidden rounded-lg border" style="border-color:#cfdce8;background:#FBF7F2;">
                        <button type="button" aria-label="Decrease"
                                onclick="const i=this.nextElementSibling;if(+i.value>1)i.value=+i.value-1"
                                class="w-10 py-2.5 text-center transition" style="color:#1a4d6e;"
                                onmouseover="this.style.background='#EDE0D4';"
                                onmouseout="this.style.background='';">−</button>
                        <input type="number" name="quantity" value="1"
                               min="1" max="{{ $product->stock }}" aria-label="Quantity"
                               class="w-12 border-0 bg-transparent text-center text-sm font-medium focus:outline-none"
                               style="color:#002b4d;">
                        <button type="button" aria-label="Increase"
                                onclick="const i=this.previousElementSibling;if(+i.value<{{ $product->stock }})i.value=+i.value+1"
                                class="w-10 py-2.5 text-center transition" style="color:#1a4d6e;"
                                onmouseover="this.style.background='#EDE0D4';"
                                onmouseout="this.style.background='';">+</button>
                    </div>

                    <button type="submit" name="action" value="cart"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg py-3 text-sm font-semibold transition"
                            style="border:1.5px solid #fa4e1c;color:#fa4e1c;background:#fff;"
                            onmouseover="this.style.background='#FFF6EE';"
                            onmouseout="this.style.background='#fff';"
                            {{ $inStock ? '' : 'disabled' }}>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h1.6l1.7 10.4a2 2 0 002 1.6h8.6a2 2 0 002-1.6L20.4 7H6"/>
                            <circle cx="9.5" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                            <circle cx="17" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                        </svg>
                        {{ $inStock ? 'Add to Cart' : 'Out of Stock' }}
                    </button>

                    <button type="submit" name="action" value="buy_now"
                            class="flex-1 rounded-lg py-3 text-sm font-semibold transition"
                            style="background:#fa4e1c;color:#fff;"
                            onmouseover="this.style.background='#E14F00';"
                            onmouseout="this.style.background='#fa4e1c';"
                            {{ $inStock ? '' : 'disabled' }}>
                        Buy Now
                    </button>
                </div>
                {{-- Note: "Buy Now" submits the same add-to-cart route with action=buy_now.
                     Handle that flag in the controller to redirect straight to checkout. --}}
            </form>

            {{-- Trust badges --}}
            <div class="mt-6 grid grid-cols-3 gap-3">
                @foreach ([['🚚','Free shipping','orders over $50'],['↩️','Easy returns','30-day policy'],['🔒','Secure','checkout']] as [$ic,$t,$s])
                    <div class="flex flex-col items-center gap-1 rounded-xl border py-3 text-center" style="border-color:#cfdce8;">
                        <span class="text-lg">{{ $ic }}</span>
                        <span class="text-[11px] font-semibold" style="color:#1a4d6e;">{{ $t }}</span>
                        <span class="text-[10px]" style="color:#6b90aa;">{{ $s }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Book Details table --}}
            <div class="mt-8 rounded-2xl border p-6" style="border-color:#cfdce8;background:#FDFAF7;">
                <h3 class="font-display text-sm font-semibold uppercase tracking-widest mb-4" style="color:#002b4d;">
                    Book Details
                </h3>
                <dl class="text-sm divide-y" style="--tw-divide-color:#cfdce8;">
                    @php
                        $details = [
                            'Format'       => $product->format ?? '—',
                            'Language'     => $product->language ?? 'English',
                            'Pages'        => $product->pages ? number_format($product->pages) . ' pages' : '—',
                            'Edition'      => $product->edition ?? '—',
                            'Publisher'    => $product->publisher ?? '—',
                            'Pub. Year'    => $product->publication_year ?? '—',
                            'ISBN'         => $product->isbn ?? '—',
                            'Category'     => $product->category->name ?? '—',
                            'SKU'          => $product->sku ?? '—',
                        ];
                    @endphp
                    @foreach ($details as $label => $val)
                        <div class="flex items-center justify-between py-2.5" style="border-color:#cfdce8;">
                            <dt style="color:#6b90aa;">{{ $label }}</dt>
                            <dd class="font-medium text-right" style="color:#002b4d;">{{ $val }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Seller info --}}
            @if ($product->seller)
                <div class="mt-4 flex items-center gap-3 rounded-xl border p-4" style="border-color:#cfdce8;">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-bold text-sm"
                          style="background:#fa4e1c;color:#fff;">
                        {{ strtoupper(substr($product->seller->name, 0, 1)) }}
                    </span>
                    <div class="flex-1">
                        <p class="text-xs" style="color:#6b90aa;">Sold by</p>
                        <p class="font-semibold text-sm" style="color:#002b4d;">{{ $product->seller->name }}</p>
                    </div>
                    <a href="#" class="rounded px-3 py-1.5 text-xs font-semibold transition"
                       style="border:1px solid #fa4e1c;color:#fa4e1c;"
                       onmouseover="this.style.background='#FFF6EE';"
                       onmouseout="this.style.background='';">Visit Store</a>
                </div>
            @endif
        </div>
    </div>

    {{-- Related Books --}}
    @if ($related->count())
        <div class="mt-20 border-t pt-16" style="border-color:#cfdce8;">
            <h2 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">
                You May Also Like
                <span style="display:inline-block;width:32px;height:3px;background:#fa4e1c;border-radius:2px;margin-left:10px;vertical-align:middle;"></span>
            </h2>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                @foreach ($related as $r)
                    <x-product-card :product="$r" />
                @endforeach
            </div>
        </div>
    @endif

</div>
</x-layout>