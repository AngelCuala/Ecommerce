<x-layout title="Following — ALVY">
<style>
    .fl-card { overflow: hidden; transition: box-shadow .2s ease, transform .2s ease; }
    .fl-card:hover { box-shadow: 0 18px 40px -24px rgba(0, 43, 77, .45); transform: translateY(-1px); }
    .fl-cover { position: relative; height: 84px; background: linear-gradient(120deg, #fa4e1c 0%, #ff8a5b 45%, #002b4d 130%); }
    .fl-cover img { width: 100%; height: 100%; object-fit: cover; }
    .fl-logo { position: relative; z-index: 1; width: 72px; height: 72px; margin-top: -36px; border: 4px solid #fff; border-radius: 18px; background: #002b4d; color: #fff;
               display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;
               font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.6rem; font-weight: 900; box-shadow: 0 8px 20px -10px rgba(0,0,0,.45); }
    .fl-logo img { width: 100%; height: 100%; object-fit: cover; background: #fff; }
    .fl-stat { display: inline-flex; align-items: center; gap: 5px; font-size: .8rem; color: #6b5e55; }
    .fl-stat b { color: #222; font-weight: 700; }
    .fl-dot { width: 3px; height: 3px; border-radius: 999px; background: #d6c8be; }
    .fl-tag { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 3px 10px; font-size: .72rem; font-weight: 700; }
    .fl-thumb { display: block; overflow: hidden; border-radius: 12px; border: 1px solid #F0E4DC; background: #FDF8F5; transition: border-color .15s ease; }
    .fl-thumb:hover { border-color: #fa4e1c; }
    .fl-thumb .img { aspect-ratio: 1 / 1; overflow: hidden; display: flex; align-items: center; justify-content: center; color: #c9b9ad; }
    .fl-thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; }
    .fl-thumb:hover img { transform: scale(1.05); }
</style>

<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    @include('profile._page-header', ['title' => 'Following', 'subtitle' => 'Stores you follow, with their newest products.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-4">
            @if ($stores->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm pf-muted" data-following-count>
                        You follow <b style="color:#222;">{{ $stores->count() }}</b> {{ \Illuminate\Support\Str::plural('store', $stores->count()) }}
                    </p>
                    <a href="{{ route('shop.index') }}" class="text-sm font-semibold hover:underline" style="color:#fa4e1c;">Discover more stores →</a>
                </div>
            @endif

            @forelse ($stores as $s)
                @php $name = $s->seller->storeName(); $url = route('stores.show', $s->seller->id); @endphp
                <article class="pf-card fl-card" data-followed-store="{{ $s->seller->id }}">
                    {{-- Cover: the store's banner, or the ALVY gradient --}}
                    <a href="{{ $url }}" class="fl-cover block" aria-label="Visit {{ $name }}">
                        @if ($s->app?->banner_path)<img src="{{ asset('storage/' . $s->app->banner_path) }}" alt="">@endif
                    </a>

                    <div class="px-5 pb-5">
                        {{-- Logo, name and actions --}}
                        <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                            <a href="{{ $url }}" class="fl-logo">
                                @if ($s->app?->logo_path)
                                    <img src="{{ asset('storage/' . $s->app->logo_path) }}" alt="{{ $name }} logo">
                                @else
                                    {{ strtoupper(substr($name, 0, 1)) }}
                                @endif
                            </a>
                            <div class="min-w-0 flex-1 pt-2">
                                <a href="{{ $url }}" class="font-display text-lg font-extrabold leading-tight hover:underline" style="color:#222;">{{ $name }}</a>
                                @if ($s->app?->is_on_vacation)
                                    <span class="fl-tag ml-1 align-middle" style="background:#FFFBEB;color:#B45309;">On vacation</span>
                                @endif
                                <p class="mt-0.5 flex items-center gap-1 text-xs pf-muted">
                                    @if ($s->app?->municipality)
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        {{ $s->app->municipality }}{{ $s->app->province ? ', ' . $s->app->province : '' }} ·
                                    @endif
                                    Following since {{ $s->follow->created_at->format('M j, Y') }}
                                </p>
                            </div>
                            <div class="flex w-full gap-2 sm:w-auto">
                                <a href="{{ $url }}" class="pf-btn flex-1 justify-center text-sm sm:flex-none">Visit store</a>
                                <form action="{{ route('stores.follow', $s->seller->id) }}" method="POST" class="flex-1 sm:flex-none"
                                      data-confirm="You'll stop seeing {{ $name }} here. You can follow it again from its store page."
                                      data-confirm-title="Unfollow {{ $name }}?" data-confirm-ok="Unfollow">
                                    @csrf
                                    <button class="pf-btn-ghost w-full justify-center text-sm">Unfollow</button>
                                </form>
                            </div>
                        </div>

                        {{-- Store numbers --}}
                        <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="fl-stat">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="#F59E0B" aria-hidden="true"><path d="M10 1.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8L10 14.9l-5.3 2.7 1-5.8L1.5 7.7l5.9-.9z"/></svg>
                                @if ($s->rating)<b>{{ number_format($s->rating, 1) }}</b> ({{ $s->reviews }} {{ \Illuminate\Support\Str::plural('review', $s->reviews) }})@else No ratings yet @endif
                            </span>
                            <span class="fl-dot" aria-hidden="true"></span>
                            <span class="fl-stat"><b>{{ number_format($s->products) }}</b> {{ \Illuminate\Support\Str::plural('product', $s->products) }}</span>
                            <span class="fl-dot" aria-hidden="true"></span>
                            <span class="fl-stat"><b>{{ number_format($s->followers) }}</b> {{ \Illuminate\Support\Str::plural('follower', $s->followers) }}</span>
                            @if ($s->vouchers > 0)
                                <a href="{{ $url }}" class="fl-tag" style="background:#FFF1EA;color:#d93d0e;">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                    {{ $s->vouchers }} {{ \Illuminate\Support\Str::plural('voucher', $s->vouchers) }} to claim
                                </a>
                            @endif
                        </div>

                        {{-- Newest products --}}
                        @if ($s->latest->isNotEmpty())
                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($s->latest as $p)
                                    <a href="{{ route('products.show', $p->slug) }}" class="fl-thumb" title="{{ $p->title }}">
                                        <div class="img">
                                            @if ($p->primary_image)
                                                <img src="{{ $p->primary_image }}" alt="{{ $p->title }}" loading="lazy">
                                            @else
                                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                                            @endif
                                        </div>
                                        <div class="px-2.5 py-2">
                                            <p class="truncate text-xs font-medium" style="color:#3a2f29;">{{ $p->title }}</p>
                                            <p class="text-sm font-bold" style="color:#fa4e1c;">₱{{ number_format($p->effective_price, 2) }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-4 rounded-xl px-4 py-3 text-sm pf-muted" style="background:#FDF8F5;">This store hasn't listed any products yet.</p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pf-card px-6 py-14 text-center">
                    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl" style="background:#FFF1EA;color:#fa4e1c;" aria-hidden="true">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/></svg>
                    </span>
                    <p class="mt-4 font-display text-lg font-extrabold" style="color:#222;">You don't follow any stores yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm pf-muted">Follow the stores you like to find their newest products and vouchers here.</p>
                    <a href="{{ route('shop.index') }}" class="pf-btn mt-5 inline-flex">Start shopping</a>
                </div>
            @endforelse
        </div>
    </div>
</div>
</div>
</x-layout>
