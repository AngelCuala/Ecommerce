<x-courier-layout title="My Earnings" active="profit">
@php $peso = fn ($v) => '₱' . number_format((float) $v, 2); @endphp
<style>
    .er-hero { display: grid; gap: 20px; padding: 24px; margin-bottom: 16px; border: 0; color: #fff;
               background: radial-gradient(120% 140% at 100% 0, #0b4a7a 0, var(--navy) 55%); }
    @media (min-width: 900px) { .er-hero { grid-template-columns: 1.1fr 2fr; align-items: center; } }
    .er-hero-label { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: rgba(255,255,255,.65); }
    .er-hero-total { margin-top: 6px; font-family: 'Nunito', sans-serif; font-size: 38px; font-weight: 800; line-height: 1; }
    .er-hero-note { margin-top: 8px; font-size: 12.5px; color: rgba(255,255,255,.7); }
    .er-periods { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .er-period { border-radius: 14px; padding: 14px 16px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); }
    .er-period p:first-child { font-size: 12px; color: rgba(255,255,255,.7); }
    .er-period p:last-child { margin-top: 4px; font-family: 'Nunito', sans-serif; font-size: 20px; font-weight: 800; }
    .er-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (min-width: 900px) { .er-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .er-stats .cx-stat-value { font-size: 22px; color: var(--text); }
    .er-split { display: grid; gap: 16px; }
    @media (min-width: 1100px) { .er-split { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); align-items: start; } }
    .er-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; padding: 16px 18px 0; }
    .er-card-head h2 { font-size: 15px; font-weight: 700; color: var(--text); }
    .er-bars { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; align-items: end; height: 190px; padding: 18px; }
    .er-bar { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 6px; }
    .er-bar span.fill { width: 100%; max-width: 38px; border-radius: 8px 8px 3px 3px; background: var(--accent); min-height: 3px; }
    .er-bar span.fill.zero { background: var(--border); }
    .er-bar .amt { font-size: 10.5px; font-weight: 700; color: var(--text); white-space: nowrap; }
    .er-bar .mon { font-size: 11px; color: var(--text-muted); }
    .er-job { display: inline-flex; border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 700; }
</style>

<div class="cx-page-head">
    <h1 class="font-display">My Earnings</h1>
    <p>₱{{ number_format(\App\Services\RiderDispatch::PAY_PICKUP, 0) }} per completed pickup · ₱{{ number_format(\App\Services\RiderDispatch::PAY_DELIVERY, 0) }} per successful delivery · ₱0 for a failed attempt. COD cash you collect isn't part of your pay.</p>
</div>

{{-- Total and this month / week / today --}}
<div class="card er-hero">
    <div>
        <p class="er-hero-label">Total earned</p>
        <p class="er-hero-total" data-earn="total">{{ $peso($stats['total']) }}</p>
        <p class="er-hero-note">{{ $stats['pickups'] + $stats['deliveries'] }} paid {{ \Illuminate\Support\Str::plural('job', $stats['pickups'] + $stats['deliveries']) }}@if ($rider) · {{ $rider->companyName() }}@endif</p>
    </div>
    <div class="er-periods">
        @foreach ([['This month', 'month'], ['This week', 'week'], ['Today', 'today']] as [$label, $key])
            <div class="er-period"><p>{{ $label }}</p><p data-earn="{{ $key }}">{{ $peso($stats[$key]) }}</p></div>
        @endforeach
    </div>
</div>

<div class="er-stats">
    @foreach ([
        ['Deliveries completed', $stats['deliveries'], 'deliveries'],
        ['Pickups completed', $stats['pickups'], 'pickups'],
        ['Average per job', $peso($stats['average']), 'average'],
        ['Pending (jobs in progress)', $peso($stats['pending']), 'pending'],
    ] as [$label, $value, $key])
        <div class="card cx-stat"><p class="cx-stat-value" data-earn="{{ $key }}">{{ $value }}</p><p class="cx-stat-label">{{ $label }}</p></div>
    @endforeach
</div>

<div class="er-split">
    {{-- Last six months --}}
    <div class="card">
        <div class="er-card-head"><h2>Last 6 months</h2><span class="cx-stat-label" style="margin:0;">earned per month</span></div>
        <div class="er-bars" role="img" aria-label="Earnings for the last six months">
            @foreach ($months as $m)
                <div class="er-bar" title="{{ $m->label }} {{ $m->year }}: {{ $peso($m->total) }} from {{ $m->jobs }} {{ \Illuminate\Support\Str::plural('job', $m->jobs) }}">
                    @if ($m->total > 0)<span class="amt">{{ $peso($m->total) }}</span>@endif
                    <span class="fill {{ $m->total > 0 ? '' : 'zero' }}" style="height:{{ $m->total > 0 ? max(4, round($m->total / $peak * 100)) : 2 }}%;"></span>
                    <span class="mon">{{ $m->label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent jobs --}}
    <div class="card cx-table-wrap">
        <div class="er-card-head" style="padding-bottom:12px;"><h2>Recent jobs</h2>
            @if (\Illuminate\Support\Facades\Route::has('courier.history'))<a href="{{ route('courier.history') }}" class="cx-stat-label" style="margin:0;color:var(--accent);font-weight:600;">Delivery history →</a>@endif
        </div>
        <table class="cx-table">
            <thead><tr><th>Date</th><th>Order</th><th>Job</th><th>Result</th><th style="text-align:right;">Earned</th></tr></thead>
            <tbody>
            @forelse ($recent as $r)
                <tr data-earning-row="{{ $r['tracking'] }}">
                    <td style="white-space:nowrap;">{{ $r['at']->format('M j, Y') }}<div class="cx-stat-label" style="margin:0;">{{ $r['at']->format('g:i A') }}</div></td>
                    <td style="white-space:nowrap;">{{ $r['order'] ?? '—' }}@if ($r['tracking'])<div class="cx-stat-label" style="margin:0;font-family:ui-monospace,monospace;">{{ $r['tracking'] }}</div>@endif</td>
                    <td><span class="er-job" style="{{ $r['type'] === 'Pickup' ? 'background:var(--blue-soft);color:var(--blue);' : 'background:var(--accent-soft);color:var(--accent);' }}">{{ $r['type'] }}</span></td>
                    <td style="max-width:240px;">{{ $r['result'] }}</td>
                    <td style="text-align:right;font-weight:800;white-space:nowrap;color:{{ $r['pay'] > 0 ? 'var(--green)' : 'var(--text-muted)' }};">{{ $r['pay'] > 0 ? '+' . $peso($r['pay']) : $peso(0) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">No jobs yet. Pickups and deliveries you complete will show here with what you earned.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</x-courier-layout>
