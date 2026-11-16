<x-courier-layout title="Wallet" active="rider-wallet">

@include('partials.page-tabs', ['tabs' => [['Rider pay', 'courier.rider.earnings', 'courier.rider.earnings'], ['Wallet', 'courier.rider.wallet', 'courier.rider.wallet'], ['COD remittance', 'courier.rider.cod', 'courier.rider.cod*']]])
@php
    $tz = config('app.business_timezone');
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $methods = \App\Models\PayoutAccount::METHODS;
@endphp

<div class="cx-page-head"><h1 class="font-display">Wallet</h1>
    <p>Your share of the shipping fee: {{ \App\Services\RiderDispatch::payRule() }}. A pickup is added once the hub receives the parcel,
        a prepaid delivery once it's delivered, and a COD delivery once {{ $rider->companyName() }} confirms your remittance.</p></div>

{{-- Available (can withdraw), on hold (remittance short), expected (COD not remitted yet), cash to remit (not yours). --}}
<div class="cx-grid cx-cols-2" style="margin-bottom:24px;">
    <div class="card cx-stat" style="color:#fff;border:0;background:radial-gradient(120% 160% at 100% 0, #0b4a7a 0, var(--navy) 60%);">
        <p class="cx-stat-value" style="color:#fff;" data-wallet-balance>{{ $peso($balances['available']) }}</p>
        <p class="cx-stat-label" style="color:rgba(255,255,255,.75);">Available: you can withdraw it</p>
    </div>
    <div class="card cx-stat">
        <p class="cx-stat-value" data-wallet-expected>{{ $peso($balances['expected']) }}</p>
        <p class="cx-stat-label">Expected: COD deliveries, added once your remittance is confirmed</p>
    </div>
    @if ($balances['held'] > 0)
    <div class="card cx-stat" style="border:1.5px solid #FCA5A5;">
        <p class="cx-stat-value" style="color:#B91C1C;" data-wallet-held>{{ $peso($balances['held']) }}</p>
        <p class="cx-stat-label">On hold: your last remittance was short. Hand over the gap, or {{ $rider->companyName() }} takes it from your earnings.</p>
    </div>
    @endif
    <div class="card cx-stat">
        <p class="cx-stat-value" data-wallet-cash>{{ $peso($balances['cash_to_remit']) }}</p>
        <p class="cx-stat-label">Cash to remit: COD you collected (not your pay) · <a href="{{ route('courier.rider.cod') }}">Remit</a></p>
    </div>
    @if ($toSend > 0)
    <div class="card cx-stat">
        <p class="cx-stat-value" data-wallet-to-send>{{ $peso($toSend) }}</p>
        <p class="cx-stat-label">Older jobs: waiting for {{ $rider->companyName() }} to send</p>
    </div>
    @endif
</div>

{{-- Withdraw: any amount up to the available balance, no fee, to a saved or new GCash, Maya or bank account. --}}
<div class="card" style="padding:18px 20px;margin-bottom:24px;" id="cashout">
    <h2 class="cx-section-title" style="margin-top:0;">Withdraw</h2>
    @if ($pendingCashout)
        <p data-cashout-pending>{{ $peso(abs((float) $pendingCashout->amount)) }} to <strong>{{ $pendingCashout->destination() }}</strong>
            is processing: {{ $rider->companyName() }} sends it from its bank. Requested {{ $pendingCashout->created_at->copy()->tz($tz)->format('M j, g:i A') }}.</p>
    @elseif ($balances['available'] <= 0)
        <p class="cx-hint" style="font-size:13px;">Nothing available to withdraw yet.</p>
    @else
        <form method="POST" action="{{ route('courier.rider.wallet.cashout') }}" style="display:grid;gap:10px;max-width:460px;" data-withdraw
              onsubmit="return confirm('Withdraw ₱' + this.amount.value + '? Check the account first: the money is sent there.');">
            @csrf
            <label style="font-size:13px;font-weight:600;">Amount (₱) · no fee
                <input type="number" name="amount" class="cx-input" step="0.01" min="0.01" max="{{ $balances['available'] }}" value="{{ old('amount', number_format($balances['available'], 2, '.', '')) }}" required style="width:100%;margin-top:4px;">
            </label>
            @foreach ($accounts as $a)
                <label style="display:flex;gap:8px;align-items:center;font-size:13px;">
                    <input type="radio" name="payout_account_id" value="{{ $a->id }}" @checked(old('payout_account_id', $loop->first ? $a->id : null) == $a->id)>
                    {{ $a->label() }} · {{ $a->account_name }}
                </label>
            @endforeach
            <details @if ($accounts->isEmpty() || old('account_number')) open @endif style="font-size:13px;">
                <summary style="cursor:pointer;font-weight:600;">{{ $accounts->isEmpty() ? 'Where to send it' : 'Use another account' }}</summary>
                <div style="display:grid;gap:8px;margin-top:8px;">
                    <select name="method" class="cx-input" aria-label="Send to" onchange="this.form.querySelector('[data-bank]').hidden = this.value !== 'bank'; this.form.querySelectorAll('[name=payout_account_id]').forEach(r => r.checked = false);">
                        @foreach ($methods as $k => $label)<option value="{{ $k }}" @selected(old('method', 'gcash') === $k)>{{ $label }}</option>@endforeach
                    </select>
                    <input name="bank_name" class="cx-input" placeholder="Bank name (e.g. BDO)" value="{{ old('bank_name') }}" data-bank @if (old('method') !== 'bank') hidden @endif maxlength="80">
                    <input name="account_number" class="cx-input" placeholder="Mobile number (09…) or bank account number" value="{{ old('account_number', $accounts->isEmpty() ? $lastGcash?->gcash_number : null) }}" maxlength="30"
                           oninput="this.form.querySelectorAll('[name=payout_account_id]').forEach(r => r.checked = false);">
                    <input name="account_name" class="cx-input" placeholder="Name on the account" value="{{ old('account_name', $lastGcash?->gcash_name ?? $rider->full_name) }}" maxlength="120">
                </div>
            </details>
            @if ($errors->any())<p class="cx-alert cx-alert-error">{{ $errors->first() }}</p>@endif
            <button class="cx-btn cx-btn-orange" style="justify-self:start;">Withdraw</button>
        </form>
    @endif
</div>

@if ($expected->isNotEmpty())
<h2 class="cx-section-title">Expected: waiting for your COD remittance</h2>
<div class="card cx-table-wrap" style="margin-bottom:24px;" data-expected>
    <table class="cx-table">
        <thead><tr><th>Delivered</th><th>Tracking</th><th>COD collected</th><th>Your pay</th></tr></thead>
        <tbody>
        @foreach ($expected as $j)
            <tr><td>{{ $j['at']->copy()->tz($tz)->format('M j, g:i A') }}</td><td>{{ $j['tracking'] }}</td><td>{{ $peso($j['cod']) }}</td><td>{{ $peso($j['pay']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if ($unsent->isNotEmpty())
<h2 class="cx-section-title">Older jobs not sent yet</h2>
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

<h2 class="cx-section-title">Wallet ledger</h2>
<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>When</th><th>What</th><th>Status</th><th>Amount</th></tr></thead>
        <tbody>
        @forelse ($entries as $e)
            @php $off = in_array($e->status, ['rejected', 'held'], true); @endphp
            <tr data-ledger-line="{{ $e->type }}">
                <td>{{ ($e->earned_at ?? $e->created_at)->copy()->tz($tz)->format('M j, Y g:i A') }}</td>
                <td>{{ $e->typeLabel() }}
                    @if ($e->parcel)<div style="font-size:12px;"><a href="{{ $e->source_type === 'pickup' ? route('courier.rider.pickups.show', $e->source_id) : route('courier.rider.deliveries.show', $e->source_id) }}">{{ $e->parcel->tracking_number }}</a></div>
                    @elseif ($e->note)<div style="font-size:12px;color:var(--text-muted);">{{ $e->note }}</div>@endif
                    @if ($e->type === 'cashout')<div style="font-size:12px;color:var(--text-muted);">To {{ $e->destination() }}</div>@endif</td>
                <td>@if ($e->status)<span style="font-size:12px;font-weight:600;color:{{ in_array($e->status, ['paid', 'available'], true) ? 'var(--green,#047857)' : ($off ? '#B91C1C' : '#B45309') }};">{{ $e->statusLabel() }}</span>
                    @if ($e->reference)<div style="font-size:12px;">Ref {{ $e->reference }}</div>@endif
                    @if ($e->reject_reason)<div style="font-size:12px;color:#B91C1C;">{{ $e->reject_reason }}</div>@endif
                    @else<span style="font-size:12px;color:var(--text-muted);">Sent by {{ $e->sortingCenter ? \App\Support\SortingCenters::displayName($e->sortingCenter) : 'the hub' }}</span>@endif</td>
                <td style="font-weight:700;{{ $off ? 'text-decoration:line-through;color:var(--text-muted);' : 'color:' . ((float) $e->amount >= 0 ? 'var(--green,#047857)' : 'inherit') . ';' }}">{{ (float) $e->amount >= 0 ? '+' : '−' }}{{ $peso(abs((float) $e->amount)) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">Nothing yet. Your pay shows here job by job.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if ($entries->hasPages())<div style="margin-top:12px;">{{ $entries->links() }}</div>@endif

</x-courier-layout>
