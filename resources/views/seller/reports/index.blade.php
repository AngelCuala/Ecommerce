<x-seller-layout title="Sales Reports" active="reports">
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
    $maxVal = max(1, (float) $byDate->max());
    $every  = max(1, (int) ceil($byDate->count() / 10));   // label at most ~10 bars
@endphp
<style>
    .rp-card { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; }
    .rp-label { font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; }
    .rp-preset { border: 1px solid #E7E5E4; border-radius: .375rem; padding: .35rem .7rem; font-size: .8125rem; font-weight: 500; color: #57534E; background: #fff; transition: border-color .15s, color .15s; }
    .rp-preset:hover { border-color: #fa4e1c; color: #fa4e1c; }
    .rp-preset.is-active { border-color: #fa4e1c; color: #fa4e1c; background: #FFF7F4; }
    .rp-table th { padding: .65rem 1rem; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; border-bottom: 1px solid #ECE7E3; background: #FCFBFA; }
    .rp-table td { padding: .75rem 1rem; border-bottom: 1px solid #F2EEEA; }
    .rp-table tbody tr:hover { background: #FCFBFA; }
    .rp-bar { width: 100%; border-radius: 3px 3px 0 0; background: #fa4e1c; opacity: .8; transition: opacity .15s; }
    .rp-col:hover .rp-bar { opacity: 1; }
</style>

<div class="mx-auto max-w-6xl">

    <div class="mb-5">
        <h1 class="font-display text-2xl font-bold" style="color:#222;">Sales Reports</h1>
        <p class="mt-0.5 text-sm" style="color:#8a7f78;">{{ $rangeLabel }}</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    {{-- Date range --}}
    <form method="GET" class="rp-card mb-5 flex flex-wrap items-end gap-3 px-5 py-4">
        <div>
            <label for="from" class="rp-label">From</label>
            <input type="date" id="from" name="from" value="{{ $from }}" class="input mt-1 !py-1.5 text-sm">
        </div>
        <div>
            <label for="to" class="rp-label">To</label>
            <input type="date" id="to" name="to" value="{{ $to }}" class="input mt-1 !py-1.5 text-sm">
        </div>
        <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90" style="background:#222;">Apply</button>
        <a href="{{ route('seller.reports', ['from' => $from, 'to' => $to, 'export' => 'csv']) }}" class="rp-preset">Export CSV</a>
        <a href="{{ route('seller.reports', ['from' => $from, 'to' => $to, 'export' => 'xlsx']) }}" class="rp-preset">Export XLSX</a>
        <div class="ml-auto flex flex-wrap gap-2">
            @foreach ($presets as $label => [$pFrom, $pTo])
                <a href="{{ route('seller.reports', ['from' => $pFrom, 'to' => $pTo]) }}"
                   class="rp-preset {{ $from === $pFrom && $to === $pTo ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    {{-- Summary --}}
    <div class="rp-card mb-5 grid grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Gross sales',   '₱' . number_format($totalRevenue, 2),    'Cancelled & refunded orders excluded',       '#222'],
            ['Fees & discounts', '−₱' . number_format($totalCommission + $totalSellerFunded, 2), '₱' . number_format($totalCommission, 2) . ' fees (' . $commissionRate . '%) · ₱' . number_format($totalSellerFunded, 2) . ' store vouchers & promos', '#57534E'],
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
        <div class="mb-4 flex items-baseline justify-between">
            <h2 class="font-semibold" style="color:#222;">Daily net earnings</h2>
            @if ($byDate->count())
                <span class="text-xs" style="color:#8a7f78;">Best day: {{ $byDate->sortDesc()->keys()->first() }} · ₱{{ number_format($byDate->max(), 2) }}</span>
            @endif
        </div>
        @if ($byDate->count())
            <div class="flex h-40 items-end gap-1.5 border-b" style="border-color:#ECE7E3;">
                @foreach ($byDate as $date => $amount)
                    <div class="rp-col flex h-full flex-1 flex-col justify-end" title="{{ $date }}: ₱{{ number_format($amount, 2) }}">
                        <div class="rp-bar" style="height:{{ max(2, round($amount / $maxVal * 100)) }}%;"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1.5 flex gap-1.5">
                @foreach ($byDate->keys() as $i => $date)
                    <span class="flex-1 truncate text-center text-[11px]" style="color:#a39890;">{{ $i % $every === 0 ? $date : '' }}</span>
                @endforeach
            </div>
        @else
            <p class="py-10 text-center text-sm" style="color:#8a7f78;">No sales in this period.</p>
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
