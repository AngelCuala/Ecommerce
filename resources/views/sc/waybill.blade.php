@php
    use App\Support\Barcode;
    // Phone numbers are partly hidden on the printed label (like Shopee): 0917***4567.
    $mask = fn (?string $phone) => $phone ? (strlen($phone) > 7 ? substr($phone, 0, 4) . str_repeat('*', max(0, strlen($phone) - 8)) . substr($phone, -4) : $phone) : '—';
    $kg   = fn ($w) => $w ? rtrim(rtrim(number_format((float) $w, 2), '0'), '.') . ' kg' : '—';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $parcels->count() === 1 ? 'Waybill ' . $parcels->first()->waybill_number : $parcels->count() . ' waybills' }} — ALVY</title>
    <style>
        @page { size: 4in 6in; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eceae7; font: 11px/1.3 Arial, Helvetica, sans-serif; color: #000; }
        .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 16px; background: #002b4d; color: #fff; font-size: 13px; }
        .bar strong { margin-right: auto; }
        .bar button, .bar a { border: 0; border-radius: 6px; padding: 7px 14px; font: 600 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
        .bar button { background: #fa4e1c; color: #fff; }
        .bar a { background: rgba(255,255,255,.15); color: #fff; }
        .sheet { display: flex; flex-wrap: wrap; justify-content: center; gap: 18px; padding: 18px; }

        /* One 4 × 6 in label */
        .wb { width: 4in; height: 6in; display: flex; flex-direction: column; overflow: hidden; background: #fff; border: 1.5px solid #000; page-break-after: always; break-after: page; }
        .sec { border-bottom: 1.5px solid #000; padding: 5px 8px; }
        .row { display: flex; }
        .row > .sec + .sec { border-left: 1.5px solid #000; }
        .lbl { font-size: 7.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .head { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
        .brand { display: flex; align-items: center; gap: 5px; font-size: 15px; font-weight: 900; letter-spacing: -.02em; }
        .brand img { width: 20px; height: 20px; object-fit: contain; }
        .brand small { font-size: 9px; font-weight: 700; letter-spacing: .04em; }
        .tag { border: 1.5px solid #000; border-radius: 3px; padding: 1px 6px; font-size: 11px; font-weight: 900; }
        .tag.inv { background: #000; color: #fff; }
        .code { text-align: center; padding: 6px 8px 4px; }
        .code svg { display: block; width: 100%; height: 58px; }
        .wbno { margin-top: 2px; font-size: 15px; font-weight: 900; letter-spacing: .14em; }
        .sort { display: flex; align-items: stretch; }
        .sort .big { flex: 1; display: flex; align-items: center; justify-content: center; padding: 4px; font-size: 26px; font-weight: 900; letter-spacing: .06em; background: #000; color: #fff; }
        .sort .hub { width: 36%; padding: 4px 8px; border-left: 1.5px solid #000; }
        .who { font-size: 12px; font-weight: 800; }
        .addr { margin-top: 1px; font-size: 10px; }
        .cod { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; }
        .cod b { font-size: 20px; font-weight: 900; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px 8px; font-size: 9.5px; }
        .meta b { display: block; font-size: 10.5px; }
        .items { flex: 1; overflow: hidden; font-size: 9.5px; }
        .items li { display: flex; justify-content: space-between; gap: 6px; }
        .items ul { margin: 2px 0 0; padding: 0; list-style: none; }
        .sign { display: flex; gap: 8px; border-bottom: 0; }
        .sign div { flex: 1; border-bottom: 1px solid #000; height: 22px; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { display: block; padding: 0; }
            .wb { border: 0; margin: 0; }
        }
    </style>
</head>
<body>
<div class="bar">
    <strong>{{ $parcels->count() === 1 ? 'Waybill' : $parcels->count() . ' waybills' }} · 4 × 6 in label</strong>
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ url()->previous() }}">Back</a>
</div>

<div class="sheet">
@foreach ($parcels as $p)
    @php
        $order  = $p->order;
        $isCod  = $order && $order->payment_method === 'COD' && $order->payment_status !== 'Paid';
        $amount = $order ? (float) $order->total_price : 0;
        $store  = $p->seller?->storeName() ?? 'ALVY seller';
        $sellerPhone = $p->seller?->sellerApplication->phone ?? $p->seller?->contact_no;
        $items  = $order ? $order->items()->with('product')->get() : collect();
        $count  = $items->sum('quantity');
    @endphp
    <section class="wb" data-waybill="{{ $p->waybill_number }}">
        {{-- Brand + service --}}
        <div class="sec head">
            <div class="brand">
                <img src="{{ asset('images/logo-mark.png') }}" alt="">
                <span>ALVY <small>EXPRESS</small></span>
            </div>
            <div style="display:flex;gap:4px;">
                <span class="tag">STANDARD</span>
                <span class="tag {{ $isCod ? 'inv' : '' }}">{{ $isCod ? 'COD' : 'PAID' }}</span>
            </div>
        </div>

        {{-- Waybill barcode --}}
        <div class="sec code">
            {!! Barcode::svg($p->waybill_number, 70, 2) !!}
            <div class="wbno">{{ $p->waybill_number }}</div>
        </div>

        {{-- Sort code: where the sorter drops this parcel --}}
        <div class="sec sort" style="padding:0;">
            <div class="big">{{ $p->sortCode() }}</div>
            <div class="hub">
                <div class="lbl">Sorting hub</div>
                <div style="font-weight:800;font-size:10.5px;">{{ $p->currentSortingCenter?->assigned_municipality ? $p->currentSortingCenter->assigned_municipality . ' SC' : ($hub->assigned_municipality ? $hub->assigned_municipality . ' SC' : 'ALVY SC') }}</div>
                <div class="lbl" style="margin-top:3px;">Barangay</div>
                <div style="font-weight:800;font-size:10.5px;">{{ $p->area->name ?? 'Not sorted yet' }}</div>
            </div>
        </div>

        {{-- Recipient --}}
        <div class="sec">
            <div class="lbl">Deliver to</div>
            <div class="who">{{ $p->receiver_name ?: '—' }} <span style="font-weight:600;font-size:10.5px;">{{ $mask($p->receiver_phone) }}</span></div>
            <div class="addr">{{ $p->dropoff_address }}</div>
        </div>

        {{-- Sender --}}
        <div class="sec">
            <div class="lbl">From</div>
            <div style="font-weight:800;">{{ $store }} <span style="font-weight:600;">{{ $mask($sellerPhone) }}</span></div>
            <div class="addr">{{ $p->pickup_address }}</div>
        </div>

        {{-- Cash to collect --}}
        <div class="sec cod">
            <span class="lbl" style="font-size:9px;">{{ $isCod ? 'Collect from buyer (COD)' : 'Payment' }}</span>
            <b>{{ $isCod ? '₱' . number_format($amount, 2) : 'PAID — collect ₱0' }}</b>
        </div>

        {{-- Details --}}
        <div class="sec meta">
            <div>Order<b>{{ $order ? '#' . str_pad($order->id, 6, '0', STR_PAD_LEFT) : '—' }}</b></div>
            <div>Weight<b>{{ $kg($p->weight_kg) }}</b></div>
            <div>Items<b>{{ $count ?: '—' }}</b></div>
            <div>Ship date<b>{{ ($p->received_at ?? $p->created_at)?->format('M j, Y') }}</b></div>
            <div style="grid-column:span 2;">Tracking<b style="font-size:9.5px;">{{ $p->tracking_number }}</b></div>
        </div>

        {{-- Contents --}}
        <div class="sec items">
            <div class="lbl">Contents</div>
            <ul>
                @forelse ($items->take(4) as $i)
                    <li><span>{{ \Illuminate\Support\Str::limit($i->product->title ?? 'Item', 42) }}{{ $i->variation_name ? ' (' . $i->variation_name . ')' : '' }}</span><span>× {{ $i->quantity }}</span></li>
                @empty
                    <li><span>{{ $p->notes ?: 'Parcel' }}</span><span></span></li>
                @endforelse
                @if ($items->count() > 4)<li><span>+ {{ $items->count() - 4 }} more item(s)</span><span></span></li>@endif
            </ul>
        </div>

        {{-- Proof of delivery --}}
        <div class="sec sign">
            <span class="lbl" style="align-self:flex-end;">Received by</span><div></div>
            <span class="lbl" style="align-self:flex-end;">Date</span><div style="flex:.5;"></div>
        </div>
    </section>
@endforeach
</div>
</body>
</html>
