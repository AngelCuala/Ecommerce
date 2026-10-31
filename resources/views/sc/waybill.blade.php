<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $parcels->count() === 1 ? 'Waybill ' . $parcels->first()->waybill_number : $parcels->count() . ' waybills' }} — ALVY</title>
    @include('partials.waybill.styles')
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
        $order = $p->order;
        $sc    = $p->currentSortingCenter ?? $hub;
    @endphp
    @include('partials.waybill.label', ['l' => [
        'waybill'  => $p->waybill_number,
        'tracking' => $p->tracking_number,
        'sort'     => $p->sortCode(),
        'hub'      => $sc?->assigned_municipality ? $sc->assigned_municipality . ' SC' : null,
        'barangay' => $p->area->name ?? null,
        'cod'      => $order && $order->payment_method === 'COD' && $order->payment_status !== 'Paid',
        'amount'   => $order ? (float) $order->total_price : 0,
        'to'       => ['name' => $p->receiver_name, 'phone' => $p->receiver_phone, 'address' => $p->dropoff_address],
        'from'     => ['name' => $p->seller?->storeName(), 'phone' => $p->seller?->sellerApplication->phone ?? $p->seller?->contact_no, 'address' => $p->pickup_address],
        'order'    => $order?->number(),
        'weight'   => $p->weight_kg,
        'date'     => $p->received_at ?? $p->created_at,
        'items'    => $order ? $order->items()->with('product')->get() : collect(),
        'fallback' => $p->notes ?: 'Parcel',
        'note'     => $order?->buyer_note,
    ]])
@endforeach
</div>
</body>
</html>
