@extends('sc.layout')
@section('title', 'Commission & Earnings')
@section('icon', 'parcel')

@php
    $peso = fn ($v) => ($v < 0 ? '−₱' : '₱') . number_format(abs((float) $v), 2);
    $pct = rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%';
    $badge = ['pending' => 'badge-yellow', 'earned' => 'badge-green', 'reversed' => 'badge-gray'];
    $base = $range->query();
    // Rider pay taken from net on parcels from before the 30/30/40 split (so the cards add up).
    $oldRiderPay = round($summary['fees'] - $summary['commission'] - $summary['net'], 2);
@endphp

@section('content')
<style>
    .cm-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .cm-head p { margin: 4px 0 0; font: 400 13px/1.5 Inter, system-ui, sans-serif; color: var(--text-sub); letter-spacing: 0; }
    .cm-pdf { display: inline-flex; align-items: center; gap: 7px; padding: 9px 16px; border-radius: 10px; font: 600 13px/1 Inter, system-ui, sans-serif;
              background: #fa4e1c; color: #fff !important; text-decoration: none !important; box-shadow: 0 6px 14px -8px rgba(250,78,28,.9); }
    .cm-pdf:hover { background: #e2400f; }
    .cm-how { padding: 16px 18px; margin-bottom: 16px; }
    .cm-how h2 { margin: 0 0 10px; font-size: 14px; font-weight: 700; }
    .cm-bar { display: flex; height: 30px; border-radius: 9px; overflow: hidden; font: 700 11.5px/30px Inter, system-ui, sans-serif; color: #fff; text-align: center; }
    .cm-bar span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 6px; }
    .cm-points { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 12px 0 0; padding: 0; list-style: none; font-size: 12.5px; line-height: 1.5; color: var(--text-sub); }
    .cm-points b { display: block; color: var(--text); font-size: 12.5px; }
    @media (max-width: 900px) { .cm-points { grid-template-columns: 1fr; } }
</style>

<div class="page-header cm-head">
    <div>
        <h1>Commission &amp; Earnings</h1>
        <p>Your hub's share of each parcel's shipping fee, after ALVY's commission.</p>
    </div>
    <a href="{{ route('sc.commissions', $base + array_filter(['status' => $status]) + ['export' => 'pdf']) }}" class="cm-pdf" data-keep-colors data-export-pdf>
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M12 11v6M9 14l3 3 3-3"/></svg>
        Download PDF
    </a>
</div>

<div class="page-body">
    {{-- How the shipping fee is shared: a bar and three short points instead of one long paragraph. --}}
    <div class="card cm-how" data-how-split>
        <h2>How your earnings work</h2>
        <div class="cm-bar" role="img" aria-label="Shipping fee: 30% pickup rider, 30% delivery rider, 40% hubs">
            <span style="width:30%;background:#3b82f6;" data-keep-colors>30% pickup rider</span>
            <span style="width:30%;background:#8b5cf6;" data-keep-colors>30% delivery rider</span>
            <span style="width:40%;background:#fa4e1c;" data-keep-colors>40% hubs</span>
        </div>
        <ul class="cm-points">
            <li><b>Your share</b>The hubs' 40% is shared equally by every hub the parcel passed through. With 4 hubs, each gets 10% of the fee.</li>
            <li><b>ALVY commission</b>@if ($rate > 0) ALVY keeps {{ $pct }} of your share. Net = your share − commission. @else ALVY takes no part of the shipping fee. @endif Your riders are paid from their own 30% parts, not from your share.</li>
            <li><b>Older parcels</b>Parcels from before {{ \App\Services\RiderDispatch::shareSince()->format('M j, Y') }} use the old rule: the whole fee is shared, and rider pay (₱30 a pickup, ₱50 a delivery) is taken from net.</li>
        </ul>
    </div>
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
        <button class="btn btn-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Apply</button>
        <a href="{{ route('sc.commissions') }}" class="btn btn-ghost btn-sm">Reset</a>
        <span class="text-muted text-sm" style="margin-left:auto;align-self:center;">{{ $range->label() }}</span>
    </form>
    @if ($errors->any())
        <div class="flash flash-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    {{-- Three or four cards: they share the full width (the shared grid has six columns). --}}
    <div class="stat-grid" style="margin-bottom:16px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
        @foreach ([
            ['Your share of shipping fees', $summary['fees'], $summary['parcels'] . ' ' . \Illuminate\Support\Str::plural('parcel', $summary['parcels']), 'fees'],
            ...($rate > 0 ? [['ALVY commission (' . $pct . ')', -$summary['commission'], 'Kept by ALVY', 'commission']] : []),
            ["Your riders' pay", $summary['rider_pay'], "Paid from the riders' 30% parts", 'rider_pay'],
            ['Net earnings', $summary['net'], $peso($summary['earned_net']) . ' earned · ' . $peso($summary['pending_net']) . ' pending' . ($oldRiderPay > 0 ? ' · ' . $peso(-$oldRiderPay) . ' rider pay (older parcels)' : ''), 'net'],
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
                        <td>{{ $l['role'] }}@if ($l['hubs'] > 1)<div class="text-muted text-sm">{{ $l['hubs'] }} hubs split the hubs' share</div>@endif</td>
                        <td style="text-align:right;">{{ $peso($l['fee']) }}</td>
                        <td style="text-align:right;{{ $void ? 'text-decoration:line-through;color:var(--text-muted);' : '' }}">{{ $peso($l['share']) }}</td>
                        @if ($rate > 0)<td style="text-align:right;">{{ $l['commission'] > 0 ? $peso(-$l['commission']) : '₱0.00' }}</td>@endif
                        <td style="text-align:right;">{{ $peso($l['rider_pay']) }}</td>
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
