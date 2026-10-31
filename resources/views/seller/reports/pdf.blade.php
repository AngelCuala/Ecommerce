{{-- Seller sales report as a PDF (Seller\ReportController): the same numbers as the Reports page. --}}
@php
    $peso  = fn ($n) => '₱' . number_format((float) $n, 2);
    $range = \Carbon\Carbon::parse($from)->format('M j, Y') . ' – ' . \Carbon\Carbon::parse($to)->format('M j, Y');
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        @page { margin: 28px 32px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
        h1 { font-size: 18px; margin: 2px 0 0; color: #002b4d; }
        h2 { font-size: 12.5px; margin: 18px 0 6px; color: #002b4d; }
        .brand { color: #fa4e1c; font-weight: bold; font-size: 13px; letter-spacing: 1px; }
        .muted { color: #6b7c8a; }
        .head { border-bottom: 2px solid #fa4e1c; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { border: none; padding: 0; vertical-align: bottom; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 9px; text-transform: uppercase; text-align: left; padding: 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; }
        tbody tr:nth-child(even) td { background: #fafafa; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; border-bottom: none; background: #fff; }
        .cards td { border: 1px solid #e5e7eb; padding: 8px 10px; width: 20%; }
        .cards .label { font-size: 8.5px; text-transform: uppercase; color: #6b7c8a; }
        .cards .value { font-size: 13px; font-weight: bold; color: #002b4d; margin-top: 2px; }
        .empty { padding: 26px; text-align: center; color: #6b7c8a; border: 1px dashed #cbd5e1; }
        .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">ALVY SELLER CENTRE</div>
                <h1>Sales Report</h1>
                <div class="muted">{{ $store }} · {{ $range }}</div>
            </td>
            <td class="r muted" style="font-size:9px;">Generated {{ $generatedAt->tz(config('app.business_timezone'))->format('M j, Y g:i A') }}</td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td><div class="label">Orders</div><div class="value">{{ number_format($totals['orders']) }}</div></td>
            <td><div class="label">Gross sales</div><div class="value">{{ $peso($totals['revenue']) }}</div></td>
            <td><div class="label">Your discounts</div><div class="value">{{ $peso($totals['sellerFunded']) }}</div></td>
            <td><div class="label">Commission ({{ rtrim(rtrim(number_format((float) $commissionRate, 2), '0'), '.') }}%)</div><div class="value">{{ $peso($totals['commission']) }}</div></td>
            <td><div class="label">Net earnings</div><div class="value" style="color:#047857;">{{ $peso($totals['earnings']) }}</div></td>
        </tr>
    </table>

    <h2>Sales by product</h2>
    @if ($byProduct->isEmpty())
        <div class="empty">No sales in this date range.</div>
    @else
        <table>
            <thead><tr><th>Product</th><th class="r">Units sold</th><th class="r">Gross sales</th><th class="r">Net earnings</th></tr></thead>
            <tbody>
                @foreach ($byProduct as $p)
                    <tr><td>{{ $p['title'] }}</td><td class="r">{{ number_format($p['qty']) }}</td><td class="r">{{ $peso($p['revenue']) }}</td><td class="r">{{ $peso($p['earnings']) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td>Total</td><td class="r">{{ number_format($totals['units']) }}</td><td class="r">{{ $peso($totals['revenue']) }}</td><td class="r">{{ $peso($totals['earnings']) }}</td></tr>
            </tfoot>
        </table>
    @endif

    <div class="foot">Sales Report · {{ $store }} · {{ $range }}</div>
</body>
</html>
