@extends('sc.layout')
@section('title', 'ALVY Remittance')
@section('icon', 'money')

@php
    $tz = config('app.business_timezone');
    $peso = fn ($v) => ((float) $v < 0 ? '−' : '') . '₱' . number_format(abs((float) $v), 2);
    $total = round($ready->sum('amount_due'), 2);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>ALVY Remittance</h1>
        <p class="text-muted" style="font-size:12.5px;margin-top:2px;">Send ALVY the item price of the COD orders your riders delivered, with a report of each order.
            You keep each parcel's shipping fee (30% pickup rider, 30% delivery rider, 40% the hubs). For prepaid orders ALVY already has the money,
            so it owes you their shipping fee: it is taken off the total. A COD delivery is ready once you confirm the rider's remittance.</p>
    </div>
</div>
<div class="page-body">
    @if (session('error'))<div class="flash flash-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="flash flash-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>@endif

    <div class="card" style="margin-bottom:16px;" data-ready>
        <div class="card-header"><h2>Ready to remit</h2><span class="card-meta">{{ $ready->count() }} {{ \Illuminate\Support\Str::plural('delivery', $ready->count()) }}</span></div>
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Order</th><th>Delivered</th><th>Paid by</th><th>COD collected</th><th>Shipping fee (yours)</th><th>Due to ALVY</th></tr></thead>
            <tbody>
            @forelse ($ready as $l)
                <tr data-ready-line="{{ $l['parcel_delivery_id'] }}">
                    <td><strong>{{ $l['tracking_number'] }}</strong>@if ($l['mismatch'])<div style="color:#B91C1C;font-size:11.5px;">COD should be {{ $peso($l['cod_expected']) }}</div>@endif</td>
                    <td>{{ $l['order_number'] }}</td>
                    <td style="white-space:nowrap;">{{ $l['delivered_at']?->copy()->tz($tz)->format('M j, g:i A') }}</td>
                    <td>{{ $l['cod'] ? 'COD' : 'Prepaid' }}</td>
                    <td>{{ $l['cod'] ? $peso($l['cod_collected']) : '—' }}</td>
                    <td>{{ $peso($l['shipping_fee']) }}</td>
                    <td style="font-weight:700;{{ $l['amount_due'] < 0 ? 'color:#047857;' : '' }}">{{ $peso($l['amount_due']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="padding:24px;text-align:center;color:var(--text-muted);">Nothing to remit right now.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($ready->isNotEmpty())
            <form method="POST" action="{{ route('sc.alvy-remittances.submit') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px 16px;border-top:1px solid var(--border);"
                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($total >= 0 ? 'Send the report and ' . $peso($total) . ' to ALVY?' : 'Send the report? ALVY owes you ' . $peso(-$total) . '.') }});">
                @csrf
                <strong style="font-size:15px;">{{ $total >= 0 ? 'Total to ALVY: ' . $peso($total) : 'ALVY owes you ' . $peso(-$total) }}</strong>
                @if ($total > 0)
                    <input name="deposit_reference" class="form-input" placeholder="Your deposit reference no." required minlength="4" maxlength="60" style="max-width:240px;" value="{{ old('deposit_reference') }}" aria-label="Deposit reference">
                @endif
                <button class="btn btn-blue btn-sm" type="submit">Send report{{ $total > 0 ? ' and remit' : '' }}</button>
            </form>
        @endif
    </div>

    <div class="card">
        <div class="card-header"><h2>Sent to ALVY</h2></div>
        {{-- History: your company's remittances only; filter them. --}}
        <form method="GET" action="{{ route('sc.alvy-remittances') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;padding:12px 16px;" data-alvy-filter>
            <label class="text-muted" style="font-size:12px;">Status
                <select name="status" class="form-select" style="display:block;margin-top:2px;">
                    <option value="">All</option>
                    @foreach (\App\Models\HubRemittance::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                </select></label>
            <label class="text-muted" style="font-size:12px;">From<input type="date" name="from" value="{{ request('from') }}" class="form-input" style="display:block;margin-top:2px;"></label>
            <label class="text-muted" style="font-size:12px;">To<input type="date" name="to" value="{{ request('to') }}" class="form-input" style="display:block;margin-top:2px;"></label>
            <button class="btn btn-blue btn-sm">Filter</button>
            @if (request()->hasAny(['status', 'from', 'to']))<a href="{{ route('sc.alvy-remittances') }}" class="btn btn-ghost btn-sm">Clear</a>@endif
        </form>
        <table class="data-table">
            <thead><tr><th>Reference</th><th>Sent</th><th>Lines</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($sent as $r)
                <tr data-hub-remittance="{{ $r->id }}">
                    <td><a href="{{ route('sc.alvy-remittances.show', $r) }}" class="id-link">{{ $r->reference() }}</a></td>
                    <td style="white-space:nowrap;">{{ $r->created_at->copy()->tz($tz)->format('M j, g:i A') }}</td>
                    <td>{{ $r->items_count }}</td>
                    <td>{{ $peso($r->amount) }}</td>
                    <td><span class="badge {{ $r->status === 'confirmed' ? 'badge-green' : ($r->status === 'flagged' ? 'badge-red' : '') }}">{{ $r->statusLabel() }}</span>
                        @if ($r->note)<div style="font-size:11.5px;color:#B91C1C;">{{ $r->note }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--text-muted);">Nothing sent yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($sent->hasPages())<div style="padding:12px 16px;">{{ $sent->links() }}</div>@endif
    </div>
</div>
@endsection
