<x-courier-layout title="COD Remittance" active="rider-cod">
<div class="cx-page-head"><h1 class="font-display">COD Remittance</h1>
    <p>Hand the COD you collected to {{ $rider->companyName() }} by the end of the day. Only they can confirm it.</p></div>

<div class="cx-grid cx-cols-2" style="margin-bottom:24px;">
    <div class="card cx-stat"><p class="cx-stat-value" data-balance>₱{{ number_format($balance, 2) }}</p><p class="cx-stat-label">COD balance (not yet confirmed)</p></div>
    <div class="card cx-stat"><p class="cx-stat-value" data-pending>₱{{ number_format($pending, 2) }}</p><p class="cx-stat-label">Remittance pending confirmation</p></div>
</div>

{{-- Worked out for the rider: the COD on hand minus their unpaid pay (kept out of the cash). --}}
<form method="POST" action="{{ route('courier.rider.cod.remit') }}" class="card" style="padding:18px 20px;margin-bottom:24px;" id="remit-form" data-remit-breakdown>
    @csrf
    <input type="hidden" name="request_token" value="{{ $token }}">
    <table style="width:100%;max-width:420px;font-size:14px;">
        <tr><td style="padding:3px 0;">COD you're holding</td><td style="text-align:right;">₱{{ number_format($next['cod'], 2) }}</td></tr>
        <tr><td style="padding:3px 0;">Your pay (keep it from the cash)</td><td style="text-align:right;color:var(--green,#047857);">− ₱{{ number_format($next['pay'], 2) }}</td></tr>
        <tr style="border-top:1px solid var(--border,#e5e7eb);font-weight:800;font-size:16px;"><td style="padding:6px 0;">Hand over to {{ $rider->companyName() }}</td><td style="text-align:right;" data-remit-amount>₱{{ number_format($next['remit'], 2) }}</td></tr>
    </table>
    @if ($next['owed'] > 0)
        <p class="cx-hint" style="margin-top:6px;font-size:12px;">₱{{ number_format($next['owed'], 2) }} more of your pay is kept from your next COD.</p>
    @endif
    <button class="cx-btn cx-btn-orange" style="margin-top:12px;" @disabled($next['cod'] <= 0 || $pending > 0)>Remit ₱{{ number_format($next['remit'], 2) }}</button>
    @if ($pending > 0)<p class="cx-hint" style="margin-top:6px;font-size:12px;">You can remit again once your pending remittance is confirmed.</p>@endif
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
        <thead><tr><th>Sent</th><th>Handed over</th><th>Pay kept</th><th>Status</th><th>Shortfall</th><th>Receipt</th></tr></thead>
        <tbody>
        @forelse($remittances as $rm)
            <tr><td>{{ $rm->created_at->format('M j, g:i A') }}</td><td>₱{{ number_format($rm->amount, 2) }}</td><td>{{ (float) $rm->pay_kept > 0 ? '₱' . number_format($rm->pay_kept, 2) : '—' }}</td><td>{{ ucfirst($rm->status) }}</td><td>{{ $rm->shortfall() > 0 ? '₱' . number_format($rm->shortfall(), 2) : '—' }}</td><td><a href="{{ route('courier.rider.cod.receipt', $rm) }}" style="color:var(--accent);font-weight:600;" data-receipt-link>{{ $rm->receiptNumber() }}</a></td></tr>
        @empty
            <tr><td colspan="6" style="text-align:center;padding:24px;">No remittances yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<script>
document.getElementById('remit-form').addEventListener('submit', function (e) { if (this.dataset.sent) { e.preventDefault(); return; } this.dataset.sent = 1; });
</script>
</x-courier-layout>
