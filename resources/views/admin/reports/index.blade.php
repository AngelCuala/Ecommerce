<x-admin-layout title="Sales Reports" active="reports">

@php
    $presets = [
        'Today'        => [now()->toDateString(), now()->toDateString()],
        'Last 7 days'  => [now()->subDays(6)->toDateString(), now()->toDateString()],
        'This month'   => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        'Last month'   => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'Last 30 days' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        'This year'    => [now()->startOfYear()->toDateString(), now()->toDateString()],
    ];
    // Ranges can cover the same dates (on the 7th, "Last 7 days" is "This month"): the one clicked is the one lit,
    // and a page opened without a click lights the first match in this order.
    $isRange = fn (string $label) => isset($presets[$label]) && [$from, $to] === $presets[$label];
    $activePreset = $isRange((string) request('preset'))
        ? request('preset')
        : collect(['Today', 'This month', 'Last month', 'Last 7 days', 'Last 30 days', 'This year'])->first($isRange);
    $rangeLabel = \Carbon\Carbon::parse($from)->format('M j, Y') . ' – ' . \Carbon\Carbon::parse($to)->format('M j, Y');
    $rangeDays  = (int) \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1;
    // A seller's own report stays open when only the dates change.
    $sellerParam = $type === 'sellers' ? request('seller') : null;
    $reportTypes = ['sales' => 'Sales Summary', 'commission' => 'Commission Report', 'sellers' => 'Sales by Seller'];
@endphp
<style>
    .ar-tabs { display: flex; flex-wrap: wrap; gap: .25rem 1.25rem; padding: 0 1.25rem; border-bottom: 1px solid #E5EDF3; }
    .ar-tab { margin-bottom: -1px; padding: .85rem .125rem; border-bottom: 2px solid transparent; font-size: .875rem; font-weight: 600; color: #6b90aa; transition: color .15s; }
    .ar-tab:hover { color: #002b4d; }
    .ar-tab.is-active { border-color: #fa4e1c; color: #fa4e1c; }
    .ar-seg { display: inline-flex; flex-wrap: wrap; gap: .125rem; padding: .1875rem; border-radius: .625rem; background: #EEF3F7; }
    .ar-seg-item { padding: .35rem .75rem; border-radius: .5rem; font-size: .8125rem; font-weight: 500; white-space: nowrap; color: #47657c; transition: color .15s; }
    a.ar-seg-item:hover { color: #fa4e1c; }
    .ar-seg-item.is-active { background: #fff; font-weight: 600; color: #fa4e1c; box-shadow: 0 1px 2px rgba(0, 43, 77, .14); }
    .ar-dates { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .ar-date { display: inline-flex; align-items: center; gap: .5rem; }
    .ar-date label { font-size: .8125rem; font-weight: 500; color: #6b90aa; }
    .ar-date .input { width: 9.5rem; padding-top: .375rem; padding-bottom: .375rem; }
    .ar-apply { border-radius: .5rem; padding: .45rem 1rem; background: #002b4d; font-size: .8125rem; font-weight: 600; color: #fff; transition: opacity .15s; }
    .ar-apply:hover { opacity: .9; }
    .ar-apply:disabled { background: #EEF3F7; color: #8fa9bc; opacity: 1; cursor: default; }
    .ar-stat { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
    .ar-stat-icon { display: flex; flex: none; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: .75rem; }
    .ar-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; padding: 2rem 1rem; text-align: center; }
    .ar-empty-icon { display: flex; height: 2.75rem; width: 2.75rem; align-items: center; justify-content: center; border-radius: 9999px; background: #EEF3F7; color: #6b90aa; }
    @media (max-width: 480px) {
        .ar-date { width: 100%; }
        .ar-date label { width: 2.5rem; flex: none; }
        .ar-date .input { width: 100%; flex: 1; }
        .ar-apply { width: 100%; }
    }
</style>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="-mb-4">
        @include('partials.page-tabs', ['tabs' => [['Sales Reports', 'admin.reports.index', 'admin.reports.*'], ['Analytics', 'admin.analytics.index', 'admin.analytics.*']]])
    </div>
    <a href="{{ route('admin.reports.index', array_filter(['type' => $type, 'from' => $from, 'to' => $to, 'seller' => request('seller'), 'export' => 'pdf'])) }}"
       class="btn-outline inline-flex items-center gap-1.5 px-4 py-2 text-sm"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg> Download PDF</a>
</div>

{{-- ── Report type + date range ─────────────────────────────────── --}}
<div class="card mb-6 overflow-hidden">
    {{-- Picking a report type opens it right away (a seller's report only stays open under "Sales by Seller"). --}}
    <nav class="ar-tabs" aria-label="Report type" data-report-type>
        @foreach ($reportTypes as $value => $label)
            <a href="{{ route('admin.reports.index', array_filter(['type' => $value, 'from' => $from, 'to' => $to, 'preset' => $activePreset])) }}"
               class="ar-tab {{ $type === $value ? 'is-active' : '' }}" @if ($type === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 pt-4" data-report-range>
        <input type="hidden" name="type" value="{{ $type }}">
        @if ($sellerParam)
            <input type="hidden" name="seller" value="{{ $sellerParam }}">
        @endif
        <div class="ar-seg" role="group" aria-label="Quick date ranges">
            @foreach ($presets as $label => [$pFrom, $pTo])
                <a href="{{ route('admin.reports.index', array_filter(['type' => $type, 'seller' => $sellerParam, 'from' => $pFrom, 'to' => $pTo, 'preset' => $label])) }}"
                   class="ar-seg-item {{ $activePreset === $label ? 'is-active' : '' }}" @if ($activePreset === $label) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            @unless ($activePreset)
                <span class="ar-seg-item is-active" aria-current="true">Custom</span>
            @endunless
        </div>
        <div class="ar-dates">
            <span class="ar-date">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $from }}" max="{{ $to }}" class="input text-sm">
            </span>
            <span class="ar-date">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $to }}" min="{{ $from }}" class="input text-sm">
            </span>
            <button type="submit" class="ar-apply" data-range-apply>Apply</button>
        </div>
    </form>
    <p class="px-5 pb-4 pt-3 text-xs" style="color:#6b90aa;">{{ $rangeLabel }} · {{ $rangeDays }} {{ \Illuminate\Support\Str::plural('day', $rangeDays) }} · Cancelled orders are not counted as sales in any report.</p>
</div>
<script>
(function () {
    var form = document.querySelector('[data-report-range]'), from = form.elements.from, to = form.elements.to,
        apply = form.querySelector('[data-range-apply]'), start = [from.value, to.value].join();
    function sync() {
        from.max = to.value;
        to.min = from.value;
        apply.disabled = [from.value, to.value].join() === start || !from.value || !to.value;
    }
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    sync();
})();
</script>

{{-- ════════════════════════════════════════
     SALES SUMMARY
════════════════════════════════════════ --}}
@if($type === 'sales')

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <div class="card p-5">
            <div class="ar-stat">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Total Revenue</p>
                    <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;">₱{{ number_format($totalRevenue, 2) }}</p>
                    <p class="text-xs mt-1" style="color:#6b90aa;">Sales in this period</p>
                </div>
                <span class="ar-stat-icon" style="background:#FFF1EE;color:#fa4e1c;" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M16.5 7.5C16.5 5.6 14.5 4.5 12 4.5S7.5 5.8 7.5 8s2 3 4.5 3.5 4.5 1.5 4.5 3.8-2 3.7-4.5 3.7-4.5-1.3-4.5-3.3"/></svg></span>
            </div>
        </div>
        <div class="card p-5">
            <div class="ar-stat">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Total Orders</p>
                    <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ number_format($totalOrders) }}</p>
                    <p class="text-xs mt-1" style="color:#6b90aa;">In selected period</p>
                </div>
                <span class="ar-stat-icon" style="background:#EEF3F7;color:#002b4d;" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h15l-1.5 9h-12L6 7Zm0 0L5 3H2m7 18a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/></svg></span>
            </div>
        </div>
        <div class="card p-5">
            <div class="ar-stat">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Items Sold</p>
                    <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ number_format($totalItems) }}</p>
                    <p class="text-xs mt-1" style="color:#6b90aa;">Units across all orders</p>
                </div>
                <span class="ar-stat-icon" style="background:#EEF3F7;color:#002b4d;" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8 12 3 3 8m18 0-9 5m9-5v8l-9 5m0-8L3 8m9 5v8m-9-13v8l9 5"/></svg></span>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2 mb-6">

        {{-- Orders by status --}}
        <div class="card p-5">
            <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Orders by Status</h2>
            @if ($byStatus->isEmpty())
                <div class="ar-empty">
                    <span class="ar-empty-icon" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg></span>
                    <p class="text-sm" style="color:#6b90aa;">No orders in this period.</p>
                </div>
            @else
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Status</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Orders</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($byStatus as $row)
                    @php
                        $color = match(strtolower($row->status)) {
                            'delivered', 'completed' => '#059669',
                            'shipped'    => '#2563EB',
                            'processing' => '#fa4e1c',
                            'cancelled'  => '#DC2626',
                            default      => '#B45309',
                        };
                    @endphp
                    <tr style="border-bottom:1px solid #F5F0EB;">
                        <td class="py-2">
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background:{{ $color }}1a;color:{{ $color }};">
                                {{ $row->status }}
                            </span>
                        </td>
                        <td class="py-2 text-right font-semibold" style="color:#002b4d;">{{ $row->count }}</td>
                        <td class="py-2 text-right font-semibold" style="color:#fa4e1c;">₱{{ number_format($row->total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- Daily sales chart --}}
        <div class="card p-5">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-x-3">
                <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Daily Sales</h2>
                @if($dailySales->isNotEmpty())
                    <span class="text-xs" style="color:#6b90aa;">Best day: ₱{{ number_format($dailySales->max('total'), 2) }}</span>
                @endif
            </div>
            @if($dailySales->isEmpty())
                <div class="ar-empty">
                    <span class="ar-empty-icon" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m6 10V4m6 16v-7m4 7H2"/></svg></span>
                    <p class="text-sm" style="color:#6b90aa;">No sales in this period.</p>
                    @if ($activePreset !== 'This year')
                        <a href="{{ route('admin.reports.index', ['type' => $type, 'from' => $presets['This year'][0], 'to' => $presets['This year'][1], 'preset' => 'This year']) }}" class="text-xs font-semibold hover:underline" style="color:#fa4e1c;">See this year</a>
                    @endif
                </div>
            @else
                @php
                    $maxSale = $dailySales->max('total') ?: 1;
                    $labelEvery = max(1, (int) ceil($dailySales->count() / 10));   // about 10 date labels at most
                @endphp
                <div class="flex items-end gap-1.5" style="height:160px;" data-daily-chart>
                    @foreach($dailySales as $i => $day)
                        @php $h = max(4, round(($day->total / $maxSale) * 85)); @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-1 h-full justify-end">
                            <div class="w-full rounded-t-md"
                                 style="max-width:48px;height:{{ $h }}%;background:linear-gradient(180deg,#fb7048,#fa4e1c);min-height:4px;"
                                 title="{{ $day->day }}: ₱{{ number_format($day->total, 2) }}"></div>
                            <span class="text-[10px]" style="color:#6b90aa;white-space:nowrap;{{ $i % $labelEvery ? 'visibility:hidden;' : '' }}">{{ $day->day }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Top products --}}
    <div class="card p-5">
        <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Top Products by Revenue</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">#</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Product</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Seller</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Units</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($topProducts as $i => $item)
                    <tr style="border-bottom:1px solid #F5F0EB;">
                        <td class="py-2.5 text-xs" style="color:#6b90aa;">{{ $i + 1 }}</td>
                        <td class="py-2.5 font-medium" style="color:#002b4d;">
                            {{ $item->product->title ?? '—' }}
                        </td>
                        <td class="py-2.5 text-xs" style="color:#6b90aa;">
                            {{ $item->product->seller->name ?? '—' }}
                        </td>
                        <td class="py-2.5 text-right" style="color:#002b4d;">{{ $item->units_sold }}</td>
                        <td class="py-2.5 text-right font-semibold" style="color:#fa4e1c;">
                            ₱{{ number_format($item->revenue, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-sm" style="color:#6b90aa;">No product sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{-- ════════════════════════════════════════
     COMMISSION REPORT
════════════════════════════════════════ --}}
@elseif($type === 'commission')

    @php
        $peso = fn ($v) => '₱' . number_format((float) $v, 2);
        $effective = $totalGross > 0 ? round($totalCommission / $totalGross * 100, 1) : 0;
        $topCommission = max(0.01, (float) $bySellerCommission->max('commission'));
    @endphp

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6" data-commission-cards>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Gross Sales</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;" data-card="gross">{{ $peso($totalGross) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Items sold, before commission</p>
        </div>
        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1" style="background:#fa4e1c;"></div>
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">ALVY Commission</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;" data-card="commission">{{ $peso($totalCommission) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">ALVY's earnings for the period</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Seller Payout</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#059669;" data-card="payout">{{ $peso($totalPayout) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">What sellers keep</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Commission Rate</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ $commissionRate }}%</p>
            <p class="text-xs mt-1" style="color:#6b90aa;" data-card="effective">Collected this period: {{ $effective }}% of gross</p>
        </div>
    </div>

    {{-- Gross split: commission vs payout --}}
    @if ($totalGross > 0)
        @php $commPct = round($totalCommission / $totalGross * 100, 1); @endphp
        <div class="card p-5 mb-6" data-commission-split>
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Where the sales went</h2>
                <span class="text-xs" style="color:#6b90aa;">{{ $peso($totalGross) }} gross</span>
            </div>
            <div class="flex h-3 overflow-hidden rounded-full" style="background:#F1F5F9;">
                <div style="width:{{ $commPct }}%;background:#fa4e1c;" title="Commission {{ $commPct }}%"></div>
                <div style="width:{{ 100 - $commPct }}%;background:#10b981;" title="Seller payout {{ round(100 - $commPct, 1) }}%"></div>
            </div>
            <div class="mt-2 flex flex-wrap gap-4 text-xs" style="color:#57534E;">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#fa4e1c;"></span>ALVY commission {{ $commPct }}% · {{ $peso($totalCommission) }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#10b981;"></span>Seller payout {{ round(100 - $commPct, 1) }}% · {{ $peso($totalPayout) }}</span>
            </div>
        </div>
    @endif

    {{-- Daily commission chart --}}
    @if($dailyCommission->isNotEmpty())
    <div class="card p-5 mb-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Daily Commission</h2>
            <span class="text-xs" style="color:#6b90aa;">{{ $dailyCommission->count() }} {{ \Illuminate\Support\Str::plural('day', $dailyCommission->count()) }} with sales · best day {{ $peso($dailyCommission->max('commission')) }}</span>
        </div>
        @php $maxC = $dailyCommission->max('commission') ?: 1; @endphp
        <div class="overflow-x-auto">
            <div class="flex items-end gap-2" style="height:150px;min-width:{{ max(1, $dailyCommission->count()) * 52 }}px;">
                @foreach($dailyCommission as $day)
                    @php $h = max(4, round(($day->commission / $maxC) * 100)); @endphp
                    <div class="flex flex-1 flex-col items-center gap-1 h-full justify-end" data-day="{{ $day->day }}">
                        <span class="text-[10px] font-semibold" style="color:#002b4d;white-space:nowrap;">{{ $peso($day->commission) }}</span>
                        <div class="w-full rounded-t-md" style="max-width:36px;height:{{ $h }}%;background:linear-gradient(180deg,#fb7048,#fa4e1c);min-height:4px;"
                             title="{{ $day->day }}: commission {{ $peso($day->commission) }} · payout {{ $peso($day->payout) }}"></div>
                        <span class="text-[10px]" style="color:#6b90aa;white-space:nowrap;">{{ $day->day }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Per-seller commission table --}}
    <div class="card p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Commission by Seller</h2>
            <span class="text-xs" style="color:#6b90aa;">{{ $bySellerCommission->count() }} {{ \Illuminate\Support\Str::plural('seller', $bySellerCommission->count()) }} with sales</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">#</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Seller</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Units</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Gross Sales</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Commission</th>
                        <th class="pb-2 pl-4 text-left text-xs font-semibold" style="color:#6b90aa;min-width:150px;">Share of commission</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Seller Payout</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($bySellerCommission as $i => $row)
                    @php $share = $totalCommission > 0 ? round($row['commission'] / $totalCommission * 100, 1) : 0; @endphp
                    <tr style="border-bottom:1px solid #F5F0EB;" data-commission-row>
                        <td class="py-3 text-xs" style="color:#6b90aa;">{{ $i + 1 }}</td>
                        <td class="py-3">
                            <span class="inline-flex items-center gap-2 font-medium" style="color:#002b4d;">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" style="background:#FFF1EE;color:#fa4e1c;">{{ strtoupper(mb_substr($row['name'], 0, 1)) }}</span>
                                {{ $row['name'] }}
                            </span>
                        </td>
                        <td class="py-3 text-right" style="color:#002b4d;">{{ $row['units'] }}</td>
                        <td class="py-3 text-right" style="color:#002b4d;">{{ $peso($row['gross']) }}</td>
                        <td class="py-3 text-right font-semibold" style="color:#fa4e1c;">{{ $peso($row['commission']) }}</td>
                        <td class="py-3 pl-4">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full" style="background:#F1F5F9;">
                                    <div class="h-full rounded-full" style="width:{{ round($row['commission'] / $topCommission * 100) }}%;background:#fa4e1c;"></div>
                                </div>
                                <span class="w-12 text-right text-xs font-semibold" style="color:#57534E;" data-share>{{ $share }}%</span>
                            </div>
                        </td>
                        <td class="py-3 text-right font-semibold" style="color:#059669;">{{ $peso($row['payout']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-sm" style="color:#6b90aa;">No sales, so no commission, in this period. Try a wider date range.</td></tr>
                @endforelse
                </tbody>
                @if($bySellerCommission->isNotEmpty())
                <tfoot style="border-top:2px solid #cfdce8;">
                    <tr>
                        <td colspan="3" class="pt-3 text-xs font-bold" style="color:#002b4d;">Total</td>
                        <td class="pt-3 text-right font-bold" style="color:#002b4d;">{{ $peso($totalGross) }}</td>
                        <td class="pt-3 text-right font-bold" style="color:#fa4e1c;">{{ $peso($totalCommission) }}</td>
                        <td class="pt-3 pl-4 text-xs font-semibold" style="color:#57534E;">100%</td>
                        <td class="pt-3 text-right font-bold" style="color:#059669;">{{ $peso($totalPayout) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

@else
    @include('admin.reports._sellers')

@endif

{{-- Every seller, with a "View sales" link to their own report (below the report) --}}
@if ($type !== 'sellers' && isset($sellerBox))
    <div class="mt-6">
        @include('admin.reports._seller_box')
    </div>
@endif

</x-admin-layout>
