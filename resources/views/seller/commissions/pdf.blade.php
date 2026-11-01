{{-- Seller commission details as a PDF (Seller\CommissionController): the lines and totals of the Commission page. --}}
@php
    $peso   = fn ($n) => '₱' . number_format((float) $n, 2);
    $rate   = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';
    $labels = \App\Services\SellerCommission::STATUSES;
    $colors = ['pending' => '#C2410C', 'finalized' => '#047857', 'refunded' => '#B91C1C', 'reversed' => '#78716C', 'unpaid' => '#1D4ED8'];
    $tz     = config('app.business_timezone');
    // The total row counts the charged lines only (pending + finalized), like the cards above.
    $charged = $rows->whereIn('status', ['pending', 'finalized']);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Commission Report</title>
    <style>
        @page { margin: 28px 32px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #222; }
        h1 { font-size: 18px; margin: 2px 0 0; color: #002b4d; }
        h2 { font-size: 12.5px; margin: 18px 0 6px; color: #002b4d; }
        .brand { color: #fa4e1c; font-weight: bold; font-size: 13px; letter-spacing: 1px; }
        .muted { color: #6b7c8a; }
        .head { border-bottom: 2px solid #fa4e1c; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { border: none; padding: 0; vertical-align: bottom; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 8.5px; text-transform: uppercase; text-align: left; padding: 6px 5px; }
        td { padding: 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; }
        tbody tr:nth-child(even) td { background: #fafafa; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; border-bottom: none; background: #fff; }
        .cards td { border: 1px solid #e5e7eb; padding: 8px 10px; width: 20%; }
        .cards .label { font-size: 8.5px; text-transform: uppercase; color: #6b7c8a; }
        .cards .value { font-size: 13px; font-weight: bold; color: #002b4d; margin-top: 2px; }
        .cards .sub { font-size: 8px; color: #6b7c8a; margin-top: 2px; }
        .status { font-weight: bold; font-size: 8.5px; }
        .empty { padding: 26px; text-align: center; color: #6b7c8a; border: 1px dashed #cbd5e1; }
        .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">ALVY SELLER CENTRE</div>
                <h1>Commission Report</h1>
                <div class="muted">{{ $store }} · {{ $range->label() }}@if ($status) · {{ $labels[$status] }} only @endif</div>
            </td>
            <td class="r muted" style="font-size:9px;">Generated {{ $generatedAt->tz($tz)->format('M j, Y g:i A') }}</td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td><div class="label">Gross sales</div><div class="value">{{ $peso($summary['gross']) }}</div><div class="sub">Pending + finalized items</div></td>
            <td><div class="label">Refunds</div><div class="value">{{ $peso($summary['refunds']) }}</div><div class="sub">No commission charged</div></td>
            <td><div class="label">Commission</div><div class="value">{{ $peso($summary['commission']) }}</div><div class="sub">{{ $peso($summary['pending_commission']) }} still pending</div></td>
            <td><div class="label">Finalized</div><div class="value">{{ $peso($summary['finalized_commission']) }}</div><div class="sub">On completed orders</div></td>
            <td><div class="label">Net earnings</div><div class="value" style="color:#047857;">{{ $peso($summary['net']) }}</div><div class="sub">Gross minus commission</div></td>
        </tr>
    </table>

    <h2>Commission by item</h2>
    @if ($rows->isEmpty())
        <div class="empty">No order lines in this period.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Order</th><th>Placed</th><th>Product</th><th class="r">Qty</th><th class="r">Gross</th>
                    <th class="r">Rate</th><th class="r">Commission</th><th class="r">Net</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['order'] }}</td>
                        <td>{{ $row['placed']?->copy()->tz($tz)->format('M j, Y') }}</td>
                        <td>{{ $row['product'] }}@if ($row['option'])<div class="muted">{{ $row['option'] }}</div>@endif</td>
                        <td class="r">{{ $row['qty'] }}</td>
                        <td class="r">{{ $peso($row['gross']) }}</td>
                        <td class="r">{{ $rate($row['rate']) }}</td>
                        <td class="r">{{ $peso($row['commission']) }}</td>
                        <td class="r">{{ $peso($row['net']) }}</td>
                        <td><span class="status" style="color:{{ $colors[$row['status']] ?? '#222' }};">{{ $labels[$row['status']] ?? $row['status'] }}</span></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total charged ({{ $charged->count() }} of {{ $rows->count() }} {{ \Illuminate\Support\Str::plural('line', $rows->count()) }}: pending + finalized)</td>
                    <td class="r">{{ $charged->sum('qty') }}</td>
                    <td class="r">{{ $peso($charged->sum('gross')) }}</td>
                    <td></td>
                    <td class="r">{{ $peso($charged->sum('commission')) }}</td>
                    <td class="r">{{ $peso($charged->sum('net')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="foot">Commission Report · {{ $store }} · {{ $range->label() }}</div>
</body>
</html>
