<x-logistics-layout title="Parcel: {{ $parcel->tracking_number }}">

<a href="{{ route('logistics.parcels.index') }}" class="btn btn--sm btn--ghost" style="margin-bottom:18px;display:inline-block;">← Back</a>

<div class="panel" style="max-width:640px">
    <div class="panel__header">
        <h2 class="tracking-no">{{ $parcel->tracking_number }}</h2>
        <span class="badge badge--neutral">{{ ucfirst(str_replace('_',' ',$parcel->status)) }}</span>
    </div>
    <div style="padding:18px;display:grid;gap:10px;grid-template-columns:1fr 1fr;">
        <div><div class="text-muted">Seller</div>{{ $parcel->seller->name ?? '—' }}</div>
        <div><div class="text-muted">Receiver</div>{{ $parcel->receiver_name }}</div>
        <div><div class="text-muted">Receiver Phone</div>{{ $parcel->receiver_phone ?? '—' }}</div>
        <div><div class="text-muted">Area</div>{{ $parcel->area->name ?? '—' }}</div>
        <div><div class="text-muted">Pickup Address</div>{{ $parcel->pickup_address }}</div>
        <div><div class="text-muted">Dropoff Address</div>{{ $parcel->dropoff_address }}</div>
        <div><div class="text-muted">Weight</div>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</div>
        <div><div class="text-muted">Size</div>{{ $parcel->size ?? '—' }}</div>
        @if($parcel->notes)<div class="col-span-2"><div class="text-muted">Notes</div>{{ $parcel->notes }}</div>@endif
    </div>
    @if($parcel->parcelDelivery)
    <div style="padding:0 18px 18px;">
        <div class="text-muted" style="margin-bottom:4px;">Assigned Rider</div>
        {{ $parcel->parcelDelivery->rider->full_name ?? '—' }}
        · <span class="badge badge--neutral">{{ ucfirst(str_replace('_',' ',$parcel->parcelDelivery->status)) }}</span>
    </div>
    @endif
</div>

</x-logistics-layout>
