<x-layout title="Voucher Center — ALVY">
@php
    $icons = [
        'all'      => '<path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
        'shipping' => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'discount' => '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'store'    => '<path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/>',
    ];
    $tabs = ['all' => 'All vouchers', 'shipping' => 'Free shipping', 'discount' => 'Discounts', 'store' => 'Store vouchers'];
    $emptyText = [
        'all'      => ['No vouchers right now', 'New vouchers from ALVY and stores show up here. Check back soon.'],
        'shipping' => ['No free-shipping vouchers right now', 'Free-shipping vouchers show up here when ALVY runs them.'],
        'discount' => ['No ALVY discounts right now', 'ALVY-wide discounts show up here during promos and sales.'],
        'store'    => ['No store vouchers right now', 'Follow your favourite stores. Their vouchers show up here.'],
    ];
    $sections = [
        'shipping' => ['Free shipping', 'Use on any order', $vouchers->where('scope', \App\Models\Voucher::SCOPE_SHIPPING)],
        'platform' => ['ALVY discounts', 'Paid by ALVY, at every store', $vouchers->where('scope', \App\Models\Voucher::SCOPE_PLATFORM)],
        'seller'   => ['Store vouchers', 'Only at the store that offers them', $vouchers->where('scope', \App\Models\Voucher::SCOPE_SELLER)],
    ];
    $unclaimed = $vouchers->reject(fn ($v) => in_array($v->id, $claimedIds, true))->count();
    $svg = fn ($d, $cls = 'h-4 w-4') => '<svg class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $d . '</svg>';
@endphp
<style>
    .vc-hero { position: relative; overflow: hidden; border-radius: 1.25rem; padding: 1.75rem; color: #fff;
               background: radial-gradient(120% 140% at 100% 0%, #ff8a5b 0%, rgba(255,138,91,0) 55%), linear-gradient(115deg, #fa4e1c 0%, #e2400f 40%, #002b4d 120%); }
    .vc-hero h1 { color: #fff; }
    .vc-hero::after { content: ""; position: absolute; right: -40px; bottom: -60px; width: 220px; height: 220px; border-radius: 999px; border: 28px solid rgba(255,255,255,.08); }
    .vc-chip { display: inline-flex; align-items: center; gap: .4rem; border-radius: 999px; background: rgba(255,255,255,.16); padding: .35rem .8rem; font-size: .8rem; font-weight: 600; backdrop-filter: blur(4px); }
    .vc-chip b { font-size: .95rem; font-weight: 800; }
    .vc-hero-btn { display: inline-flex; align-items: center; gap: .45rem; border-radius: .75rem; background: #fff; padding: .6rem 1rem; font-size: .875rem; font-weight: 700; color: #d93d0e; box-shadow: 0 8px 20px -10px rgba(0,0,0,.4); transition: transform .15s; }
    .vc-hero-btn:hover { transform: translateY(-1px); }
    .vc-ticket-art { position: relative; z-index: 1; display: flex; align-items: center; width: 11.5rem; white-space: nowrap; height: 5.25rem; border-radius: .9rem; background: #fff; color: #fa4e1c; transform: rotate(-6deg); box-shadow: 0 18px 30px -14px rgba(0,0,0,.45); }
    .vc-ticket-art .stub { display: flex; height: 100%; width: 3.1rem; align-items: center; justify-content: center; border-radius: .9rem 0 0 .9rem; background: #FFF1EB; border-right: 2px dashed #FAD7C8; }
    .vc-ticket-art .txt { padding: 0 .8rem; font-family: 'Nunito', ui-sans-serif, system-ui; font-weight: 900; line-height: 1; }

    .vc-steps { display: grid; gap: .75rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .vc-step { display: flex; align-items: center; gap: .7rem; border-radius: .9rem; border: 1px solid #F0E4DC; background: #fff; padding: .8rem .9rem; }
    .vc-step-n { display: flex; height: 2rem; width: 2rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: .6rem; background: #FFF1EB; color: #fa4e1c; font-weight: 800; font-size: .85rem; }
    @media (max-width: 640px) { .vc-steps { grid-template-columns: 1fr; } }

    .vc-tabs { display: flex; gap: .5rem; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
    .vc-tabs::-webkit-scrollbar { display: none; }
    .vc-tab { display: inline-flex; align-items: center; gap: .45rem; white-space: nowrap; border-radius: 999px; border: 1px solid #ECE7E3; background: #fff; padding: .5rem .95rem; font-size: .85rem; font-weight: 600; color: #6b5e55; transition: all .15s; }
    .vc-tab:hover { border-color: #fa4e1c; color: #222; }
    .vc-tab.is-active { border-color: #fa4e1c; background: #fa4e1c; color: #fff; box-shadow: 0 8px 18px -10px rgba(250,78,28,.8); }
    .vc-n { min-width: 1.25rem; border-radius: 999px; background: #F3EEEA; padding: 0 .4rem; font-size: .6875rem; line-height: 1.25rem; text-align: center; color: #6b5d54; }
    .vc-tab.is-active .vc-n { background: rgba(255,255,255,.25); color: #fff; }

    .vc-sec-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .6rem; margin: 1.75rem 0 .75rem; }
    .vc-sec-head h2 { font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.1rem; font-weight: 800; color: #222; }
    .vc-store { display: flex; align-items: center; gap: .6rem; margin: 1rem 0 .5rem; }
    .vc-store-logo { display: flex; height: 1.9rem; width: 1.9rem; flex-shrink: 0; align-items: center; justify-content: center; overflow: hidden; border-radius: .55rem; background: #002b4d; color: #fff; font-size: .8rem; font-weight: 800; }
    .vc-store-logo img { height: 100%; width: 100%; object-fit: cover; background: #fff; }

    .vc-claim { display: inline-flex; align-items: center; justify-content: center; min-width: 4.75rem; border-radius: .5rem; padding: .5rem .85rem; font-size: .8125rem; font-weight: 700; color: #fff; background: #fa4e1c; transition: background .15s, transform .1s; }
    .vc-claim:hover { background: #e2400f; }
    .vc-claim:active { transform: scale(.97); }
    .vc-claimed { display: inline-flex; flex-direction: column; align-items: center; gap: .2rem; font-size: .75rem; font-weight: 700; color: #059669; }
    .vc-claimed a { font-weight: 600; color: #fa4e1c; }
    .vc-claimed a:hover { text-decoration: underline; }
    .vc-claim-all { display: inline-flex; align-items: center; gap: .4rem; border-radius: .6rem; border: 1px solid #fa4e1c; background: #fff; padding: .45rem .85rem; font-size: .8125rem; font-weight: 700; color: #fa4e1c; transition: background .15s; }
    .vc-claim-all:hover { background: #FFF1EB; }
</style>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

    <nav class="mb-3 text-xs" style="color:#8a7f78;"><a href="{{ route('home') }}" class="hover:underline">Home</a> / <span style="color:#222;">Voucher Center</span></nav>

    {{-- Hero --}}
    <section class="vc-hero" data-voucher-hero>
        <div class="relative z-[1] flex flex-wrap items-center justify-between gap-6">
            <div class="min-w-0 max-w-xl">
                <p class="text-xs font-bold uppercase tracking-[.18em] opacity-80">ALVY deals</p>
                <h1 class="mt-1 font-display text-3xl font-extrabold sm:text-4xl">Voucher Center</h1>
                <p class="mt-2 text-sm opacity-90 sm:text-base">Claim vouchers now and they're ready at checkout. The best ones are applied for you automatically.</p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="vc-chip"><b>{{ $counts['all'] }}</b> {{ \Illuminate\Support\Str::plural('voucher', $counts['all']) }} available</span>
                    @auth
                        <span class="vc-chip"><b>{{ count($claimedIds) }}</b> claimed by you</span>
                    @endauth
                </div>
            </div>
            <div class="flex flex-col items-start gap-4 sm:items-end">
                <div class="vc-ticket-art hidden sm:flex" aria-hidden="true">
                    <span class="stub">{!! $svg($icons['all'], 'h-6 w-6') !!}</span>
                    <span class="txt"><span class="block text-2xl">SAVE</span><span class="block text-[11px] tracking-[.2em]" style="color:#8a7f78;">AT CHECKOUT</span></span>
                </div>
                @auth
                    <a href="{{ route('vouchers.wallet') }}" class="vc-hero-btn">{!! $svg($icons['all']) !!} My vouchers ({{ count($claimedIds) }})</a>
                @else
                    <a href="{{ route('login') }}" class="vc-hero-btn">Sign in to claim vouchers</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <div class="vc-steps mt-4">
        @foreach ([['Claim', 'Tap Claim on the vouchers you want.'], ['Shop', 'Add items from any store to your cart.'], ['Save', 'The best vouchers apply at checkout.']] as $i => [$t, $d])
            <div class="vc-step">
                <span class="vc-step-n">{{ $i + 1 }}</span>
                <div class="min-w-0"><p class="text-sm font-bold" style="color:#222;">{{ $t }}</p><p class="text-xs" style="color:#8a7f78;">{{ $d }}</p></div>
            </div>
        @endforeach
    </div>

    {{-- Tabs + Claim all --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <nav class="vc-tabs" aria-label="Voucher types">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('vouchers.center', $key === 'all' ? [] : ['tab' => $key]) }}" class="vc-tab {{ $tab === $key ? 'is-active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                    {!! $svg($icons[$key]) !!} {{ $label }} <span class="vc-n">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        @auth
            @if ($unclaimed > 1)
                <form method="POST" action="{{ route('vouchers.claim-all', $tab === 'all' ? [] : ['tab' => $tab]) }}">@csrf
                    <button class="vc-claim-all" data-claim-all>{!! $svg('<path d="M20 6 9 17l-5-5"/>') !!} Claim all ({{ $unclaimed }})</button>
                </form>
            @endif
        @endauth
    </div>

    @if ($vouchers->isEmpty())
        {{-- Empty state --}}
        <div class="mt-5 rounded-2xl border px-6 py-14 text-center" style="border-color:#F0E4DC;background:linear-gradient(180deg,#fff 0%,#FFF8F5 100%);" data-voucher-empty>
            <div class="mx-auto flex h-20 w-32 items-center rounded-2xl bg-white shadow-sm" style="transform:rotate(-5deg);border:1px solid #FAD7C8;" aria-hidden="true">
                <span class="flex h-full w-10 items-center justify-center rounded-l-2xl" style="background:#FFF1EB;border-right:2px dashed #FAD7C8;color:#fa4e1c;">{!! $svg($icons[$tab], 'h-5 w-5') !!}</span>
                <span class="flex-1 px-3 text-left"><span class="block h-2 w-14 rounded-full" style="background:#F3EEEA;"></span><span class="mt-2 block h-2 w-9 rounded-full" style="background:#F3EEEA;"></span></span>
            </div>
            <p class="mt-6 font-display text-lg font-extrabold" style="color:#222;">{{ $emptyText[$tab][0] }}</p>
            <p class="mx-auto mt-1 max-w-md text-sm" style="color:#8a7f78;">{{ $emptyText[$tab][1] }}</p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
                <a href="{{ route('shop.index') }}" class="vc-claim px-5">Keep shopping</a>
                @auth
                    @if ($tab === 'store')
                        <a href="{{ route('stores.following') }}" class="vc-claim-all">Stores you follow</a>
                    @else
                        <a href="{{ route('vouchers.wallet') }}" class="vc-claim-all">My vouchers</a>
                    @endif
                @endauth
            </div>
        </div>
    @else
        @foreach ($sections as $kind => [$title, $hint, $list])
            @continue($list->isEmpty())
            <div class="vc-sec-head" data-voucher-section="{{ $kind }}">
                <h2>{{ $title }}</h2>
                <span class="text-xs" style="color:#8a7f78;">{{ $hint }} · {{ $list->count() }}</span>
            </div>

            {{-- Store vouchers are grouped under their store --}}
            @foreach ($kind === 'seller' ? $list->groupBy('seller_id') : collect([0 => $list]) as $sellerId => $group)
                @if ($kind === 'seller' && ($seller = $group->first()->seller))
                    @php $sapp = $seller->sellerApplication; @endphp
                    <div class="vc-store">
                        <span class="vc-store-logo">
                            @if ($sapp?->logo_path)<img src="{{ asset('storage/' . $sapp->logo_path) }}" alt="">@else{{ strtoupper(substr($seller->storeName(), 0, 1)) }}@endif
                        </span>
                        <span class="text-sm font-bold" style="color:#222;">{{ $seller->storeName() }}</span>
                        <a href="{{ route('stores.show', $seller->id) }}" class="ml-auto text-xs font-semibold hover:underline" style="color:#fa4e1c;">Visit store →</a>
                    </div>
                @endif
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($group as $v)
                        @php
                            $claimed = in_array($v->id, $claimedIds, true);
                            $usedUp  = ($used[$v->id] ?? 0) >= $v->per_user_limit;
                            $useUrl  = $v->scope === 'seller' && $v->seller_id ? route('stores.show', $v->seller_id) : route('shop.index');
                        @endphp
                        <x-voucher-ticket :kind="$v->scope" :type="$v->discount_type" :value="$v->discount_value" :max="$v->max_discount"
                                          :min-spend="$v->min_spend" :name="$v->name" :where="$v->whereText()" :ends-at="$v->ends_at"
                                          :used-pct="$v->usedPercent()" :muted="$usedUp">
                            @if ($usedUp)
                                <span class="vc-claimed" style="color:#8a7f78;">Used</span>
                            @elseif ($claimed)
                                <span class="vc-claimed">
                                    <span>✓ Claimed</span>
                                    <a href="{{ $useUrl }}">Use now</a>
                                </span>
                            @else
                                @auth
                                    <form method="POST" action="{{ route('vouchers.claim', $v) }}">@csrf
                                        <button class="vc-claim" aria-label="Claim {{ $v->name ?: $v->discountLabel() }}">Claim</button>
                                    </form>
                                @else
                                    <a href="{{ route('login') }}" class="vc-claim">Claim</a>
                                @endauth
                            @endif
                        </x-voucher-ticket>
                    @endforeach
                </div>
            @endforeach
        @endforeach

        <p class="mt-6 flex items-start gap-2 rounded-xl px-4 py-3 text-xs" style="background:#FDF8F5;color:#8a7f78;">
            {!! $svg('<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>', 'mt-px h-4 w-4 shrink-0') !!}
            One store voucher per store, plus one ALVY discount and one free-shipping voucher per checkout. Each voucher's limits are shown on it.
        </p>
    @endif
</div>
</x-layout>
