@php
    $app = $seller->sellerApplication;
    $sc  = $parcel?->currentSortingCenter;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Waybill {{ $waybill ?? $tracking }} — ALVY</title>
    @include('partials.waybill.styles')
</head>
<body>
<div class="bar">
    <strong>Waybill · 4 × 6 in label</strong>
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ route('seller.orders.show', $order->id) }}">Back to order</a>
</div>

<div class="sheet">
    {{-- Same label (and barcode) the sorting center prints for this parcel --}}
    @include('partials.waybill.label', ['l' => [
        'waybill'  => $waybill ?? $tracking,
        'tracking' => $tracking,
        'sort'     => $parcel?->sortCode(),
        'hub'      => $sc?->assigned_municipality ? $sc->assigned_municipality . ' SC' : null,
        'barangay' => $parcel?->area->name ?? null,
        'cod'      => $order->payment_method === 'COD' && $order->payment_status !== 'Paid',
        'amount'   => (float) $order->total_price,
        'to'       => ['name' => $order->full_name, 'phone' => $order->phone, 'address' => $order->fullAddress()],
        'from'     => ['name' => $seller->storeName(), 'phone' => $app->phone ?? $seller->contact_no, 'address' => $app->address ?? null],
        'order'    => $order->number(),
        'weight'   => $weight,
        'date'     => $parcel?->created_at ?? now(),
        'items'    => $items,
        'note'     => $order->buyer_note,
    ]])
</div>
</body>
</html>
