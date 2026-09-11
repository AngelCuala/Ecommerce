@extends('admin.layouts.app')
@section('title', 'Parcel Details')
@section('content')

<div class="panel">
    <div class="panel__header">
        <h2 class="tracking-no">{{ $parcel->tracking_number }}</h2>
        <a href="{{ route('admin.parcels.index') }}" class="btn btn--ghost btn--sm">Back</a>
    </div>
    <table>
        <tbody>
            <tr><td style="width:200px" class="text-muted">Seller</td><td>{{ $parcel->seller->name ?? '—' }}</td></tr>
            <tr><td class="text-muted">Pickup address</td><td>{{ $parcel->pickup_address }}</td></tr>
            <tr><td class="text-muted">Dropoff address</td><td>{{ $parcel->dropoff_address }}</td></tr>
            <tr><td class="text-muted">Receiver</td><td>{{ $parcel->receiver_name }} · {{ $parcel->receiver_phone ?? '—' }}</td></tr>
            <tr><td class="text-muted">Weight</td><td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td></tr>
            <tr><td class="text-muted">Size</td><td>{{ $parcel->size ?? '—' }}</td></tr>
            <tr><td class="text-muted">Area</td><td>{{ $parcel->area->name ?? '— unsorted —' }}</td></tr>
            <tr><td class="text-muted">Status</td><td><span class="badge badge--neutral">{{ str_replace('_',' ',$parcel->status) }}</span></td></tr>
            <tr><td class="text-muted">Rider</td><td>{{ $parcel->parcelDelivery->rider->full_name ?? '— not yet assigned —' }}</td></tr>
            @if($parcel->notes)
            <tr><td class="text-muted">Notes</td><td>{{ $parcel->notes }}</td></tr>
            @endif
        </tbody>
    </table>
</div>

@endsection
