{{--
    One 4 × 6 in ALVY waybill label. Used by the sorting center (sc.waybill) and the seller (seller.orders.waybill).
    @include('partials.waybill.label', ['l' => [
        'waybill' => 'PH…',            // barcode value
        'tracking' => 'ALVY-…' | null,
        'sort' => 'LAG-CAL-REA' | null, 'hub' => 'City of Calamba SC' | null, 'barangay' => 'Real' | null,
        'cod' => bool, 'amount' => float,
        'to'   => ['name' => …, 'phone' => …, 'address' => …],
        'from' => ['name' => …, 'phone' => …, 'address' => …],
        'order' => '#000069', 'weight' => 0.4 | null, 'date' => Carbon | null,
        'items' => collection of order items (product, variation_name, quantity), 'fallback' => 'Parcel', 'note' => string | null,
    ]])
--}}
@php
    // Phone numbers are partly hidden on the printed label: 0917***4567.
    $mask = fn (?string $phone) => $phone ? (strlen($phone) > 7 ? substr($phone, 0, 4) . str_repeat('*', max(0, strlen($phone) - 8)) . substr($phone, -4) : $phone) : '—';
    $kg   = fn ($w) => $w ? rtrim(rtrim(number_format((float) $w, 2), '0'), '.') . ' kg' : '—';
    $items = collect($l['items'] ?? []);
    $shown = 5;
@endphp
<section class="wb" data-waybill="{{ $l['waybill'] }}">

    {{-- 1. Brand + service --}}
    <div class="wb-sec wb-head">
        <div class="wb-brand">
            <img src="{{ asset('images/logo-mark.png') }}" alt="">
            <span>ALVY <small>EXPRESS</small></span>
        </div>
        <div class="wb-tags">
            <span class="wb-tag">STANDARD</span>
            <span class="wb-tag {{ $l['cod'] ? 'inv' : '' }}">{{ $l['cod'] ? 'COD' : 'PREPAID' }}</span>
        </div>
    </div>

    {{-- 2. Waybill QR code (what staff scan) + the barcode as a back-up --}}
    <div class="wb-sec wb-code">
        <div class="wb-qr" data-waybill-qr>
            {!! \App\Support\QrCode::svg($l['waybill'], 100, 2) !!}
            <span>SCAN ME</span>
        </div>
        <div class="wb-bar">
            {!! \App\Support\Barcode::svg($l['waybill'], 70, 2) !!}
            <div class="wb-no">{{ $l['waybill'] }}</div>
        </div>
    </div>

    {{-- 3. Sort code: where the sorter drops this parcel --}}
    <div class="wb-sec wb-sort">
        @if (! empty($l['sort']))
            <div class="wb-sort-code">{{ $l['sort'] }}</div>
        @else
            <div class="wb-sort-code pending">Sort code is added<br>at the sorting hub</div>
        @endif
        <div class="wb-hub">
            <div><span class="wb-lbl">Sorting hub</span><b>{{ $l['hub'] ?: 'ALVY sorting hub' }}</b></div>
            <div><span class="wb-lbl">Barangay</span><b>{{ $l['barangay'] ?: 'Not sorted yet' }}</b></div>
        </div>
    </div>

    {{-- 4. Recipient (the biggest block) --}}
    <div class="wb-sec wb-party to">
        <span class="wb-side to">TO</span>
        <div class="wb-party-body">
            <div class="wb-name">{{ $l['to']['name'] ?: '—' }} <span class="wb-phone">{{ $mask($l['to']['phone'] ?? null) }}</span></div>
            <div class="wb-addr" data-buyer-address>{{ $l['to']['address'] ?: '—' }}</div>
        </div>
    </div>

    {{-- 5. Sender --}}
    <div class="wb-sec wb-party from">
        <span class="wb-side">FROM</span>
        <div class="wb-party-body">
            <div class="wb-name">{{ $l['from']['name'] ?: 'ALVY seller' }} <span class="wb-phone" style="font-size:10.5px;">{{ $mask($l['from']['phone'] ?? null) }}</span></div>
            <div class="wb-addr" data-pickup-address>{{ $l['from']['address'] ?: '—' }}</div>
        </div>
    </div>

    {{-- 6. Cash the rider collects (COD only; prepaid parcels just carry the PREPAID tag above) --}}
    @if ($l['cod'])
        <div class="wb-sec wb-pay cod" data-cod>
            <div><div class="wb-lbl">Collect from buyer</div><div class="wb-pay-what">CASH ON DELIVERY</div></div>
            <div class="wb-pay-amount">₱{{ number_format((float) $l['amount'], 2) }}</div>
        </div>
    @endif

    {{-- 7. Details --}}
    <div class="wb-sec wb-meta">
        <div><span class="wb-lbl">Order</span><b>{{ $l['order'] ?: '—' }}</b></div>
        <div><span class="wb-lbl">Ship date</span><b>{{ ! empty($l['date']) ? $l['date']->format('M j, Y') : '—' }}</b></div>
        <div><span class="wb-lbl">Weight</span><b data-weight>{{ $kg($l['weight'] ?? null) }}</b></div>
        <div><span class="wb-lbl">Qty</span><b>{{ $items->sum('quantity') ?: '—' }}</b></div>
    </div>
    @if (! empty($l['tracking']) && $l['tracking'] !== $l['waybill'])
        <div class="wb-sec wb-track"><span class="wb-lbl" style="align-self:center;">Tracking no.</span><b data-tracking>{{ $l['tracking'] }}</b></div>
    @endif

    {{-- 8. Contents --}}
    <div class="wb-sec wb-items">
        <div class="wb-lbl">Contents</div>
        <ul>
            @forelse ($items->take($shown) as $i)
                <li><span>{{ $i->product->title ?? 'Item' }}{{ $i->variation_name ? ' (' . $i->variation_name . ')' : '' }}</span><span>× {{ $i->quantity }}</span></li>
            @empty
                <li><span>{{ $l['fallback'] ?? 'Parcel' }}</span><span></span></li>
            @endforelse
            @if ($items->count() > $shown)<li><span>+ {{ $items->count() - $shown }} more item(s)</span><span></span></li>@endif
        </ul>
        @if (! empty($l['note']))<div class="wb-note"><b>Note:</b> {{ \Illuminate\Support\Str::limit($l['note'], 120) }}</div>@endif
    </div>

    {{-- 9. Proof of delivery --}}
    <div class="wb-sec wb-sign">
        <span class="wb-lbl">Received by</span><div class="line"></div>
        <span class="wb-lbl">Date</span><div class="line short"></div>
    </div>
</section>
