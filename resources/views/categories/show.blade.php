<x-layout :title="$active['name'] . ' — ALVY'">
@php
    // Category photos (Unsplash License) live in public/images/categories/<slug>.jpg
    $photo = fn ($slug) => asset('images/categories/' . $slug . '.jpg');
    $catUrl = fn ($sub = null) => route('categories.show', $active['slug']) . ($sub ? '?sub=' . urlencode($sub) : '');
@endphp
<style>
    .ct-card { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; }
    .ct-side-item { display: flex; align-items: center; gap: .65rem; padding: .45rem .75rem; border-radius: .5rem; font-size: .875rem; color: #44403C; transition: background .12s, color .12s; }
    .ct-side-item:hover { background: #FAFAF9; color: #222; }
    .ct-side-item.is-active { background: #FFF5F1; color: #fa4e1c; font-weight: 600; }
    .ct-thumb { width: 1.75rem; height: 1.75rem; flex-shrink: 0; overflow: hidden; border-radius: 9999px; background: #F5F2EF; }
    .ct-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .ct-chip { display: inline-flex; align-items: center; white-space: nowrap; border: 1px solid #E7E5E4; border-radius: 9999px; padding: .35rem .85rem; font-size: .8125rem; color: #57534E; background: #fff; transition: border-color .12s, color .12s; }
    .ct-chip:hover { border-color: #fa4e1c; color: #fa4e1c; }
    .ct-chip.is-active { border-color: #fa4e1c; background: #fa4e1c; color: #fff; }
    .ct-scroll { scrollbar-width: none; }
    .ct-scroll::-webkit-scrollbar { display: none; }
</style>

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- Breadcrumb --}}
    <nav class="mb-5 flex flex-wrap items-center gap-1.5 text-xs" style="color:#8a7f78;">
        <a href="{{ route('home') }}" class="hover:underline">Home</a>
        <span>/</span>
        <a href="{{ route('categories.page') }}" class="hover:underline">Categories</a>
        <span>/</span>
        @if ($activeSub)
            <a href="{{ $catUrl() }}" class="hover:underline">{{ $active['name'] }}</a>
            <span>/</span>
            <span style="color:#222;">{{ $activeSub }}</span>
        @else
            <span style="color:#222;">{{ $active['name'] }}</span>
        @endif
    </nav>

    {{-- Mobile: categories as a scrolling row --}}
    <div class="ct-scroll -mx-4 mb-4 flex gap-2 overflow-x-auto px-4 lg:hidden">
        @foreach ($catalog as $cat)
            <a href="{{ route('categories.show', $cat['slug']) }}"
               class="ct-chip {{ $cat['slug'] === $active['slug'] ? 'is-active' : '' }}">{{ $cat['name'] }}</a>
        @endforeach
    </div>

    <div class="flex gap-6">

        {{-- Sidebar --}}
        <aside class="hidden w-60 flex-shrink-0 lg:block">
            <div class="ct-card sticky top-24 p-2">
                <p class="px-3 pb-2 pt-2 text-xs font-semibold uppercase tracking-wider" style="color:#a39890;">Categories</p>
                <nav class="space-y-0.5">
                    @foreach ($catalog as $cat)
                        <a href="{{ route('categories.show', $cat['slug']) }}"
                           class="ct-side-item {{ $cat['slug'] === $active['slug'] ? 'is-active' : '' }}">
                            <span class="ct-thumb"><img src="{{ $photo($cat['slug']) }}" alt="" loading="lazy"></span>
                            <span class="leading-snug">{{ $cat['name'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Main --}}
        <div class="min-w-0 flex-1 space-y-4">

            {{-- Header --}}
            <section class="ct-card p-5">
                <div class="flex items-center gap-4">
                    <img src="{{ $photo($active['slug']) }}" alt=""
                         class="h-16 w-16 flex-shrink-0 rounded-lg object-cover" style="border:1px solid #ECE7E3;">
                    <div class="min-w-0">
                        <h1 class="font-display text-xl font-extrabold sm:text-2xl" style="color:#222;">{{ $active['name'] }}</h1>
                        <p class="mt-0.5 text-sm" style="color:#8a7f78;">
                            {{ $products->count() }} {{ \Illuminate\Support\Str::plural('product', $products->count()) }}
                            @if (count($active['subs']) > 0)
                                &middot; {{ count($active['subs']) }} subcategories
                            @endif
                        </p>
                    </div>
                </div>

                @if (count($active['subs']) > 0)
                    <div class="ct-scroll mt-4 flex gap-2 overflow-x-auto sm:flex-wrap sm:overflow-visible">
                        <a href="{{ $catUrl() }}" class="ct-chip {{ ! $activeSub ? 'is-active' : '' }}">All</a>
                        @foreach ($active['subs'] as $sub)
                            <a href="{{ $catUrl($sub) }}" class="ct-chip {{ $activeSub === $sub ? 'is-active' : '' }}">{{ $sub }}</a>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Products --}}
            @if ($products->isEmpty())
                <section class="ct-card px-6 py-14 text-center">
                    <p class="font-semibold" style="color:#222;">No products here yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm" style="color:#8a7f78;">
                        @if ($activeSub)
                            Nothing is listed under {{ $activeSub }} right now.
                        @else
                            Sellers haven't listed anything in {{ $active['name'] }} yet. Check back soon.
                        @endif
                    </p>
                    <a href="{{ route('shop.index') }}"
                       class="mt-5 inline-block rounded-md border px-4 py-2 text-sm font-semibold transition hover:bg-[#fff5f3]"
                       style="border-color:#fa4e1c;color:#fa4e1c;">Browse all products</a>
                </section>
            @else
                <div class="flex items-center justify-between">
                    <p class="text-sm" style="color:#8a7f78;">
                        Showing <span class="font-semibold" style="color:#222;">{{ $products->count() }}</span>
                        {{ \Illuminate\Support\Str::plural('product', $products->count()) }}
                    </p>
                    <form method="GET" class="flex items-center gap-2">
                        @if ($activeSub)<input type="hidden" name="sub" value="{{ $activeSub }}">@endif
                        <label for="ct-sort" class="text-sm" style="color:#8a7f78;">Sort by</label>
                        <select id="ct-sort" name="sort" onchange="this.form.submit()"
                                class="rounded-md border bg-white py-1.5 pl-3 pr-8 text-sm focus:outline-none"
                                style="border-color:#E7E5E4;color:#222;">
                            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                            <option value="price_low" @selected(request('sort') === 'price_low')>Price: low to high</option>
                            <option value="price_high" @selected(request('sort') === 'price_high')>Price: high to low</option>
                        </select>
                    </form>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
</x-layout>
