<x-courier-layout title="Wallet" active="rider-wallet">

@include('partials.page-tabs', ['tabs' => [['Rider pay', 'courier.rider.earnings', 'courier.rider.earnings'], ['Wallet', 'courier.rider.wallet', 'courier.rider.wallet'], ['COD remittance', 'courier.rider.cod', 'courier.rider.cod*']]])
@php
    $tz = config('app.business_timezone');
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
@endphp

<div class="cx-page-head"><h1 class="font-display">Wallet</h1>
    <p>Your share of the shipping fee: ₱{{ number_format(\App\Services\RiderDispatch::PAY_PICKUP, 0) }} a pickup and ₱{{ number_format(\App\Services\RiderDispatch::PAY_DELIVERY, 0) }} a delivery.
        {{ $rider->companyName() }} sends it to this wallet.</p></div>

<div class="cx-grid cx-cols-2" style="margin-bottom:24px;">
    <div class="card cx-stat" style="color:#fff;border:0;background:radial-gradient(120% 160% at 100% 0, #0b4a7a 0, var(--navy) 60%);">
        <p class="cx-stat-value" style="color:#fff;" data-wallet-balance>{{ $peso($balance) }}</p>
        <p class="cx-stat-label" style="color:rgba(255,255,255,.75);">In your wallet</p>
    </div>
    <div class="card cx-stat">
        <p class="cx-stat-value" data-wallet-to-send>{{ $peso($toSend) }}</p>
        <p class="cx-stat-label">Earned, waiting for {{ $rider->companyName() }} to send</p>
    </div>
</div>

{{-- Cash out to GCash: the logistics company sends it, then it shows as Sent with the GCash reference. --}}
<div class="card" style="padding:18px 20px;margin-bottom:24px;" id="cashout">
    <h2 class="cx-section-title" style="margin-top:0;">Cash out to GCash</h2>
    @if ($pendingCashout)
        <p data-cashout-pending>₱{{ number_format(abs((float) $pendingCashout->amount), 2) }} to GCash <strong>{{ $pendingCashout->gcash_number }}</strong> ({{ $pendingCashout->gcash_name }})
            is waiting for {{ $rider->companyName() }} to send it. Requested {{ $pendingCashout->created_at->copy()->tz($tz)->format('M j, g:i A') }}.</p>
    @elseif ($balance < \App\Services\RiderWallet::CASHOUT_MIN)
        <p class="cx-hint" style="font-size:13px;">You can cash out once you have at least ₱{{ number_format(\App\Services\RiderWallet::CASHOUT_MIN, 2) }} in your wallet.</p>
    @else
        <form method="POST" action="{{ route('courier.rider.wallet.cashout') }}" style="display:grid;gap:10px;max-width:420px;"
              onsubmit="return confirm('Cash out to GCash ' + this.gcash_number.value + '? Check the number first: the money is sent there.');">
            @csrf
            <label style="font-size:13px;font-weight:600;">Amount (₱)
                <input type="number" name="amount" class="cx-input" step="0.01" min="{{ \App\Services\RiderWallet::CASHOUT_MIN }}" max="{{ $balance }}" value="{{ old('amount', number_format($balance, 2, '.', '')) }}" required style="width:100%;margin-top:4px;">
            </label>
            <label style="font-size:13px;font-weight:600;">GCash number
                <input type="tel" name="gcash_number" class="cx-input" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09171234567" value="{{ old('gcash_number', $lastGcash?->gcash_number) }}" required style="width:100%;margin-top:4px;">
            </label>
            <label style="font-size:13px;font-weight:600;">Name on the GCash account
                <input type="text" name="gcash_name" class="cx-input" maxlength="120" value="{{ old('gcash_name', $lastGcash?->gcash_name ?? $rider->full_name) }}" required style="width:100%;margin-top:4px;">
            </label>
            @if ($errors->any())<p class="cx-alert cx-alert-error">{{ $errors->first() }}</p>@endif
            <button class="cx-btn cx-btn-orange" style="justify-self:start;">Cash out</button>
        </form>
    @endif
</div>

@if ($unsent->isNotEmpty())
<h2 class="cx-section-title">Not sent yet</h2>
<div class="card cx-table-wrap" style="margin-bottom:24px;">
    <table class="cx-table">
        <thead><tr><th>When</th><th>Job</th><th>Tracking</th><th>Pay</th></tr></thead>
        <tbody>
        @foreach ($unsent as $j)
            <tr><td>{{ $j['at']->copy()->tz($tz)->format('M j, g:i A') }}</td><td>{{ $j['type'] }}</td><td>{{ $j['tracking'] }}</td><td>{{ $peso($j['pay']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<h2 class="cx-section-title">Wallet history</h2>
<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>When</th><th>What</th><th>From</th><th>Amount</th></tr></thead>
        <tbody>
        @forelse ($entries as $e)
            <tr>
                <td>{{ $e->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}</td>
                <td>{{ $e->typeLabel() }}<div style="font-size:12px;color:var(--text-muted);">{{ $e->note }}</div>
                    @if ($e->status)<div style="font-size:12px;font-weight:600;color:{{ $e->status === 'paid' ? 'var(--green,#047857)' : ($e->status === 'rejected' ? '#B91C1C' : '#B45309') }};">{{ $e->statusLabel() }}{{ $e->reference ? ' · ref ' . $e->reference : '' }}{{ $e->reject_reason ? ': ' . $e->reject_reason : '' }}</div>@endif</td>
                <td>{{ $e->sortingCenter ? \App\Support\SortingCenters::displayName($e->sortingCenter) : '—' }}</td>
                <td style="font-weight:700;{{ $e->status === 'rejected' ? 'text-decoration:line-through;color:var(--text-muted);' : 'color:' . ((float) $e->amount >= 0 ? 'var(--green,#047857)' : 'inherit') . ';' }}">{{ (float) $e->amount >= 0 ? '+' : '−' }}{{ $peso(abs((float) $e->amount)) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">Nothing yet. Your pay shows here once {{ $rider->companyName() }} sends it.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if ($entries->hasPages())<div style="margin-top:12px;">{{ $entries->links() }}</div>@endif

</x-courier-layout>
