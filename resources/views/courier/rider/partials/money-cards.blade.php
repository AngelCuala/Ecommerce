{{--
    Where the money is: the rider's own pay (the wallet) next to the COD cash that belongs to the hub, so the two are
    never mixed up. Remitting moves the cash to the hub; it never touches the wallet.
    @include('courier.rider.partials.money-cards', ['b' => $balances, 'pendingRemit' => $pending, 'company' => $rider->companyName()])
--}}
@php $peso = fn ($v) => '₱' . number_format((float) $v, 2); @endphp
<style>
    .mc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-bottom: 10px; }
    .mc { border-radius: 18px; padding: 18px 20px; position: relative; overflow: hidden; }
    .mc-mine { background: linear-gradient(135deg, #047857, #065f46); color: #fff; }
    .mc-hub { background: #fff; border: 2px dashed #F59E0B; color: #1f2937; }
    .mc-tag { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 3px 9px; border-radius: 999px; }
    .mc-mine .mc-tag { background: rgba(255,255,255,.18); }
    .mc-hub .mc-tag { background: #FEF3C7; color: #92400E; }
    .mc-amt { font-size: 30px; font-weight: 800; margin: 10px 0 2px; font-family: inherit; }
    .mc-sub { font-size: 12.5px; opacity: .9; }
    .mc-rows { margin-top: 12px; display: grid; gap: 4px; font-size: 12.5px; }
    .mc-rows div { display: flex; justify-content: space-between; gap: 10px; }
    .mc-mine .mc-rows { border-top: 1px solid rgba(255,255,255,.25); padding-top: 10px; }
    .mc-hub .mc-rows { border-top: 1px solid #FDE68A; padding-top: 10px; }
    .mc-note { font-size: 12.5px; color: var(--text-muted); margin: 0 0 22px; }
</style>
<div class="mc-grid" data-money-cards>
    <div class="mc mc-mine" data-money="mine">
        <span class="mc-tag">💰 Your money · Wallet</span>
        <div class="mc-amt" data-money-available data-wallet-balance>{{ $peso($b['available']) }}</div>
        <div class="mc-sub">Available: you can withdraw it</div>
        <div class="mc-rows">
            <div><span>Expected (waiting for {{ $company }} to accept your remittance)</span><b>{{ $peso($b['expected']) }}</b></div>
            @if (($b['held'] ?? 0) > 0)<div><span>On hold (your remittance was short)</span><b>{{ $peso($b['held']) }}</b></div>@endif
        </div>
    </div>
    <div class="mc mc-hub" data-money="hub">
        <span class="mc-tag">🏦 Not yours · Cash for {{ $company }}</span>
        <div class="mc-amt" data-money-cash>{{ $peso($b['cash_to_remit']) }}</div>
        <div class="mc-sub">COD you collected: hand it all to the hub</div>
        <div class="mc-rows">
            <div><span>Remitted, waiting for the hub to accept</span><b>{{ $peso($pendingRemit) }}</b></div>
        </div>
    </div>
</div>
<p class="mc-note">Remitting moves the cash to {{ $company }}. It never touches your wallet: your pay for COD deliveries goes in once they accept the remittance.</p>
