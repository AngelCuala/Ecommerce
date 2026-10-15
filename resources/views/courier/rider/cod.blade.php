<x-courier-layout title="COD Remittance" active="rider-cod">
<div class="cx-page-head"><h1 class="font-display">COD Remittance</h1>
    <p>Hand the COD you collected to {{ $rider->companyName() }} by the end of the day. Only they can confirm it.</p></div>

<div class="cx-grid cx-cols-2" style="margin-bottom:24px;">
    <div class="card cx-stat"><p class="cx-stat-value" data-balance>₱{{ number_format($balance, 2) }}</p><p class="cx-stat-label">COD balance (not yet confirmed)</p></div>
    <div class="card cx-stat"><p class="cx-stat-value" data-pending>₱{{ number_format($pending, 2) }}</p><p class="cx-stat-label">Remittance pending confirmation</p></div>
</div>

<form method="POST" action="{{ route('courier.rider.cod.remit') }}" class="card" style="padding:18px 20px;margin-bottom:24px;display:flex;flex-wrap:wrap;gap:10px;align-items:end;" id="remit-form">
    @csrf
    <input type="hidden" name="request_token" value="{{ $token }}">
    <div><label class="cx-label" for="amount">Amount to remit (₱)</label>
        <input type="number" step="0.01" min="0.01" max="{{ $balance }}" id="amount" name="amount" class="input" value="{{ old('amount', $balance > 0 ? number_format($balance, 2, '.', '') : '') }}"></div>
    <button class="cx-btn cx-btn-orange" @disabled($balance <= 0 || $pending > 0)>Remit</button>
    @error('amount')<p class="cx-alert cx-alert-error" style="width:100%;">{{ $message }}</p>@enderror
</form>

<h2 class="cx-section-title">COD collected</h2>
<div class="card cx-table-wrap" style="margin-bottom:24px;">
    <table class="cx-table">
        <thead><tr><th>Delivered</th><th>Tracking</th><th>Order</th><th>COD</th></tr></thead>
        <tbody>
        @forelse($orders as $d)
            <tr data-cod-order="{{ $d->parcel->tracking_number }}"><td>{{ $d->delivered_at?->format('M j, g:i A') }}</td><td>{{ $d->parcel->tracking_number }}</td><td>{{ $d->parcel->order?->number() }}</td><td>₱{{ number_format($d->cod_collected, 2) }}</td></tr>
        @empty
            <tr><td colspan="4" style="text-align:center;padding:24px;">No COD collected yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<h2 class="cx-section-title">Remittances</h2>
<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>Sent</th><th>Amount</th><th>Status</th><th>Shortfall</th></tr></thead>
        <tbody>
        @forelse($remittances as $rm)
            <tr><td>{{ $rm->created_at->format('M j, g:i A') }}</td><td>₱{{ number_format($rm->amount, 2) }}</td><td>{{ ucfirst($rm->status) }}</td><td>{{ $rm->shortfall() > 0 ? '₱' . number_format($rm->shortfall(), 2) : '—' }}</td></tr>
        @empty
            <tr><td colspan="4" style="text-align:center;padding:24px;">No remittances yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<script>
document.getElementById('remit-form').addEventListener('submit', function (e) { if (this.dataset.sent) { e.preventDefault(); return; } this.dataset.sent = 1; });
</script>
</x-courier-layout>
