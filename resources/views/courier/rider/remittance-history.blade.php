<x-courier-layout title="Remittance history" active="rider-wallet">

@include('partials.page-tabs', ['tabs' => [['Rider pay', 'courier.rider.earnings', 'courier.rider.earnings'], ['Wallet', 'courier.rider.wallet', 'courier.rider.wallet'], ['COD remittance', 'courier.rider.cod', 'courier.rider.cod*']]])
@php
    $tz = config('app.business_timezone');
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
@endphp

<div class="cx-page-head"><h1 class="font-display">Remittance history</h1>
    <p>Every COD remittance you sent to {{ $rider->companyName() }}: accepted (with the cash they counted), rejected (remit it again), or still waiting.
        Only you see this. <a href="{{ route('courier.rider.cod') }}">← COD remittance</a></p></div>

{{-- Filter: status and dates. --}}
<form method="GET" action="{{ route('courier.rider.cod.history') }}" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;padding:14px 16px;margin-bottom:16px;" data-history-filter>
    <label style="font-size:12.5px;font-weight:600;">Status
        <select name="status" class="cx-input" style="display:block;margin-top:4px;">
            <option value="">All</option>
            @foreach (\App\Models\RiderRemittance::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <label style="font-size:12.5px;font-weight:600;">From<input type="date" name="from" value="{{ request('from') }}" class="cx-input" style="display:block;margin-top:4px;"></label>
    <label style="font-size:12.5px;font-weight:600;">To<input type="date" name="to" value="{{ request('to') }}" class="cx-input" style="display:block;margin-top:4px;"></label>
    <button class="cx-btn cx-btn-orange">Filter</button>
    @if (request()->hasAny(['status', 'from', 'to']))<a href="{{ route('courier.rider.cod.history') }}" class="cx-btn cx-btn-outline">Clear</a>@endif
</form>

<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>Sent</th><th>Receipt</th><th>Handed over</th><th>COD then</th><th>Status</th><th>Shortfall</th></tr></thead>
        <tbody>
        @forelse ($remittances as $rm)
            <tr data-history-row="{{ $rm->id }}">
                <td>{{ $rm->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}</td>
                <td><a href="{{ route('courier.rider.cod.receipt', $rm) }}" style="color:var(--accent);font-weight:600;">{{ $rm->receiptNumber() }}</a></td>
                <td>{{ $peso($rm->amount) }}@if ($rm->status === 'confirmed' && $rm->counted_amount !== null)<div style="font-size:12px;color:var(--text-muted);">counted by the hub</div>@endif</td>
                <td>{{ $peso($rm->expected_amount) }}</td>
                <td>
                    <span style="font-weight:700;color:{{ $rm->status === 'confirmed' ? 'var(--green,#047857)' : ($rm->status === 'rejected' ? '#B91C1C' : '#B45309') }};">{{ $rm->statusLabel() }}</span>
                    @if ($rm->status === 'confirmed')<div style="font-size:12px;color:var(--text-muted);">{{ $rm->confirmed_at?->copy()->tz($tz)->format('M j, g:i A') }}@if ($rm->confirmer) · {{ $rm->confirmer->name }}@endif</div>@endif
                    @if ($rm->status === 'rejected')<div style="font-size:12px;color:#B91C1C;">{{ $rm->reject_reason }}</div>@endif
                </td>
                <td>{{ $rm->shortfall() > 0 ? $peso($rm->shortfall()) : '—' }}@if ((float) $rm->deducted > 0)<div style="font-size:12px;color:var(--text-muted);">{{ $peso($rm->deducted) }} taken from your earnings</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;padding:24px;">{{ request()->hasAny(['status', 'from', 'to']) ? 'No remittances match the filter.' : 'No remittances yet.' }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if ($remittances->hasPages())<div style="margin-top:12px;">{{ $remittances->links() }}</div>@endif

</x-courier-layout>
