@extends('sc.layout')
@section('title', 'Commission & Earnings')
@section('icon', 'parcel')

@php
    $peso = fn ($v) => ($v < 0 ? '−₱' : '₱') . number_format(abs((float) $v), 2);
    $pct = rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%';
    $badge = ['pending' => 'badge-yellow', 'earned' => 'badge-green', 'reversed' => 'badge-gray'];
    $base = $range->query();
@endphp

@section('content')
<div class="page-header flex-between">
    <div>
        <h1>Commission &amp; Earnings</h1>
        <p class="text-sub" style="margin-top:4px;max-width:720px;">
            Your share of the shipping fee on every parcel you handle. When two hubs handle a parcel, they split the fee evenly.
            @if ($rate > 0) ALVY keeps a {{ $pct }} commission and your @else ALVY takes no part of the shipping fee; only your @endif riders' pay is deducted. What's left is your net earnings.
        </p>
    </div>
    <a href="{{ route('sc.commissions', $base + array_filter(['status' => $status]) + ['export' => 'csv']) }}" class="btn btn-ghost btn-sm">Export CSV</a>
</div>

<div class="page-body">
    <form method="GET" action="{{ route('sc.commissions') }}" class="card" style="padding:14px 18px;display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:16px;">
        <div>
            <label class="form-label" for="period">Period</label>
            <select id="period" name="period" class="form-select" onchange="this.form.querySelectorAll('[data-custom]').forEach(e => e.hidden = this.value !== 'custom')">
                @foreach (\App\Support\DateRange::PERIODS as $key => $label)
                    <option value="{{ $key }}" @selected($range->period === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div data-custom @if ($range->period !== 'custom') hidden @endif>
            <label class="form-label" for="from">From</label>
            <input type="date" id="from" name="from" value="{{ $range->from->toDateString() }}" class="form-input">
        </div>
        <div data-custom @if ($range->period !== 'custom') hidden @endif>
            <label class="form-label" for="to">To</label>
            <input type="date" id="to" name="to" value="{{ $range->to->toDateString() }}" class="form-input">
        </div>
        <div>
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">All</option>
                @foreach (\App\Services\SortingCenterCommission::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-blue btn-sm">Apply</button>
        <a href="{{ route('sc.commissions') }}" class="btn btn-ghost btn-sm">Reset</a>
        <span class="text-muted text-sm" style="margin-left:auto;align-self:center;">{{ $range->label() }}</span>
    </form>
    @if ($errors->any())
        <div class="flash flash-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <div class="stat-grid" style="margin-bottom:16px;">
        @foreach ([
            ['Shipping fees earned', $summary['fees'], $summary['parcels'] . ' ' . \Illuminate\Support\Str::plural('parcel', $summary['parcels']) . ', your share', 'fees'],
            ...($rate > 0 ? [['ALVY commission (' . $pct . ')', -$summary['commission'], 'Kept by ALVY', 'commission']] : []),
            ['Rider pay', -$summary['rider_pay'], '₱' . number_format(\App\Services\RiderDispatch::PAY_PICKUP, 0) . ' per pickup, ₱' . number_format(\App\Services\RiderDispatch::PAY_DELIVERY, 0) . ' per delivery', 'rider_pay'],
            ['Net earnings', $summary['net'], $peso($summary['earned_net']) . ' earned · ' . $peso($summary['pending_net']) . ' pending', 'net'],
        ] as [$label, $value, $note, $key])
            <div class="stat-card" @if ($key === 'net') style="border:1.5px solid {{ $value < 0 ? '#FCA5A5' : '#86EFAC' }};" @endif>
                <div class="stat-value {{ $key === 'net' ? ($value < 0 ? 'text-red' : 'text-green') : '' }}" data-total="{{ $key }}">{{ $peso($value) }}</div>
                <div class="stat-label">{{ $label }}</div>
                <div class="text-muted text-sm" style="margin-top:4px;">{{ $note }}</div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Parcel</th><th>Handled</th><th style="text-align:right;">Shipping fee</th><th style="text-align:right;">Your share</th>
                        @if ($rate > 0)<th style="text-align:right;">Commission</th>@endif<th style="text-align:right;">Rider pay</th><th style="text-align:right;">Net</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $l)
                    @php $p = $l['parcel']; $void = $l['status'] === 'reversed'; @endphp
                    <tr data-line="{{ $p->id }}" data-status="{{ $l['status'] }}">
                        <td style="white-space:nowrap;">
                            <div class="font-bold">{{ $p->tracking_number }}</div>
                            <div class="text-muted text-sm">{{ $p->order?->number() }} · {{ $p->created_at?->format('M j, Y') }}</div>
                        </td>
                        <td>{{ $l['role'] }}@if ($l['hubs'] > 1)<div class="text-muted text-sm">Split with {{ $l['hubs'] - 1 }} other {{ \Illuminate\Support\Str::plural('hub', $l['hubs'] - 1) }}</div>@endif</td>
                        <td style="text-align:right;">{{ $peso($l['fee']) }}</td>
                        <td style="text-align:right;{{ $void ? 'text-decoration:line-through;color:var(--text-muted);' : '' }}">{{ $peso($l['share']) }}</td>
                        @if ($rate > 0)<td style="text-align:right;">{{ $l['commission'] > 0 ? $peso(-$l['commission']) : '₱0.00' }}</td>@endif
                        <td style="text-align:right;">{{ $l['rider_pay'] > 0 ? $peso(-$l['rider_pay']) : '₱0.00' }}</td>
                        <td style="text-align:right;" class="font-bold {{ $l['net'] < 0 ? 'text-red' : '' }}">{{ $peso($l['net']) }}</td>
                        <td><span class="badge {{ $badge[$l['status']] }}">{{ \App\Services\SortingCenterCommission::STATUSES[$l['status']] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $rate > 0 ? 8 : 7 }}" style="text-align:center;padding:24px;color:var(--text-muted);">No parcels for this period{{ $status ? ' and status' : '' }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($items->hasPages())
        <div style="margin-top:12px;">{{ $items->links() }}</div>
    @endif
    <p class="text-muted text-sm" style="margin-top:12px;">
        Pending amounts can still change, for example when a parcel is transferred and a second hub shares the fee.
        Cancelled and refunded orders earn no fee, but rider pay already earned still counts.
    </p>
</div>
@endsection
