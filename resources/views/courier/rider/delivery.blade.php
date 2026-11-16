<x-courier-layout title="Delivery" active="rider">
@php $p = $delivery->parcel; $order = $p->order; $cod = (float) $delivery->cod_amount > 0; @endphp
<a href="{{ route('courier.rider') }}" class="cx-link-back">&larr; Hub Jobs</a>
<div class="cx-page-head">
    <h1 class="font-display">Delivery · {{ $p->tracking_number }}</h1>
    <p><span class="cx-pill cx-pill-blue" data-status>{{ $delivery->statusLabel() }}</span> · attempt {{ $delivery->attempt }} of {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }}</p>
</div>

<div class="card" style="padding:18px 20px;margin-bottom:20px;">
    <dl class="cx-dl">
        <dt>Buyer</dt><dd>{{ $p->receiver_name }} · {{ $p->receiver_phone }}</dd>
        <dt>Address</dt><dd>{{ $p->dropoff_address }}
            <a href="{{ $p->mapsUrl() }}" target="_blank" rel="noopener" class="cx-btn cx-btn-outline cx-btn-sm" style="margin-top:8px;display:inline-flex;gap:6px;" data-maps-link>
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $p->hasMapPin() ? 'Directions to the buyer\'s pin' : 'Find the address in Maps' }}
            </a>
            @unless($p->hasMapPin())<span style="display:block;font-size:12px;color:#6b7280;margin-top:4px;">The buyer didn't pin this address on the map; call them if you can't find it.</span>@endunless
        </dd>
        <dt>Payment</dt><dd data-cod>{{ $cod ? 'COD — collect ₱' . number_format($delivery->cod_amount, 2) : '₱0.00 · Paid (no cash to collect)' }}</dd>
        <dt>Assigned</dt><dd>{{ $delivery->created_at->format('M j, g:i A') }}</dd>
        @if($delivery->picked_up_at)<dt>Out for delivery</dt><dd>{{ $delivery->picked_up_at->format('M j, g:i A') }}</dd>@endif
        @if($delivery->delivered_at)<dt>Delivered</dt><dd>{{ $delivery->delivered_at->format('M j, g:i A') }}</dd>@endif
    </dl>
</div>

@if($delivery->status === 'assigned')
    <form method="POST" action="{{ route('courier.rider.deliveries.start', $delivery->id) }}">@csrf
        <button class="cx-btn cx-btn-blue cx-btn-block">Mark Out for Delivery</button>
    </form>
@elseif($delivery->status === 'out_for_delivery')
    <div style="margin-bottom:10px;">@include('partials.waybill-scanner', ['lookup' => 'fill:delivery', 'fill' => '#tracking_number', 'label' => 'Scan the waybill with the camera'])</div>
    <form method="POST" action="{{ route('courier.rider.deliveries.deliver', $delivery->id) }}" enctype="multipart/form-data" class="card" style="padding:18px 20px;margin-bottom:16px;">
        @csrf
        <p class="cx-section-title" style="margin-top:0;">Mark Delivered</p>
        <label class="cx-label" for="pod_photo">Proof-of-delivery photo (JPG or PNG, up to 5 MB)</label>
        <input type="file" id="pod_photo" name="pod_photo" accept="image/jpeg,image/png" capture="environment" class="input">
        @error('pod_photo')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        <label class="cx-label cx-field-mt" for="tracking_number">Waybill (scan it with the button above, or type the number)</label>
        <input id="tracking_number" name="tracking_number" class="input" autocomplete="off" required value="{{ old('tracking_number') }}" placeholder="e.g. PH2611…">
        @error('tracking_number')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        <p style="font-size:12px;color:#8a7f78;margin-top:6px;">Take the photo with the parcel handed to the buyer (or at the door). It is the proof that completes the delivery.</p>

        @if($cod)
            <label class="cx-label cx-field-mt" for="cod_collected">COD collected (₱)</label>
            <input id="cod_collected" name="cod_collected" type="number" step="0.01" min="0" class="input" value="{{ old('cod_collected') }}">
            @error('cod_collected')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        @endif
        <button class="cx-btn cx-btn-green cx-btn-block" style="margin-top:14px;">Delivered</button>
    </form>
@endif

@if(in_array($delivery->status, \App\Models\ParcelDelivery::OPEN, true))
    <form method="POST" action="{{ route('courier.rider.deliveries.fail', $delivery->id) }}" class="card" style="padding:18px 20px;">
        @csrf
        <label class="cx-label" for="reason">Failed delivery — reason</label>
        <select id="reason" name="reason" class="input">
            <option value="">Choose a reason…</option>
            @foreach(\App\Models\ParcelDelivery::FAILURE_REASONS as $r)<option>{{ $r }}</option>@endforeach
        </select>
        @error('reason')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        <p style="font-size:12px;color:#8a7f78;margin-top:6px;"><strong>Buyer not home</strong>: rescheduled for another try (up to 3). <strong>Wrong address</strong>: the parcel is returned to the seller. <strong>Refused</strong> or a 3rd failed try: the order is cancelled. Bring the parcel back to the hub in every case.</p>
        <button class="cx-btn cx-btn-danger-outline cx-btn-block" style="margin-top:10px;">Mark failed</button>
    </form>
@endif

@if($delivery->status === 'delivered' && $delivery->pod_path)
    <div class="card" style="padding:18px 20px;"><img src="{{ \App\Support\ProtectedFile::url($delivery->pod_path) }}" alt="Proof of delivery" style="max-height:220px;border-radius:8px;"></div>
@endif
</x-courier-layout>
