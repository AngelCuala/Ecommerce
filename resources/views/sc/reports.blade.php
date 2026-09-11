@extends('sc.layout')
@section('title', 'Reports')
@section('icon', '📊')

@section('topbar-right')
    <form method="GET" id="date-form" style="display:flex;align-items:center;gap:8px;">
        <input type="hidden" name="tab" id="form-tab" value="{{ request('tab','daily') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-input" style="width:135px;"
               onchange="document.getElementById('date-form').submit()">
        <span style="color:var(--text-muted);font-size:12px;">to</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-input" style="width:135px;"
               onchange="document.getElementById('date-form').submit()">
        <a href="{{ route('sc.reports', array_merge(request()->only('from','to','tab'), ['export'=>1])) }}"
           class="btn btn-orange">Export CSV</a>
    </form>
@endsection

@section('content')
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
    </div>

    {{-- ══════════════════════════════════════════════════════
         TAB 1 — DAILY SUMMARY
    ══════════════════════════════════════════════════════ --}}
    @if($activeTab === 'daily')

        @php $maxVal = max($dailyData->max('delivered'), 1); @endphp

        <div class="chart-placeholder">
            <div style="font-size:13px;font-weight:600;margin-bottom:14px;">Daily Delivery Overview</div>
            <div class="chart-bars">
                @foreach($dailyData->take(7) as $day)
                    <div class="chart-bar-group">
                        <div class="chart-bar"
                             style="height:{{ max(4, round(($day->delivered/$maxVal)*100)) }}%"
                             title="{{ $day->delivered }} delivered"></div>
                        <div class="chart-bar-label">{{ \Carbon\Carbon::parse($day->date)->format('m-d') }}</div>
                    </div>
                @endforeach
            </div>
            <div class="chart-legend">
                <span><span class="legend-dot" style="background:var(--accent-green)"></span>Delivered</span>
                <span><span class="legend-dot" style="background:var(--accent-red)"></span>Failed</span>
                <span><span class="legend-dot" style="background:#002b4d"></span>Returned</span>
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
                                    <div style="height:100%;border-radius:3px;width:{{ $rate }}%;background:{{ $rate>=85 ? 'var(--accent-green)' : ($rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};"></div>
                                </div>
                                <span class="{{ $rate>=85 ? 'text-green' : ($rate>=70 ? 'text-orange' : 'text-red') }} font-bold"
                                      style="{{ $rate>=70 && $rate<85 ? 'color:#fa4e1c;' : '' }}">
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
                <div class="stat-value" style="font-size:22px;color:#fa4e1c;">{{ $avgRate }}%</div>
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
                        <td><span style="color:#fa4e1c;font-weight:500;">{{ $r->area }}</span></td>
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
                                         background:{{ $r->rate>=85 ? 'var(--accent-green)' : ($r->rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};"></div>
                                </div>
                                <span style="font-weight:700;font-size:12.5px;
                                      color:{{ $r->rate>=85 ? 'var(--accent-green)' : ($r->rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};">
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
                        <span style="font-size:12px;font-weight:700;color:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};">
                            {{ $area->rate }}%
                        </span>
                    </div>
                    <div style="height:5px;background:var(--border);border-radius:3px;margin-bottom:6px;">
                        <div style="height:100%;border-radius:3px;width:{{ $area->rate }}%;
                             background:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};"></div>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);">
                        {{ $area->delivered }} delivered / {{ $area->total }}
                        @if($area->pending > 0)
                            · <span style="color:#fa4e1c;">{{ $area->pending }} pending</span>
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
                                <span style="color:#fa4e1c;font-weight:600;">{{ $area->pending }}</span>
                            @else
                                <span style="color:var(--text-muted);">0</span>
                            @endif
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="width:80px;height:5px;background:var(--border);border-radius:3px;flex-shrink:0;">
                                    <div style="height:100%;border-radius:3px;width:{{ $area->rate }}%;
                                         background:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};"></div>
                                </div>
                                <span style="font-weight:700;font-size:12.5px;
                                      color:{{ $area->rate>=85 ? 'var(--accent-green)' : ($area->rate>=70 ? '#fa4e1c' : 'var(--accent-red)') }};">
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
                        <td style="color:#fa4e1c;font-weight:600;">{{ $areaSummary->sum('pending') }}</td>
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

    @endif

</div>
@endsection
