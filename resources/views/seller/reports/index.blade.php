<x-seller-layout title="Sales Reports" active="reports">

@include('partials.page-tabs', ['tabs' => [['Sales reports', 'seller.reports', 'seller.reports*'], ['Commission', 'seller.commissions', 'seller.commissions*']]])
@php
    $presets = [
        'Today'        => [now()->toDateString(), now()->toDateString()],
        'Last 7 days'  => [now()->subDays(6)->toDateString(), now()->toDateString()],
        'This month'   => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        'Last month'   => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'Last 30 days' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        'This year'    => [now()->startOfYear()->toDateString(), now()->toDateString()],
    ];
    $rangeLabel = \Carbon\Carbon::parse($from)->format('M j, Y') . ' – ' . \Carbon\Carbon::parse($to)->format('M j, Y');
    $rangeDays = (int) \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1;
    // Ranges can cover the same dates (on the 7th, "Last 7 days" is "This month"): the one clicked is the one lit,
    // and a page opened without a click lights the first match in this order.
    $isRange = fn (string $label) => isset($presets[$label]) && [$from, $to] === $presets[$label];
    $activePreset = $isRange((string) request('preset'))
        ? request('preset')
        : collect(['Today', 'This month', 'Last month', 'Last 7 days', 'Last 30 days', 'This year'])->first($isRange);
    // Chart scale: a round top value (₱100, ₱250, ₱500, ₱1,000 …) with lines at quarters.
    $peak   = (float) $byDate->max();
    $maxVal = 100;
    for ($step = 0; $maxVal < $peak; $step++) {
        $maxVal = [250, 500, 1000][$step % 3] * 10 ** intdiv($step, 3);   // 250, 500, 1,000, 2,500, 5,000, 10,000 …
    }
    $every  = max(1, (int) ceil($byDate->count() / 10));   // label at most ~10 bars
    $showValues = $byDate->count() <= 14;                   // amounts above the bars when there are few
    $bestLabel = $byDateUnit === 'month' ? 'Best month' : 'Best day';
@endphp
<style>
    .rp-card { background: #fff; border: 1px solid #ECE7E3; border-radius: 1rem; box-shadow: 0 1px 2px rgba(34, 34, 34, .04); }
    .rp-label { font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; }
    .rp-export { display: inline-flex; align-items: center; gap: .4rem; flex: none; border: 1px solid #E7E5E4; border-radius: .5rem; padding: .5rem .875rem; background: #fff; font-size: .8125rem; font-weight: 600; color: #44403C; transition: border-color .15s, color .15s; }
    .rp-export:hover { border-color: #fa4e1c; color: #fa4e1c; }
    .rp-seg { display: inline-flex; flex-wrap: wrap; gap: .125rem; padding: .1875rem; border-radius: .625rem; background: #F5F2EF; }
    .rp-seg-item { padding: .35rem .75rem; border-radius: .5rem; font-size: .8125rem; font-weight: 500; white-space: nowrap; color: #57534E; transition: color .15s, background .15s; }
    a.rp-seg-item:hover { color: #fa4e1c; }
    .rp-seg-item.is-active { background: #fff; font-weight: 600; color: #fa4e1c; box-shadow: 0 1px 2px rgba(34, 34, 34, .12); }
    .rp-dates { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .rp-date { display: inline-flex; align-items: center; gap: .5rem; }
    .rp-date label { font-size: .8125rem; font-weight: 500; color: #8a7f78; }
    @media (max-width: 480px) {
        .rp-date { width: 100%; }
        .rp-date label { width: 2.5rem; flex: none; }
        .rp-dates .input { width: 100%; flex: 1; }
        .rp-apply { width: 100%; }
    }
    .rp-dates .input { width: 9.5rem; padding-top: .375rem; padding-bottom: .375rem; }
    .rp-apply { border-radius: .5rem; padding: .45rem 1rem; background: #222; font-size: .8125rem; font-weight: 600; color: #fff; transition: opacity .15s; }
    .rp-apply:hover { opacity: .9; }
    .rp-apply:disabled { background: #F5F2EF; color: #a39890; opacity: 1; cursor: default; }
    .rp-link { font-size: .8125rem; font-weight: 600; color: #fa4e1c; }
    .rp-link:hover { text-decoration: underline; }
    .rp-table th { padding: .65rem 1rem; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; border-bottom: 1px solid #ECE7E3; background: #FCFBFA; }
    .rp-table td { padding: .75rem 1rem; border-bottom: 1px solid #F2EEEA; }
    .rp-table tbody tr:hover { background: #FCFBFA; }
    .rp-bar { width: 100%; border-radius: 3px 3px 0 0; background: #fa4e1c; opacity: .8; transition: opacity .15s; }
    .rp-col:hover .rp-bar { opacity: 1; }
</style>

<div class="mx-auto max-w-6xl">

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">Sales Reports</h1>
            <p class="mt-0.5 text-sm" style="color:#8a7f78;">{{ $rangeLabel }} · {{ $rangeDays }} {{ \Illuminate\Support\Str::plural('day', $rangeDays) }}</p>
        </div>
        <a href="{{ route('seller.reports', ['from' => $from, 'to' => $to, 'export' => 'pdf']) }}" class="rp-export" data-export-pdf>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>
            Export PDF
        </a>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    {{-- Date range --}}
    <form method="GET" class="rp-card mb-5 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 py-4" data-report-range>
        <div class="rp-seg" role="group" aria-label="Quick date ranges">
            @foreach ($presets as $label => [$pFrom, $pTo])
                <a href="{{ route('seller.reports', ['from' => $pFrom, 'to' => $pTo, 'preset' => $label]) }}"
                   class="rp-seg-item {{ $activePreset === $label ? 'is-active' : '' }}" @if ($activePreset === $label) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            @unless ($activePreset)
                <span class="rp-seg-item is-active" aria-current="true">Custom</span>
            @endunless
        </div>
        <div class="rp-dates">
            <span class="rp-date">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $from }}" max="{{ $to }}" class="input text-sm">
            </span>
            <span class="rp-date">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $to }}" min="{{ $from }}" class="input text-sm">
            </span>
            <button type="submit" class="rp-apply" data-range-apply>Apply</button>
        </div>
    </form>
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

    {{-- Summary --}}
    <div class="rp-card mb-5 grid grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Gross sales',   '₱' . number_format($totalRevenue, 2),    'Cancelled & refunded orders excluded',       '#222'],
            ['Fees & discounts', (($totalCommission + $totalSellerFunded) > 0 ? '−' : '') . '₱' . number_format($totalCommission + $totalSellerFunded, 2), '₱' . number_format($totalCommission, 2) . ' fees (' . $commissionRate . '%) · ₱' . number_format($totalSellerFunded, 2) . ' store vouchers & promos', '#57534E'],
            ['Net earnings',  '₱' . number_format($totalEarnings, 2),   'What you take home',                         '#047857'],
            ['Orders',        number_format($totalOrders),               $byProduct->sum('qty') . ' units sold',       '#222'],
        ] as $i => [$label, $value, $note, $color])
            <div class="px-5 py-4 {{ $i > 0 ? 'border-l' : '' }} {{ $i === 2 ? 'max-lg:border-l-0 max-lg:border-t' : '' }} {{ $i === 3 ? 'max-lg:border-t' : '' }}" style="border-color:#F2EEEA;">
                <p class="rp-label">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};">{{ $value }}</p>
                <p class="mt-0.5 text-xs" style="color:#8a7f78;">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    {{-- Daily earnings --}}
    <div class="rp-card mb-5 px-5 py-4">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-x-3">
            <h2 class="font-semibold" style="color:#222;">{{ $byDateUnit === 'month' ? 'Monthly' : 'Daily' }} net earnings</h2>
            @if ($byDate->count())
                <span class="text-xs" style="color:#8a7f78;">{{ $bestLabel }}: {{ $byDate->sortDesc()->keys()->first() }} · ₱{{ number_format($peak, 2) }}</span>
            @endif
        </div>
        @if ($byDate->count())
            <div class="flex gap-2" data-earnings-chart>
                {{-- ₱ scale --}}
                <div class="relative h-44 w-14 shrink-0 text-right text-[10px]" style="color:#a39890;">
                    @foreach ([1, .75, .5, .25, 0] as $f)
                        <span class="absolute right-0 -translate-y-1/2" style="top:{{ (1 - $f) * 100 }}%;">₱{{ number_format($maxVal * $f) }}</span>
                    @endforeach
                </div>
                <div class="min-w-0 flex-1">
                    <div class="relative h-44 border-b border-l" style="border-color:#E7E5E4;">
                        @foreach ([.25, .5, .75, 1] as $f)
                            <div class="absolute inset-x-0 border-t border-dashed" style="top:{{ (1 - $f) * 100 }}%;border-color:#F2EEEA;"></div>
                        @endforeach
                        <div class="absolute inset-0 flex items-end justify-center gap-1.5 px-1">
                            @foreach ($byDate as $date => $amount)
                                <div class="rp-col flex h-full flex-1 flex-col items-center justify-end" style="max-width:56px;" title="{{ $date }}: ₱{{ number_format($amount, 2) }}" data-bar="{{ $date }}">
                                    @if ($showValues && $amount > 0)
                                        <span class="mb-0.5 whitespace-nowrap text-[10px] font-semibold" style="color:#57534E;">₱{{ number_format($amount, $amount < 1000 ? 2 : 0) }}</span>
                                    @endif
                                    <div class="rp-bar" style="height:{{ $amount > 0 ? max(1, round($amount / $maxVal * 100, 2)) : 0 }}%;"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-1.5 flex justify-center gap-1.5 px-1">
                        @foreach ($byDate->keys() as $i => $date)
                            <span class="flex-1 truncate text-center text-[10px]" style="color:#a39890;max-width:56px;">{{ $i % $every === 0 ? $date : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="py-10 text-center">
                <p class="text-sm" style="color:#8a7f78;">No sales in this period.</p>
                @if ($activePreset !== 'This year')
                    <a href="{{ route('seller.reports', ['from' => $presets['This year'][0], 'to' => $presets['This year'][1], 'preset' => 'This year']) }}" class="rp-link mt-1 inline-block">See this year</a>
                @endif
            </div>
        @endif
    </div>

    {{-- By product --}}
    <div class="rp-card overflow-hidden">
        <div class="px-5 py-4">
            <h2 class="font-semibold" style="color:#222;">Sales by product</h2>
        </div>
        @if ($byProduct->isEmpty())
            <p class="border-t px-5 py-10 text-center text-sm" style="border-color:#F2EEEA;color:#8a7f78;">No sales in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="rp-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left">Product</th>
                            <th class="text-right">Units</th>
                            <th class="text-right">Gross</th>
                            <th class="text-right">Fees</th>
                            <th class="text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byProduct as $p)
                            <tr>
                                <td class="font-medium" style="color:#222;">{{ $p['title'] }}</td>
                                <td class="text-right" style="color:#57534E;">{{ $p['qty'] }}</td>
                                <td class="text-right" style="color:#222;">₱{{ number_format($p['revenue'], 2) }}</td>
                                <td class="text-right" style="color:#8a7f78;">−₱{{ number_format($p['revenue'] - $p['earnings'], 2) }}</td>
                                <td class="text-right font-semibold" style="color:#047857;">₱{{ number_format($p['earnings'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#FCFBFA;">
                            <td class="px-4 py-3 font-semibold" style="color:#222;">Total</td>
                            <td class="px-4 py-3 text-right font-semibold" style="color:#222;">{{ $byProduct->sum('qty') }}</td>
                            <td class="px-4 py-3 text-right font-semibold" style="color:#222;">₱{{ number_format($byProduct->sum('revenue'), 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold" style="color:#8a7f78;">−₱{{ number_format($byProduct->sum('revenue') - $byProduct->sum('earnings'), 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold" style="color:#047857;">₱{{ number_format($byProduct->sum('earnings'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
</x-seller-layout>
