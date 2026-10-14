@php
    $peso  = fn ($n) => '₱' . number_format((float) $n, 2);
    $range = \Carbon\Carbon::parse($from)->format('M d, Y') . ' – ' . \Carbon\Carbon::parse($to)->format('M d, Y');
    $title = match ($type) {
        'commission' => 'Commission Report',
        'sellers'    => ($seller ?? null) ? 'Seller Sales Report — ' . $seller->name : 'Sales by Seller',
        default      => 'Sales Summary',
    };
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
        h1 { font-size: 18px; margin: 0; color: #002b4d; }
        h2 { font-size: 12.5px; margin: 18px 0 6px; color: #002b4d; }
        .brand { color: #fa4e1c; font-weight: bold; font-size: 13px; letter-spacing: 1px; }
        .muted { color: #6b7c8a; }
        .head { border-bottom: 2px solid #fa4e1c; padding-bottom: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 9.5px; text-transform: uppercase; text-align: left; padding: 6px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; border-bottom: none; }
        .cards td { border: 1px solid #e5e7eb; padding: 8px 10px; width: 25%; }
        .cards .label { font-size: 8.5px; text-transform: uppercase; color: #6b7c8a; }
        .cards .value { font-size: 14px; font-weight: bold; color: #002b4d; margin-top: 2px; }
        .orange { color: #fa4e1c; } .green { color: #059669; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="footer">
        ALVY · {{ $title }} · {{ $range }} · Generated {{ $generatedAt->format('M d, Y g:i A') }}{{ $generatedBy ? ' by ' . $generatedBy : '' }}
    </div>

    <div class="head">
        <div class="brand">ALVY</div>
        <h1>{{ $title }}</h1>
        <div class="muted">{{ $range }} · Cancelled orders are not counted as sales.</div>
    </div>

    @if ($type === 'sales')
        <table class="cards"><tr>
            <td><div class="label">Total revenue</div><div class="value orange">{{ $peso($totalRevenue) }}</div></td>
            <td><div class="label">Orders</div><div class="value">{{ number_format($totalOrders) }}</div></td>
            <td><div class="label">Items sold</div><div class="value">{{ number_format($totalItems) }}</div></td>
            <td><div class="label">Average order</div><div class="value">{{ $peso($totalOrders ? $totalRevenue / $totalOrders : 0) }}</div></td>
        </tr></table>

        <h2>Orders by status</h2>
        <table>
            <thead><tr><th>Status</th><th class="r">Orders</th><th class="r">Amount</th></tr></thead>
            <tbody>
            @forelse ($byStatus as $row)
                <tr><td>{{ $row->status }}{{ $row->status === 'Cancelled' ? ' (not counted)' : '' }}</td>
                    <td class="r">{{ $row->count }}</td><td class="r">{{ $peso($row->total) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No orders in this period.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h2>Top products</h2>
        <table>
            <thead><tr><th>#</th><th>Product</th><th class="r">Units sold</th><th class="r">Revenue</th></tr></thead>
            <tbody>
            @forelse ($topProducts as $i => $row)
                <tr><td>{{ $i + 1 }}</td><td>{{ $row->product->title ?? 'Removed product' }}</td>
                    <td class="r">{{ $row->units_sold }}</td><td class="r">{{ $peso($row->revenue) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">No sales in this period.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h2>Daily sales</h2>
        <table>
            <thead><tr><th>Day</th><th class="r">Sales</th></tr></thead>
            <tbody>
            @forelse ($dailySales as $row)
                <tr><td>{{ \Carbon\Carbon::parse($row->day_date)->format('D, M d, Y') }}</td><td class="r">{{ $peso($row->total) }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No sales in this period.</td></tr>
            @endforelse
            </tbody>
            @if ($dailySales->isNotEmpty())
                <tfoot><tr><td>Total</td><td class="r">{{ $peso($dailySales->sum('total')) }}</td></tr></tfoot>
            @endif
        </table>

    @elseif ($type === 'commission')
        <table class="cards"><tr>
            <td><div class="label">Commission earned</div><div class="value orange">{{ $peso($totalCommission) }}</div></td>
            <td><div class="label">Seller payouts</div><div class="value green">{{ $peso($totalPayout) }}</div></td>
            <td><div class="label">Gross sales</div><div class="value">{{ $peso($totalCommission + $totalPayout) }}</div></td>
            <td><div class="label">Commission rate</div><div class="value">{{ $commissionRate }}%</div></td>
        </tr></table>

        <h2>Commission by seller</h2>
        <table>
            <thead><tr><th>#</th><th>Seller</th><th class="r">Units</th><th class="r">Gross</th><th class="r">Commission</th><th class="r">Payout</th></tr></thead>
            <tbody>
            @forelse ($bySellerCommission as $i => $row)
                <tr><td>{{ $i + 1 }}</td><td>{{ $row['name'] }}</td><td class="r">{{ $row['units'] }}</td>
                    <td class="r">{{ $peso($row['gross']) }}</td><td class="r orange">{{ $peso($row['commission']) }}</td>
                    <td class="r green">{{ $peso($row['payout']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="muted">No commission in this period.</td></tr>
            @endforelse
            </tbody>
            @if ($bySellerCommission->isNotEmpty())
                <tfoot><tr><td colspan="2">Total</td><td class="r">{{ $bySellerCommission->sum('units') }}</td>
                    <td class="r">{{ $peso($bySellerCommission->sum('gross')) }}</td>
                    <td class="r">{{ $peso($totalCommission) }}</td><td class="r">{{ $peso($totalPayout) }}</td></tr></tfoot>
            @endif
        </table>

        <h2>Daily commission</h2>
        <table>
            <thead><tr><th>Day</th><th class="r">Commission</th><th class="r">Seller payout</th></tr></thead>
            <tbody>
            @forelse ($dailyCommission as $row)
                <tr><td>{{ \Carbon\Carbon::parse($row->day_date)->format('D, M d, Y') }}</td>
                    <td class="r">{{ $peso($row->commission) }}</td><td class="r">{{ $peso($row->payout) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No commission in this period.</td></tr>
            @endforelse
            </tbody>
        </table>

    @elseif (! $seller)
        <table class="cards"><tr>
            <td><div class="label">Gross sales</div><div class="value orange">{{ $peso($sellers->sum('gross')) }}</div></td>
            <td><div class="label">Orders</div><div class="value">{{ number_format($sellers->sum('orders')) }}</div></td>
            <td><div class="label">ALVY commission</div><div class="value">{{ $peso($sellers->sum('commission')) }}</div></td>
            <td><div class="label">Seller earnings</div><div class="value green">{{ $peso($sellers->sum('payout')) }}</div></td>
        </tr></table>

        <h2>Sales by seller</h2>
        <table>
            <thead><tr><th>#</th><th>Seller</th><th class="r">Orders</th><th class="r">Units</th><th class="r">Gross</th><th class="r">Commission</th><th class="r">Earnings</th></tr></thead>
            <tbody>
            @forelse ($sellers as $i => $row)
                <tr><td>{{ $i + 1 }}</td>
                    <td>{{ $row->name }}@if ($row->shop)<br><span class="muted">{{ $row->shop }}</span>@endif</td>
                    <td class="r">{{ $row->orders }}</td><td class="r">{{ $row->units }}</td>
                    <td class="r">{{ $peso($row->gross) }}</td><td class="r orange">{{ $peso($row->commission) }}</td>
                    <td class="r green">{{ $peso($row->payout) }}</td></tr>
            @empty
                <tr><td colspan="7" class="muted">No sellers yet.</td></tr>
            @endforelse
            </tbody>
            @if ($sellers->isNotEmpty())
                <tfoot><tr><td colspan="2">Total</td><td class="r">{{ $sellers->sum('orders') }}</td><td class="r">{{ $sellers->sum('units') }}</td>
                    <td class="r">{{ $peso($sellers->sum('gross')) }}</td><td class="r">{{ $peso($sellers->sum('commission')) }}</td>
                    <td class="r">{{ $peso($sellers->sum('payout')) }}</td></tr></tfoot>
            @endif
        </table>

    @else
        <div class="muted" style="margin-bottom:8px;">{{ $seller->shop ? $seller->shop . ' · ' : '' }}{{ $seller->email }}</div>
        <table class="cards"><tr>
            <td><div class="label">Gross sales</div><div class="value orange">{{ $peso($seller->gross) }}</div></td>
            <td><div class="label">Orders · Units</div><div class="value">{{ $seller->orders }} · {{ $seller->units }}</div></td>
            <td><div class="label">ALVY commission</div><div class="value">{{ $peso($seller->commission) }}</div></td>
            <td><div class="label">Seller earnings</div><div class="value green">{{ $peso($seller->payout) }}</div></td>
        </tr></table>

        <h2>Products sold</h2>
        <table>
            <thead><tr><th>Code</th><th>Product</th><th class="r">Units</th><th class="r">Gross</th><th class="r">Commission</th><th class="r">Earnings</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr><td>{{ $p->product_code ?: '—' }}</td><td>{{ $p->title }}</td><td class="r">{{ $p->units }}</td>
                    <td class="r">{{ $peso($p->gross) }}</td><td class="r orange">{{ $peso($p->commission) }}</td>
                    <td class="r green">{{ $peso($p->payout) }}</td></tr>
            @empty
                <tr><td colspan="6" class="muted">No sales in this period.</td></tr>
            @endforelse
            </tbody>
        </table>

        <h2>Sales per day</h2>
        <table>
            <thead><tr><th>Day</th><th class="r">Orders</th><th class="r">Gross</th></tr></thead>
            <tbody>
            @forelse ($daily as $d)
                <tr><td>{{ \Carbon\Carbon::parse($d->day)->format('D, M d, Y') }}</td><td class="r">{{ $d->orders }}</td><td class="r">{{ $peso($d->gross) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No sales in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
