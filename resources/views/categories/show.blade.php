<x-layout :title="$active['name'] . ' — ALVY'">

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <nav class="mb-5 flex items-center gap-1.5 text-xs" style="color:#999999;">
        <a href="{{ route('home') }}" onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#999999';">Home</a>
        <span>›</span>
        <a href="{{ route('categories.page') }}" onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#999999';">Categories</a>
        <span>›</span>
        <span style="color:#fa4e1c;">{{ $active['name'] }}</span>
        @if ($activeSub)
            <span>›</span>
            <span style="color:#333;">{{ $activeSub }}</span>
        @endif
    </nav>

    <div class="flex gap-6">

        {{-- ══════════════════════════
             LEFT SIDEBAR — all categories
        ══════════════════════════ --}}
        <aside class="hidden w-56 flex-shrink-0 lg:block">
            <div class="card overflow-hidden">
                <a href="{{ route('categories.page') }}"
                   class="flex items-center px-4 py-3 text-xs font-bold uppercase tracking-widest text-white transition hover:opacity-90"
                   style="background:#002b4d;">
                    ☰ All Categories
                </a>
                <nav class="py-1">
                    @foreach ($catalog as $cat)
                        @php $isActive = $cat['slug'] === $active['slug']; @endphp
                        <a href="{{ route('categories.show', $cat['slug']) }}"
                           class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition"
                           style="{{ $isActive
                               ? 'background:#fff1ee;color:#fa4e1c;font-weight:700;border-right:3px solid #fa4e1c;'
                               : 'color:#444444;' }}"
                           onmouseover="{{ $isActive ? '' : "this.style.background='#FFF8F5';this.style.color='#fa4e1c';" }}"
                           onmouseout="{{ $isActive ? '' : "this.style.background='';this.style.color='#444444';" }}">
                            <span class="text-base">{{ $cat['icon'] }}</span>
                            <span class="line-clamp-2 leading-snug">{{ $cat['name'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- ══════════════════════════
             MAIN CONTENT
        ══════════════════════════ --}}
        <div class="min-w-0 flex-1">

            {{-- Category header --}}
            <div class="card mb-5 overflow-hidden">
                <div class="flex flex-wrap items-center gap-4 px-6 py-5"
                     style="background:linear-gradient(135deg,{{ $active['color'] }}20 0%,{{ $active['color'] }}08 100%);border-bottom:2px solid {{ $active['color'] }}25;">
                    <span class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl text-3xl"
                          style="background:{{ $active['color'] }}20;">{{ $active['icon'] }}</span>
                    <div>
                        <h1 class="text-xl font-extrabold" style="color:#222222;font-family:'Nunito',sans-serif;">
                            {{ $active['name'] }}
                        </h1>
                        <p class="mt-0.5 text-sm" style="color:#757575;">
                            @if(count($active['subs']) > 0)
                                {{ count($active['subs']) }} subcategories
                                @if ($activeSub) · Showing: <strong>{{ $activeSub }}</strong> @endif
                            @endif
                        </p>
                    </div>
                    @if ($activeSub)
                        <a href="{{ route('categories.show', $active['slug']) }}"
                           class="ml-auto rounded-full border px-4 py-1.5 text-xs font-semibold transition"
                           style="border-color:#E0E0E0;color:#757575;"
                           onmouseover="this.style.borderColor='#fa4e1c';this.style.color='#fa4e1c';"
                           onmouseout="this.style.borderColor='#E0E0E0';this.style.color='#757575';">
                            ✕ Clear filter
                        </a>
                    @endif
                </div>

                {{-- Subcategory pills — only shown if the category has subcategories --}}
                @if(count($active['subs']) > 0)
                <div class="flex flex-wrap gap-2 px-6 py-4" style="background:#FAFAFA;">
                    <a href="{{ route('categories.show', $active['slug']) }}"
                       class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
                       style="{{ !$activeSub
                           ? 'background:'.$active['color'].';color:#fff;'
                           : 'background:#fff;color:#555;border:1px solid #E0E0E0;' }}"
                       onmouseover="{{ $activeSub ? "this.style.borderColor='{$active['color']}';this.style.color='{$active['color']}';" : '' }}"
                       onmouseout="{{ $activeSub ? "this.style.borderColor='#E0E0E0';this.style.color='#555';" : '' }}">
                        All
                    </a>
                    @foreach ($active['subs'] as $sub)
                        @php $isActiveSub = $activeSub === $sub; @endphp
                        <a href="{{ route('categories.show', $active['slug']) }}?sub={{ urlencode($sub) }}"
                           class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
                           style="{{ $isActiveSub
                               ? 'background:'.$active['color'].';color:#fff;'
                               : 'background:#fff;color:#555;border:1px solid #E0E0E0;' }}"
                           onmouseover="{{ $isActiveSub ? '' : "this.style.borderColor='{$active['color']}';this.style.color='{$active['color']}';" }}"
                           onmouseout="{{ $isActiveSub ? '' : "this.style.borderColor='#E0E0E0';this.style.color='#555';" }}">
                            {{ $sub }}
                        </a>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- ══════════════════════════
                 PRODUCTS GRID
            ══════════════════════════ --}}
            @if ($products->isEmpty())
                <div class="card flex flex-col items-center gap-4 py-20 text-center">
                    <span class="text-5xl">{{ $active['icon'] }}</span>
                    <div>
                        <p class="text-lg font-bold" style="color:#222222;">No products yet</p>
                        <p class="mt-1 text-sm" style="color:#999999;">
                            @if ($activeSub)
                                No products found under "{{ $activeSub }}".
                            @else
                                Sellers haven't listed any products in {{ $active['name'] }} yet.
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('shop.index') }}"
                       class="btn-gold mt-2">Browse All Products</a>
                </div>
            @else
                {{-- Toolbar --}}
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm" style="color:#757575;">
                        <span class="font-bold" style="color:#222;">{{ $products->count() }}</span>
                        product{{ $products->count() !== 1 ? 's' : '' }} found
                    </p>
                    <form method="GET">
                        @if ($activeSub)<input type="hidden" name="sub" value="{{ $activeSub }}">@endif
                        <select name="sort" onchange="this.form.submit()"
                                class="rounded-lg border py-1.5 pl-3 pr-8 text-xs focus:outline-none"
                                style="border-color:#E0E0E0;color:#444;background:#fff;">
                            <option value="newest" {{ request('sort','newest')==='newest' ? 'selected':'' }}>Newest</option>
                            <option value="price_low" {{ request('sort')==='price_low' ? 'selected':'' }}>Price: Low → High</option>
                            <option value="price_high" {{ request('sort')==='price_high' ? 'selected':'' }}>Price: High → Low</option>
                        </select>
                    </form>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @php
                        $sorted = match(request('sort','newest')) {
                            'price_low'  => $products->sortBy('price')->values(),
                            'price_high' => $products->sortByDesc('price')->values(),
                            default      => $products,
                        };
                    @endphp
                    @foreach ($sorted as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</div>

{{-- Mobile category sidebar toggle --}}
<div class="fixed bottom-4 right-4 z-40 lg:hidden">
    <button id="mob-cat-btn"
            class="flex h-12 w-12 items-center justify-center rounded-full shadow-lg text-xl"
            style="background:#fa4e1c;color:#fff;"
            onclick="document.getElementById('mob-cat-drawer').classList.toggle('translate-x-full')">
        📂
    </button>
</div>
<div id="mob-cat-drawer"
     class="fixed inset-y-0 right-0 z-50 w-64 translate-x-full overflow-y-auto shadow-2xl transition-transform duration-300 lg:hidden"
     style="background:#fff;">
    <div class="flex items-center justify-between px-4 py-4 text-white" style="background:#002b4d;">
        <p class="text-sm font-bold">All Categories</p>
        <button onclick="document.getElementById('mob-cat-drawer').classList.add('translate-x-full')"
                class="text-white opacity-80 hover:opacity-100 text-xl">✕</button>
    </div>
    <nav class="py-2">
        @foreach ($catalog as $cat)
            <a href="{{ route('categories.show', $cat['slug']) }}"
               class="flex items-center gap-3 px-4 py-3 text-sm transition"
               style="{{ $cat['slug'] === $active['slug'] ? 'background:#fff1ee;color:#fa4e1c;font-weight:700;' : 'color:#444;' }}">
                <span class="text-lg">{{ $cat['icon'] }}</span>
                {{ $cat['name'] }}
            </a>
        @endforeach
    </nav>
</div>

</x-layout>
