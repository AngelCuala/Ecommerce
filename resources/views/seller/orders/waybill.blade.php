@php
    $app = $seller->sellerApplication;
    $cod = $order->payment_method === 'COD' && $order->payment_status !== 'Paid';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Waybill {{ $tracking }} — ALVY</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #f5f5f5; font: 14px/1.4 Arial, Helvetica, sans-serif; color: #111; }
        .wb { max-width: 640px; margin: 0 auto; background: #fff; border: 2px solid #111; }
        .row { display: flex; border-bottom: 2px solid #111; }
        .row > div { flex: 1; padding: 10px 12px; }
        .row > div + div { border-left: 2px solid #111; }
        .lbl { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #555; }
        .big { font-size: 22px; font-weight: 800; letter-spacing: .04em; }
        .bars { height: 46px; margin-top: 6px; background: repeating-linear-gradient(90deg, #111 0 2px, #fff 2px 4px, #111 4px 7px, #fff 7px 9px); }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        td, th { padding: 4px 0; text-align: left; }
        .cod { font-size: 20px; font-weight: 800; }
        .actions { max-width: 640px; margin: 0 auto 12px; display: flex; gap: 8px; }
        .actions button, .actions a { padding: 8px 14px; border-radius: 6px; border: 1px solid #111; background: #111; color: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
        .actions a { background: #fff; color: #111; }
        @media print { body { background: #fff; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('seller.orders.show', $order->id) }}">Back to order</a>
</div>
<div class="wb">
    <div class="row">
        <div>
            <div class="lbl">ALVY waybill · tracking no.</div>
            <div class="big" data-tracking>{{ $tracking }}</div>
            <div class="bars" aria-hidden="true"></div>
        </div>
        <div style="flex:0 0 38%;">
            <div class="lbl">Order</div>
            <div style="font-weight:700;">{{ $order->number() }}</div>
            <div class="lbl" style="margin-top:8px;">Weight</div>
            <div style="font-weight:700;" data-weight>{{ rtrim(rtrim(number_format((float) $weight, 2), '0'), '.') }} kg</div>
            <div class="lbl" style="margin-top:8px;">Payment</div>
            <div style="font-weight:700;">{{ $order->payment_method }}</div>
        </div>
    </div>
    <div class="row">
        <div>
            <div class="lbl">Deliver to (buyer)</div>
            <div style="font-weight:700;">{{ $order->full_name }}</div>
            <div>{{ $order->phone }}</div>
            <div data-buyer-address>{{ $order->fullAddress() }}</div>
        </div>
    </div>
    <div class="row">
        <div>
            <div class="lbl">Pick up from (seller)</div>
            <div style="font-weight:700;">{{ $seller->storeName() }}</div>
            <div>{{ $app->phone ?? $seller->contact_no }}</div>
            <div data-pickup-address>{{ $app->address ?? '—' }}</div>
        </div>
    </div>
    <div class="row">
        <div>
            <div class="lbl">Contents</div>
            <table>
                @foreach ($items as $item)
                    <tr><td>{{ $item->product->title ?? 'Item' }}@if ($item->variation_name) ({{ $item->variation_name }})@endif</td><td style="text-align:right;">× {{ $item->quantity }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>
    <div class="row" style="border-bottom:0;">
        <div>
            <div class="lbl">Collect on delivery (COD)</div>
            <div class="cod" data-cod>{{ $cod ? '₱' . number_format($order->total_price, 2) : '₱0.00 — already paid' }}</div>
        </div>
        @if ($order->buyer_note)
            <div><div class="lbl">Buyer note</div><div>{{ $order->buyer_note }}</div></div>
        @endif
    </div>
</div>
</body>
</html>
