<x-layout :title="$seller->storeName() . ' — ALVY'">
@php
    $app      = $seller->sellerApplication;
    $filtered = request('category') || request('scat');
    // Heading over the product grid: the category being browsed.
    $viewing  = $chosen?->name
        ?? (request('scat') === 'none' ? 'Uncategorized'
        : (request('category') ? request('category') . (request('sub') ? ' › ' . request('sub') : '') : 'All products'));
@endphp
<style>
    .st-cat { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-radius: 8px; padding: 7px 10px; font-size: 13.5px; color: #44403C; transition: background .12s, color .12s; }
    .st-cat:hover { background: #F7F5F3; color: #fa4e1c; }
    .st-cat.is-on { background: #FFF1EB; color: #fa4e1c; font-weight: 700; }
    .st-cat .n { flex-shrink: 0; min-width: 22px; border-radius: 999px; background: #F1EFED; padding: 1px 7px; text-align: center; font-size: 11px; font-weight: 600; color: #8a7f78; }
    .st-cat.is-on .n { background: #fa4e1c; color: #fff; }
    .st-sub { margin: 2px 0 4px 14px; border-left: 2px solid #F1EFED; padding-left: 6px; }
    .st-sub .st-cat { padding: 5px 8px; font-size: 12.5px; color: #78716C; }
    .st-sub .st-cat.is-on { background: none; color: #fa4e1c; }
    .st-sub .st-cat .n { background: none; padding: 0; min-width: 0; }
    .st-sub .st-cat.is-on .n { color: #fa4e1c; }
    .st-ticket { position: relative; display: flex; align-items: stretch; overflow: hidden; border-radius: 12px; border: 1px solid #FDD5C2; background: #fff; }
    .st-ticket::before, .st-ticket::after { content: ""; position: absolute; left: 76px; width: 14px; height: 14px; border-radius: 999px; background: #F7F7F7; border: 1px solid #FDD5C2; }
    .st-ticket::before { top: -8px; } .st-ticket::after { bottom: -8px; }
</style>

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    @if (session('success'))<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#ECFDF5;color:#047857;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ session('error') }}</div>@endif

    {{-- ══════════ Store header ══════════ --}}
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1" style="--tw-ring-color:#EEEAE6;">
        @if ($app?->banner_path)
            <img src="{{ asset('storage/' . $app->banner_path) }}" alt="{{ $seller->storeName() }} banner" class="h-32 w-full object-cover sm:h-44" data-store-banner>
        @else
            <div class="h-24 w-full sm:h-28" style="background:radial-gradient(120% 140% at 100% 0%, rgba(250,78,28,.55) 0%, rgba(250,78,28,0) 55%), linear-gradient(120deg,#002b4d,#0b4a7a);"></div>
        @endif

        <div class="flex flex-wrap items-end gap-4 px-5 pb-5">
            <div class="-mt-10 shrink-0 rounded-full bg-white p-1 shadow-md">
                @if ($app?->logo_path)
                    <img src="{{ asset('storage/' . $app->logo_path) }}" alt="{{ $seller->storeName() }} logo" class="h-20 w-20 rounded-full object-cover" data-store-logo>
                @else
                    <span class="flex h-20 w-20 items-center justify-center rounded-full text-3xl font-extrabold text-white" style="background:#fa4e1c;">{{ strtoupper(substr($seller->storeName(), 0, 1)) }}</span>
                @endif
            </div>

            <div class="min-w-0 flex-1 pt-3">
                <h1 class="font-display break-words text-2xl font-extrabold" style="color:#1c1917;">{{ $seller->storeName() }}
                    @include('partials.trusted-badge', ['seller' => $seller])
                    @if ($app?->is_on_vacation)<span class="ml-2 rounded-full px-2.5 py-0.5 align-middle text-xs font-semibold" style="background:#FFFBEB;color:#B45309;">On vacation</span>@endif
                </h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm" style="color:#78716C;">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <b style="color:#1c1917;" data-followers>{{ number_format($followers) }}</b> {{ \Illuminate\Support\Str::plural('follower', $followers) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/></svg>
                        <b style="color:#1c1917;">{{ number_format($productCount) }}</b> {{ \Illuminate\Support\Str::plural('product', $productCount) }}
                    </span>
                    @if ($app?->municipality)
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            {{ $app->municipality }}{{ $app->province ? ', ' . $app->province : '' }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="pt-3">
                @auth
                    @if (auth()->id() !== $seller->id)
                        <form action="{{ route('stores.follow', $seller->id) }}" method="POST">@csrf
                            @if ($following)
                                <button class="inline-flex items-center gap-1.5 rounded-full border px-5 py-2 text-sm font-semibold transition hover:bg-stone-50" style="border-color:#D6D3D1;color:#44403C;">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg> Following
                                </button>
                            @else
                                <button class="inline-flex items-center gap-1.5 rounded-full px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-105" style="background:#fa4e1c;">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Follow
                                </button>
                            @endif
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-full px-5 py-2 text-sm font-semibold text-white shadow-sm" style="background:#fa4e1c;">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Follow
                    </a>
                @endauth
            </div>
        </div>

        @if ($app?->description)
            <p class="whitespace-pre-line break-words border-t px-5 py-4 text-sm leading-relaxed" style="border-color:#F1EFED;color:#57534E;" data-store-description>{{ $app->description }}</p>
        @endif
    </section>

    @if ($app?->is_on_vacation)
        <p class="mt-4 flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold" style="background:#FFFBEB;color:#B45309;">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/></svg>
            This store is on vacation. You can browse, but orders are paused for now.
        </p>
    @endif

    {{-- ══════════ Vouchers ══════════ --}}
    @if ($vouchers->isNotEmpty())
        <section class="mt-5">
            <h2 class="mb-2.5 text-sm font-bold" style="color:#1c1917;">Store vouchers</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($vouchers as $v)
                    <div class="st-ticket">
                        <div class="flex w-[84px] shrink-0 items-center justify-center" style="background:linear-gradient(160deg,#fb7048,#e2400f);">
                            <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12V8H4v4a2 2 0 0 1 0 4v4h16v-4a2 2 0 0 1 0-4zM10 8v12" stroke-dasharray="2 2"/></svg>
                        </div>
                        <div class="flex min-w-0 flex-1 items-center gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-extrabold" style="color:#C2410C;">{{ $v->discountLabel() }}</p>
                                <p class="text-xs" style="color:#8a7f78;">{{ $v->min_spend > 0 ? 'Min spend ₱' . number_format($v->min_spend, 0) : 'No minimum' }}@if ($v->ends_at) · until {{ $v->ends_at->copy()->tz(config('app.business_timezone'))->format('M d') }}@endif</p>
                            </div>
                            @if (in_array($v->id, $claimed))
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background:#ECFDF5;color:#059669;">Claimed</span>
                            @else
                                <form action="{{ route('vouchers.claim', $v) }}" method="POST">@csrf
                                    <button class="rounded-full px-4 py-1.5 text-xs font-bold text-white transition hover:brightness-105" style="background:#fa4e1c;">Claim</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[240px_1fr]">
        {{-- ══════════ Store categories (two levels) ══════════ --}}
        <aside class="rounded-2xl bg-white p-3 shadow-sm ring-1 lg:sticky lg:top-24" style="--tw-ring-color:#EEEAE6;">
            <p class="flex items-center gap-2 px-2 pb-2 pt-1 text-xs font-bold uppercase tracking-wider" style="color:#a39890;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                Categories
            </p>
            <nav class="space-y-0.5">
                <a href="{{ route('stores.show', $seller->id) }}" class="st-cat {{ $filtered ? '' : 'is-on' }}">
                    <span>All products</span><span class="n">{{ $productCount }}</span>
                </a>
                @foreach ($storeCategories as $sc)
                    <a href="{{ route('stores.show', [$seller->id, 'scat' => $sc->id]) }}" class="st-cat {{ (string) request('scat') === (string) $sc->id ? 'is-on' : '' }}" data-store-category="{{ $sc->name }}">
                        <span class="min-w-0 break-words">{{ $sc->name }}</span><span class="n">{{ $sc->products_count }}</span>
                    </a>
                    @if ($sc->children->isNotEmpty())
                        <div class="st-sub">
                            @foreach ($sc->children as $child)
                                <a href="{{ route('stores.show', [$seller->id, 'scat' => $child->id]) }}" class="st-cat {{ (string) request('scat') === (string) $child->id ? 'is-on' : '' }}" data-store-category="{{ $child->name }}">
                                    <span class="min-w-0 break-words">{{ $child->name }}</span><span class="n">{{ $child->products_count }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
                @if ($storeCategories->isNotEmpty() && $uncategorized > 0)
                    <a href="{{ route('stores.show', [$seller->id, 'scat' => 'none']) }}" class="st-cat {{ request('scat') === 'none' ? 'is-on' : '' }}" data-store-category="Uncategorized">
                        <span>Uncategorized</span><span class="n">{{ $uncategorized }}</span>
                    </a>
                @endif
                @foreach ($categories as $name => $cat)
                    @php $active = request('category') === $name; @endphp
                    <a href="{{ route('stores.show', [$seller->id, 'category' => $name]) }}" class="st-cat {{ $active && ! request('sub') ? 'is-on' : '' }}">
                        <span class="min-w-0 break-words">{{ $name }}</span><span class="n">{{ $cat['count'] }}</span>
                    </a>
                    @if ($cat['subs'])
                        <div class="st-sub">
                            @foreach ($cat['subs'] as $sub => $n)
                                <a href="{{ route('stores.show', [$seller->id, 'category' => $name, 'sub' => $sub]) }}" class="st-cat {{ request('sub') === $sub && $active ? 'is-on' : '' }}">
                                    <span class="min-w-0 break-words">{{ $sub }}</span><span class="n">{{ $n }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>
        </aside>

        {{-- ══════════ Products ══════════ --}}
        <div class="min-w-0">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white px-4 py-3 shadow-sm ring-1" style="--tw-ring-color:#EEEAE6;">
                <h2 class="min-w-0 break-words text-base font-bold" style="color:#1c1917;">{{ $viewing }}
                    <span class="ml-1 text-sm font-medium" style="color:#a39890;">{{ number_format($products->total()) }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}</span>
                </h2>
                @if ($filtered)
                    <a href="{{ route('stores.show', $seller->id) }}" class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition hover:bg-stone-50" style="border-color:#E7E5E4;color:#57534E;">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg> Clear filter
                    </a>
                @endif
            </div>

            @if ($products->isEmpty())
                <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed bg-white px-6 py-14 text-center" style="border-color:#E7E5E4;">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full" style="background:#FFF1EB;color:#fa4e1c;">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </span>
                    <p class="text-sm font-semibold" style="color:#44403C;">No products here yet.</p>
                    @if ($filtered)<a href="{{ route('stores.show', $seller->id) }}" class="text-sm font-semibold hover:underline" style="color:#fa4e1c;">See all products</a>@endif
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $p)
                        <x-product-card :product="$p" />
                    @endforeach
                </div>
                <div class="mt-5">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-layout>
