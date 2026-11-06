@extends('sc.layout')
@section('title', 'Reports')
@section('icon', 'report')

@section('topbar-right')
    <form method="GET" id="date-form" style="display:flex;align-items:center;gap:8px;">
        <input type="hidden" name="tab" id="form-tab" value="{{ request('tab','daily') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-input" style="width:135px;"
               onchange="document.getElementById('date-form').submit()">
        <span style="color:var(--text-muted);font-size:12px;">to</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-input" style="width:135px;"
               onchange="document.getElementById('date-form').submit()">
        <a href="{{ route('sc.reports', array_merge(request()->only('from','to','tab'), ['export' => 'pdf'])) }}"
           class="btn btn-orange" style="gap:6px;" data-export-pdf>
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>
            Export PDF
        </a>
    </form>
@endsection

@section('content')

@include('partials.page-tabs', ['tabs' => [['Reports', 'sc.reports', 'sc.reports'], ['Activity log', 'sc.activity', 'sc.activity']]])
<div class="page-header"><h1>Reports</h1></div>
<div class="page-body">

    {{-- ── Tab bar ─────────────────────────────────────────── --}}
    @php $activeTab = request('tab', 'daily'); @endphp
    <div class="tab-bar mb-16">
        <a href="{{ route('sc.reports', array_merge(request()->only('from','to'), ['tab'=>'daily'])) }}"
           class="tab {{ $activeTab==='daily' ? 'active' : '' }}">Daily Summary</a>
        <a href="{{ route('sc.reports', array_merge(request()->only('from','to'), ['tab'=>'rider'])) }}"
           class="tab {{ $activeTab==='rider' ? 'active' : '' }}">Rider Performance</a>
        <a href="{{ route('sc.reports', array_merge(request()->only('from','to'), ['tab'=>'area'])) }}"
           class="tab {{ $activeTab==='area' ? 'active' : '' }}">Area Summary</a>
        <a href="{{ route('sc.reports', array_merge(request()->only('from','to'), ['tab'=>'cod'])) }}"
           class="tab {{ $activeTab==='cod' ? 'active' : '' }}">COD Collections</a>
    </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 1 — DAILY SUMMARY
    ══════════════════════════════════════════════════════ --}}
    @if($activeTab === 'daily')

        @php
            // The most recent 14 days of the range, oldest on the left; each bar stacks delivered / failed / returned.
            $chartDays = $dailyData->sortBy('date')->values()->slice(-14)->values();
            $peak = max(1, (int) $chartDays->max(fn ($d) => $d->delivered + $d->failed + $d->returned));
            $step = max(1, (int) ceil($peak / 4));
            $top  = $step * 4;   // a round scale: 4 gridlines
            $segments = [['delivered', 'Delivered', '#16A34A'], ['failed', 'Failed', '#DC2626'], ['returned', 'Returned', '#D97706']];
        @endphp
        <style>
            .rc-chart { position: relative; display: grid; grid-template-columns: 28px 1fr; column-gap: 8px; }
            .rc-axis { position: relative; height: 170px; margin-top: 18px; font-size: 10.5px; color: var(--text-muted); text-align: right; }
            .rc-axis span { position: absolute; right: 0; transform: translateY(50%); }
            .rc-plot { position: relative; height: 170px; margin-top: 18px; border-bottom: 1px solid #CBD5E1; }
            .rc-grid { position: absolute; left: 0; right: 0; border-top: 1px dashed #E2E8F0; }
            .rc-cols { position: absolute; inset: 0; display: flex; align-items: flex-end; gap: 10px; padding: 0 4px; }
            .rc-col { position: relative; flex: 1; display: flex; flex-direction: column-reverse; justify-content: flex-start; height: 100%; min-width: 0; }
            .rc-stack { display: flex; flex-direction: column-reverse; width: 100%; max-width: 56px; margin: 0 auto; border-radius: 6px 6px 0 0; overflow: hidden; transition: filter .15s; }
            .rc-col:hover .rc-stack { filter: brightness(1.08); }
            .rc-seg { width: 100%; }
            .rc-total { margin: 0 auto 4px; font-size: 11px; font-weight: 700; color: var(--text); }
            .rc-labels { display: flex; gap: 10px; padding: 6px 4px 0; margin-left: 36px; }
            .rc-labels span { flex: 1; min-width: 0; text-align: center; font-size: 10.5px; color: var(--text-muted); white-space: nowrap; }
            .rc-labels span.is-busy { color: var(--text); font-weight: 600; }
        </style>

        <div class="chart-placeholder" data-daily-chart>
            <div style="display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:14px;">
                <div style="font-size:13px;font-weight:600;">Daily Delivery Overview</div>
                <div style="font-size:11.5px;color:var(--text-muted);">
                    {{ $chartDays->count() < $dailyData->count() ? 'Last ' . $chartDays->count() . ' days of the range' : $chartDays->count() . ' ' . \Illuminate\Support\Str::plural('day', $chartDays->count()) }}
                </div>
            </div>

            <div class="rc-chart">
                <div class="rc-axis" aria-hidden="true">
                    @for ($g = 0; $g <= 4; $g++)<span style="bottom:{{ $g * 25 }}%;">{{ $g * $step }}</span>@endfor
                </div>
                <div class="rc-plot">
                    @for ($g = 1; $g <= 4; $g++)<div class="rc-grid" style="bottom:{{ $g * 25 }}%;"></div>@endfor
                    <div class="rc-cols">
                        @foreach ($chartDays as $day)
                            @php $sum = $day->delivered + $day->failed + $day->returned; @endphp
                            <div class="rc-col" title="{{ \Carbon\Carbon::parse($day->date)->format('M j') }}: {{ $day->delivered }} delivered, {{ $day->failed }} failed, {{ $day->returned }} returned">
                                @if ($sum > 0)
                                    <div class="rc-stack" style="height:{{ round($sum / $top * 100, 2) }}%;">
                                        @foreach ($segments as [$key, $label, $color])
                                            @if ($day->$key > 0)<div class="rc-seg" style="height:{{ round($day->$key / $sum * 100, 2) }}%;background:{{ $color }};"></div>@endif
                                        @endforeach
                                    </div>
                                    <div class="rc-total">{{ $sum }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="rc-labels">
                @foreach ($chartDays as $day)
                    @php $sum = $day->delivered + $day->failed + $day->returned; @endphp
                    <span class="{{ $sum ? 'is-busy' : '' }}">{{ \Carbon\Carbon::parse($day->date)->format('M j') }}</span>
                @endforeach
            </div>

            <div class="chart-legend" style="margin-top:14px;">
                @foreach ($segments as [$key, $label, $color])
                    <span><span class="legend-dot" style="background:{{ $color }}"></span>{{ $label }} · {{ $chartDays->sum($key) }}</span>
                @endforeach
            </div>
        </div>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Received</th>
                        <th>Sorted</th>
                        <th style="color:var(--accent-green);">Delivered</th>
                        <th style="color:var(--accent-red);">Failed</th>
                        <th>Returned</th>
                        <th>Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($dailyData as $row)
                    @php
                        $total = $row->delivered + $row->failed + $row->returned;
                        $rate  = $total > 0 ? round(($row->delivered / $total) * 100) : 0;
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-size:12px;">{{ $row->date }}</td>
                        <td>{{ number_format($row->received) }}</td>
                        <td>{{ number_format($row->sorted) }}</td>
                        <td><span class="text-green font-bold">{{ number_format($row->delivered) }}</span></td>
                        <td><span class="text-red">{{ $row->failed }}</span></td>
                        <td>{{ $row->returned }}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:5px;background:var(--border);border-radius:3px;min-width:60px;">
                                    <div style="height:100%;border-radius:3px;width:{{ $rate }}%;background:{{ $rate>=85 ? 'var(--accent-green)' : ($rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};"></div>
                                </div>
                                <span class="{{ $rate>=85 ? 'text-green' : ($rate>=70 ? 'text-orange' : 'text-red') }} font-bold"
                                      style="{{ $rate>=70 && $rate<85 ? 'color:var(--accent-orange);' : '' }}">
                                    {{ $rate }}%
                                </span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                @if($dailyData->isEmpty())
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">No data for this period.</td></tr>
                @endif
                </tbody>
                {{-- Totals row --}}
                @if($dailyData->isNotEmpty())
                @php
                    $totDelivered = $dailyData->sum('delivered');
                    $totFailed    = $dailyData->sum('failed');
                    $totReturned  = $dailyData->sum('returned');
                    $totAll       = $totDelivered + $totFailed + $totReturned;
                    $overallRate  = $totAll > 0 ? round(($totDelivered/$totAll)*100) : 0;
                @endphp
                <tfoot>
                    <tr style="border-top:2px solid var(--border);background:var(--card-hover);">
                        <td style="font-weight:700;">Total</td>
                        <td>{{ number_format($dailyData->sum('received')) }}</td>
                        <td>{{ number_format($dailyData->sum('sorted')) }}</td>
                        <td><span class="text-green font-bold">{{ number_format($totDelivered) }}</span></td>
                        <td><span class="text-red font-bold">{{ $totFailed }}</span></td>
                        <td class="font-bold">{{ $totReturned }}</td>
                        <td>
                            <span class="{{ $overallRate>=85 ? 'text-green' : 'text-red' }} font-bold">
                                {{ $overallRate }}%
                            </span>
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 2 — RIDER PERFORMANCE
    ══════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'rider')

        {{-- Summary stats --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;">
            @php
                $totDel  = $riderPerformance->sum('delivered');
                $totFail = $riderPerformance->sum('failed');
                $totRet  = $riderPerformance->sum('returned');
                $totAll  = $riderPerformance->sum('total');
                $avgRate = $totAll > 0 ? round(($totDel/$totAll)*100) : 0;
            @endphp
            <div class="stat-card" style="padding:12px 16px;">
                <div class="stat-label" style="font-size:11px;margin-bottom:3px;">Total Deliveries</div>
                <div class="stat-value" style="font-size:22px;">{{ number_format($totAll) }}</div>
            </div>
            <div class="stat-card" style="padding:12px 16px;">
                <div class="stat-label" style="font-size:11px;margin-bottom:3px;">Delivered</div>
                <div class="stat-value" style="font-size:22px;color:var(--accent-green);">{{ number_format($totDel) }}</div>
            </div>
            <div class="stat-card" style="padding:12px 16px;">
                <div class="stat-label" style="font-size:11px;margin-bottom:3px;">Failed</div>
                <div class="stat-value" style="font-size:22px;color:var(--accent-red);">{{ $totFail }}</div>
            </div>
            <div class="stat-card" style="padding:12px 16px;">
                <div class="stat-label" style="font-size:11px;margin-bottom:3px;">Avg. Success Rate</div>
                <div class="stat-value" style="font-size:22px;color:var(--accent-orange);">{{ $avgRate }}%</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Rider Performance — {{ $from->format('M d') }} to {{ $to->format('M d, Y') }}</h2>
                <span class="card-meta">{{ $riderPerformance->count() }} riders</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Rider</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th style="color:var(--accent-green);">Delivered</th>
                        <th style="color:var(--accent-red);">Failed</th>
                        <th>Returned</th>
                        <th>Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($riderPerformance as $i => $r)
                    <tr>
                        <td style="color:var(--text-muted);">{{ $i + 1 }}</td>
                        <td>
                            <div style="font-weight:600;">{{ $r->name }}</div>
                        </td>
                        <td><span style="color:var(--accent-orange);font-weight:500;">{{ $r->area }}</span></td>
                        <td>
                            @if($r->active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-gray">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $r->total }}</td>
                        <td><span class="text-green font-bold">{{ $r->delivered }}</span></td>
                        <td><span class="text-red">{{ $r->failed }}</span></td>
                        <td>{{ $r->returned }}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="width:80px;height:5px;background:var(--border);border-radius:3px;flex-shrink:0;">
                                    <div style="height:100%;border-radius:3px;width:{{ $r->rate }}%;
                                         background:{{ $r->rate>=85 ? 'var(--accent-green)' : ($r->rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};"></div>
                                </div>
                                <span style="font-weight:700;font-size:12.5px;
                                      color:{{ $r->rate>=85 ? 'var(--accent-green)' : ($r->rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};">
                                    {{ $r->rate }}%
                                </span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted);">
                        No rider data for this period.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 3 — AREA SUMMARY
    ══════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'area')

        {{-- Area rate bar cards --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:18px;">
            @forelse($areaSummary as $area)
                <div class="stat-card" style="padding:14px 16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <span style="font-size:13px;font-weight:600;">{{ $area->name }}</span>
                        <span style="font-size:12px;font-weight:700;color:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};">
                            {{ $area->rate }}%
                        </span>
                    </div>
                    <div style="height:5px;background:var(--border);border-radius:3px;margin-bottom:6px;">
                        <div style="height:100%;border-radius:3px;width:{{ $area->rate }}%;
                             background:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};"></div>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);">
                        {{ $area->delivered }} delivered / {{ $area->total }}
                        @if($area->pending > 0)
                            · <span style="color:var(--accent-orange);">{{ $area->pending }} pending</span>
                        @endif
                    </div>
                </div>
            @empty
                <p style="color:var(--text-muted);font-size:12px;grid-column:1/-1;">No delivery areas configured yet.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Area Summary — {{ $from->format('M d') }} to {{ $to->format('M d, Y') }}</h2>
                <span class="card-meta">{{ $areaSummary->count() }} areas</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Code</th>
                        <th>Total</th>
                        <th style="color:var(--accent-green);">Delivered</th>
                        <th style="color:var(--accent-red);">Failed</th>
                        <th>Returned</th>
                        <th>Pending</th>
                        <th>Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($areaSummary as $area)
                    <tr>
                        <td style="font-weight:600;">{{ $area->name }}</td>
                        <td style="font-family:monospace;font-size:12px;color:var(--text-muted);">{{ $area->code }}</td>
                        <td>{{ $area->total }}</td>
                        <td><span class="text-green font-bold">{{ $area->delivered }}</span></td>
                        <td><span class="text-red">{{ $area->failed }}</span></td>
                        <td>{{ $area->returned }}</td>
                        <td>
                            @if($area->pending > 0)
                                <span style="color:var(--accent-orange);font-weight:600;">{{ $area->pending }}</span>
                            @else
                                <span style="color:var(--text-muted);">0</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="width:80px;height:5px;background:var(--border);border-radius:3px;flex-shrink:0;">
                                    <div style="height:100%;border-radius:3px;width:{{ $area->rate }}%;
                                         background:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};"></div>
                                </div>
                                <span style="font-weight:700;font-size:12.5px;
                                      color:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? 'var(--accent-orange)' : 'var(--accent-red)') }};">
                                    {{ $area->rate }}%
                                </span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">
                        No area data for this period.
                    </td></tr>
                @endforelse
                </tbody>
                @if($areaSummary->isNotEmpty())
                <tfoot>
                    <tr style="border-top:2px solid var(--border);background:var(--card-hover);">
                        <td style="font-weight:700;">Total</td>
                        <td></td>
                        <td class="font-bold">{{ $areaSummary->sum('total') }}</td>
                        <td><span class="text-green font-bold">{{ $areaSummary->sum('delivered') }}</span></td>
                        <td><span class="text-red font-bold">{{ $areaSummary->sum('failed') }}</span></td>
                        <td class="font-bold">{{ $areaSummary->sum('returned') }}</td>
                        <td style="color:var(--accent-orange);font-weight:600;">{{ $areaSummary->sum('pending') }}</td>
                        <td>
                            @php
                                $gt = $areaSummary->sum('total');
                                $gd = $areaSummary->sum('delivered');
                                $gr = $gt > 0 ? round(($gd/$gt)*100) : 0;
                            @endphp
                            <span class="{{ $gr>=85 ? 'text-green' : 'text-red' }} font-bold">{{ $gr }}%</span>
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

    @elseif($activeTab === 'cod')
        <div class="card">
            <div class="card-header"><h2>COD Collections — {{ $from->format('M d') }} to {{ $to->format('M d, Y') }}</h2></div>
            <table class="data-table">
                <thead><tr><th>Delivered at</th><th>Rider</th><th>Order</th><th>Tracking</th><th>COD collected</th></tr></thead>
                <tbody>
                @forelse($codRows as $c)
                    <tr data-cod-row><td>{{ $c->date }}</td><td>{{ $c->rider }}</td><td>{{ $c->order }}</td><td>{{ $c->tracking }}</td><td>₱{{ number_format($c->amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">No COD collected in this range.</td></tr>
                @endforelse
                </tbody>
                @if($codRows->isNotEmpty())
                <tfoot><tr><td colspan="4"><strong>Total</strong></td><td><strong>₱{{ number_format($codRows->sum('amount'), 2) }}</strong></td></tr></tfoot>
                @endif
            </table>
        </div>
    @endif

</div>
@endsection
