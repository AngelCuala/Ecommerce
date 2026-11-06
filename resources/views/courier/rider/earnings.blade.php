<x-courier-layout title="Rider Pay" active="rider-earnings">

@include('partials.page-tabs', ['tabs' => [['Rider pay', 'courier.rider.earnings', 'courier.rider.earnings'], ['COD remittance', 'courier.rider.cod', 'courier.rider.cod*']]])
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $periods = \App\Http\Controllers\Courier\RiderController::PAY_PERIODS;
    $range = $period === 'today' ? $from->format('l, M j') : $from->format('M j') . ' – ' . $to->format('M j, Y');
@endphp
<style>
    .rp-cards { display: grid; gap: 12px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 18px; }
    @media (max-width: 640px) { .rp-cards { grid-template-columns: 1fr 1fr; } .rp-cards > :first-child { grid-column: 1 / -1; } }
    .rp-card { padding: 16px 18px; }
    .rp-card.main { color: #fff; border: 0; background: radial-gradient(120% 160% at 100% 0, #0b4a7a 0, var(--navy) 60%); }
    .rp-card .lbl { font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); }
    .rp-card.main .lbl { color: rgba(255,255,255,.7); }
    .rp-card .val { margin-top: 6px; font-family: 'Nunito', sans-serif; font-size: 26px; font-weight: 800; line-height: 1; color: var(--text); }
    .rp-card.main .val { color: #fff; font-size: 30px; }
    .rp-card .sub { margin-top: 6px; font-size: 12px; color: var(--text-muted); }
    .rp-card.main .sub { color: rgba(255,255,255,.7); }
    .rp-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; margin-bottom: 14px; }
    .rp-tabs a { flex: none; border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 700; color: var(--text-muted); background: #fff; border: 1px solid var(--border); }
    .rp-tabs a.on { color: #fff; background: var(--accent); border-color: var(--accent); }
    @media (max-width: 640px) {   /* phones: the four tabs share the width */
        .rp-tabs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 4px; overflow: visible; }
        .rp-tabs a { padding: 7px 2px; font-size: 12px; text-align: center; white-space: nowrap; }
        .rp-day { grid-template-columns: 84px minmax(0, 1fr) 68px; padding: 6px 14px; }
    }
    .rp-split { display: grid; gap: 16px; }
    @media (min-width: 1100px) { .rp-split { grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr); align-items: start; } }
    .rp-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 16px 18px 6px; }
    .rp-head h2 { font-size: 15px; font-weight: 700; color: var(--text); }
    .rp-rows { padding: 4px 18px 16px; }
    .rp-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border-dim); font-size: 13.5px; color: var(--text); }
    .rp-row:last-child { border-bottom: 0; }
    .rp-row small { color: var(--text-muted); font-size: 12px; }
    .rp-row.total { font-weight: 800; font-size: 15px; }
    .rp-day { display: grid; grid-template-columns: 92px minmax(0, 1fr) 76px; align-items: center; gap: 10px; padding: 6px 18px; font-size: 12.5px; color: var(--text); }
    .rp-day .bar { height: 10px; border-radius: 999px; background: var(--border-dim); overflow: hidden; }
    .rp-day .bar span { display: block; height: 100%; border-radius: 999px; background: var(--accent); }
    .rp-day .amt { text-align: right; font-weight: 700; }
    .rp-day.today { font-weight: 700; }
    .rp-job { display: inline-flex; border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 700; }
    .rp-note { margin-top: 12px; font-size: 12px; color: var(--text-muted); }
</style>

<div class="cx-page-head">
    <h1 class="font-display">Rider Pay</h1>
    <p>₱{{ number_format(\App\Services\RiderDispatch::PAY_PICKUP, 0) }} per completed pickup · ₱{{ number_format(\App\Services\RiderDispatch::PAY_DELIVERY, 0) }} per successful delivery · ₱0 for a failed attempt. COD cash isn't part of your pay.</p>
</div>

{{-- Today / this week / this month (Philippine time) --}}
<div class="rp-cards">
    <div class="card rp-card main">
        <p class="lbl">Today</p>
        <p class="val" data-today-pay>{{ $peso($cards['today']['pay']) }}</p>
        <p class="sub">{{ $cards['today']['jobs'] }} {{ \Illuminate\Support\Str::plural('job', $cards['today']['jobs']) }} · {{ now($tz)->format('D, M j') }}</p>
    </div>
    <div class="card rp-card">
        <p class="lbl">This week</p>
        <p class="val" data-week-pay>{{ $peso($cards['week']['pay']) }}</p>
        <p class="sub">{{ $cards['week']['jobs'] }} {{ \Illuminate\Support\Str::plural('job', $cards['week']['jobs']) }} · since Monday</p>
    </div>
    <div class="card rp-card">
        <p class="lbl">This month</p>
        <p class="val" data-month-pay>{{ $peso($cards['month']['pay']) }}</p>
        <p class="sub">{{ $cards['month']['jobs'] }} {{ \Illuminate\Support\Str::plural('job', $cards['month']['jobs']) }} · {{ now($tz)->format('F') }}</p>
    </div>
</div>

<nav class="rp-tabs" aria-label="Period">
    @foreach ($periods as $key => $label)
        <a href="{{ route('courier.rider.earnings', ['period' => $key]) }}" class="{{ $period === $key ? 'on' : '' }}" @if ($period === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

<div class="rp-split">
    <div style="display:grid;gap:16px;">
        {{-- Totals for the period --}}
        <section class="card" data-breakdown>
            <div class="rp-head"><h2>{{ $periods[$period] }}</h2><span class="cx-stat-label" style="margin:0;">{{ $range }}</span></div>
            <div class="rp-rows">
                <div class="rp-row"><span>Deliveries <small>{{ $breakdown['deliveries']['count'] }} × ₱{{ number_format(\App\Services\RiderDispatch::PAY_DELIVERY, 0) }}</small></span><span>{{ $peso($breakdown['deliveries']['pay']) }}</span></div>
                <div class="rp-row"><span>Pickups <small>{{ $breakdown['pickups']['count'] }} × ₱{{ number_format(\App\Services\RiderDispatch::PAY_PICKUP, 0) }}</small></span><span>{{ $peso($breakdown['pickups']['pay']) }}</span></div>
                <div class="rp-row"><span>Failed attempts <small>{{ $breakdown['failed'] }} × ₱0</small></span><span>{{ $peso(0) }}</span></div>
                <div class="rp-row total"><span>Your pay</span><span style="color:var(--green);" data-period-pay>{{ $peso($breakdown['total']) }}</span></div>
            </div>
        </section>

        <section class="card">
            <div class="rp-rows" style="padding-top:12px;">
                <div class="rp-row"><span>Pending <small>jobs you have now, paid when done</small></span><span data-pending-pay>{{ $peso($pending) }}</span></div>
                <div class="rp-row"><span>COD collected <small>{{ $periods[$period] }} · not your pay</small></span><span>{{ $peso($breakdown['cod']) }}</span></div>
            </div>
            @if ($breakdown['cod'] > 0)
                <p class="rp-note" style="padding:0 18px 14px;margin:0;">Hand COD cash to {{ $rider->companyName() }} on <a href="{{ route('courier.rider.cod') }}" style="color:var(--accent);font-weight:600;">COD Remittance</a>.</p>
            @endif
        </section>

        {{-- Day by day --}}
        @if ($days->isNotEmpty())
            <section class="card" style="padding-bottom:10px;">
                <div class="rp-head"><h2>Day by day</h2><span class="cx-stat-label" style="margin:0;">pay per day</span></div>
                @foreach ($days as $d)
                    <div class="rp-day {{ $d['today'] ? 'today' : '' }}">
                        <span>{{ $d['label'] }}{{ $d['today'] ? ' · today' : '' }}</span>
                        <span class="bar"><span style="width:{{ $d['pay'] > 0 ? max(4, round($d['pay'] / $peak * 100)) : 0 }}%;"></span></span>
                        <span class="amt" style="color:{{ $d['pay'] > 0 ? 'var(--text)' : 'var(--text-muted)' }};">{{ $peso($d['pay']) }}</span>
                    </div>
                @endforeach
            </section>
        @endif
    </div>

    {{-- Every job in the period --}}
    <section class="card cx-table-wrap">
        <div class="rp-head" style="padding-bottom:12px;"><h2>Jobs</h2><span class="cx-stat-label" style="margin:0;">times in Philippine time</span></div>
        <table class="cx-table">
            <thead><tr><th>{{ $period === 'today' ? 'Time' : 'Date' }}</th><th>Tracking</th><th>Job</th><th>Result</th><th style="text-align:right;">Pay</th></tr></thead>
            <tbody>
            @forelse ($jobs as $j)
                @php $at = $j['at']->copy()->tz($tz); @endphp
                <tr data-pay-row="{{ $j['tracking'] }}">
                    <td style="white-space:nowrap;">{{ $period === 'today' ? $at->format('g:i A') : $at->format('M j, g:i A') }}</td>
                    <td style="font-family:ui-monospace,monospace;font-size:12px;">{{ $j['tracking'] ?? '—' }}</td>
                    <td><span class="rp-job" style="{{ $j['type'] === 'Pickup' ? 'background:var(--blue-soft);color:var(--blue);' : 'background:var(--accent-soft);color:var(--accent);' }}">{{ $j['type'] }}</span></td>
                    <td>{{ $j['result'] }}</td>
                    <td style="text-align:right;font-weight:800;white-space:nowrap;color:{{ $j['pay'] > 0 ? 'var(--green)' : 'var(--text-muted)' }};">{{ $j['pay'] > 0 ? '+' . $peso($j['pay']) : $peso(0) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted);">
                    No jobs {{ $period === 'today' ? 'yet today' : 'in this period' }}.
                    @if ($period !== 'month')<a href="{{ route('courier.rider.earnings', ['period' => 'month']) }}" style="color:var(--accent);font-weight:600;">See this month</a>@endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</div>
</x-courier-layout>
