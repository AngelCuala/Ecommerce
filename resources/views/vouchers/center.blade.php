<x-layout title="Voucher Center — ALVY">
@php
    $tabs = ['all' => 'All vouchers', 'shipping' => 'Free shipping', 'discount' => 'Discounts', 'store' => 'Store vouchers'];
@endphp
<style>
    .vc-tabs { display: flex; gap: .25rem; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid #ECE7E3; }
    .vc-tabs::-webkit-scrollbar { display: none; }
    .vc-tab { position: relative; display: inline-flex; align-items: center; gap: .4rem; white-space: nowrap; padding: .7rem .9rem; font-size: .875rem; font-weight: 600; color: #8a7f78; transition: color .15s; }
    .vc-tab:hover { color: #222; }
    .vc-tab.is-active { color: #222; }
    .vc-tab.is-active::after { content: ""; position: absolute; left: .6rem; right: .6rem; bottom: -1px; height: 2px; border-radius: 2px; background: #fa4e1c; }
    .vc-n { min-width: 1.15rem; border-radius: 999px; background: #F3EEEA; padding: 0 .35rem; font-size: .6875rem; line-height: 1.15rem; text-align: center; color: #6b5d54; }
    .vc-tab.is-active .vc-n { background: #FFF1EB; color: #E0431A; }
    .vc-claim { display: inline-flex; align-items: center; justify-content: center; min-width: 4.75rem; border-radius: .4rem; padding: .45rem .8rem; font-size: .8125rem; font-weight: 700; color: #fff; background: #fa4e1c; transition: background .15s, transform .1s; }
    .vc-claim:hover { background: #e2400f; }
    .vc-claim:active { transform: scale(.97); }
    .vc-claimed { display: inline-flex; flex-direction: column; align-items: center; gap: .2rem; font-size: .75rem; font-weight: 700; color: #059669; }
    .vc-claimed a { font-weight: 600; color: #fa4e1c; }
    .vc-claimed a:hover { text-decoration: underline; }
</style>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <nav class="mb-1 text-xs" style="color:#8a7f78;"><a href="{{ route('home') }}" class="hover:underline">Home</a> / <span style="color:#222;">Voucher Center</span></nav>
            <h1 class="font-display text-2xl font-extrabold sm:text-3xl" style="color:#222;">Voucher Center</h1>
            <p class="mt-1 text-sm" style="color:#8a7f78;">Claim vouchers now and they're ready at checkout. The best ones are applied for you automatically.</p>
        </div>
        @auth
            <a href="{{ route('vouchers.wallet') }}" class="inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-2 text-sm font-semibold transition hover:bg-[#fff5f3]" style="border-color:#E6D9CF;color:#3a2f29;">
                <svg class="h-4 w-4" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                My vouchers ({{ count($claimedIds) }})
            </a>
        @endauth
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-lg border px-4 py-3 text-sm" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#047857;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-lg border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#B91C1C;">{{ session('error') }}</div>
    @endif

    <nav class="vc-tabs mt-5" aria-label="Voucher types">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('vouchers.center', $key === 'all' ? [] : ['tab' => $key]) }}" class="vc-tab {{ $tab === $key ? 'is-active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                {{ $label }} <span class="vc-n">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($vouchers->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed px-6 py-14 text-center" style="border-color:#E6D9CF;background:#fff;">
            <p class="font-semibold" style="color:#222;">No vouchers here right now</p>
            <p class="mt-1 text-sm" style="color:#8a7f78;">New vouchers from ALVY and stores show up here. Check back soon.</p>
            <a href="{{ route('home') }}" class="mt-4 inline-block rounded-lg border px-4 py-2 text-sm font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">Keep shopping</a>
        </div>
    @else
        <div class="mt-5 grid gap-3 md:grid-cols-2">
            @foreach ($vouchers as $v)
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
        <p class="mt-4 text-xs" style="color:#a39890;">One store voucher per store, plus one ALVY discount and one free-shipping voucher per checkout. Each voucher's limits are shown on it.</p>
    @endif
</div>
</x-layout>
