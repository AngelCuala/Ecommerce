<x-courier-layout title="Return" active="rider">
@php
    $toSeller = in_array($rs->status, ['to_seller', 'returned'], true) && (int) $rs->seller_rider_id === (int) $rider->id;
    $app = $rs->parcel?->seller?->sellerApplication;
@endphp
<a href="{{ route('courier.rider') }}" class="cx-link-back">&larr; Hub Jobs</a>
<div class="cx-page-head">
    <h1 class="font-display">{{ $toSeller ? 'Return to seller' : 'Collect return' }} · {{ $rs->order?->number() }}</h1>
    <p><span class="cx-pill {{ in_array($rs->status, ['pickup_assigned', 'to_seller'], true) ? 'cx-pill-amber' : 'cx-pill-blue' }}">{{ $rs->statusLabel() }}</span></p>
</div>

<div class="card" style="padding:18px 20px;margin-bottom:20px;">
    <dl class="cx-dl">
        @if ($toSeller)
            <dt>Seller</dt><dd>{{ $app->shop_name ?? $rs->parcel?->seller?->name ?? '—' }}</dd>
            <dt>Address</dt><dd>{{ $rs->sellerAddress() }}</dd>
            <dt>Contact number</dt><dd>{{ $app->phone ?? $rs->parcel?->seller?->contact_no ?? '—' }}</dd>
        @else
            <dt>Buyer</dt><dd>{{ $rs->order?->full_name }}</dd>
            <dt>Address</dt><dd>{{ $rs->buyerAddress() }}</dd>
            <dt>Contact number</dt><dd>{{ $rs->order?->phone ?? '—' }}</dd>
        @endif
        <dt>Waybill</dt><dd>{{ $rs->code() }}</dd>
        <dt>Reason</dt><dd>{{ $rs->returnRequest?->reason }}</dd>
        <dt>Items</dt><dd>{{ $rs->order?->items->map(fn ($i) => ($i->product->title ?? 'Item') . ' ×' . $i->quantity)->implode(', ') }}</dd>
        @if (! $toSeller)<dt>Bring to</dt><dd>{{ $rider->companyName() }} hub</dd>@endif
    </dl>
</div>

@if ($rs->status === 'pickup_assigned' && (int) $rs->pickup_rider_id === (int) $rider->id)
    @if (! $rs->acknowledged_at)
        <form method="POST" action="{{ route('courier.rider.return-pickups.accept', $rs->id) }}" class="card" style="padding:18px 20px;">
            @csrf
            <p style="margin:0 0 10px;">Accept this return pickup, then go to the buyer.</p>
            <button class="cx-btn cx-btn-orange cx-btn-block">Accept job</button>
        </form>
    @else
        <div style="margin-bottom:10px;">@include('partials.waybill-scanner', ['lookup' => 'fill:return', 'fill' => '#tracking_number', 'label' => 'Scan the waybill with the camera'])</div>
        <form method="POST" action="{{ route('courier.rider.return-pickups.confirm', $rs->id) }}" enctype="multipart/form-data" class="card" style="padding:18px 20px;" id="return-form">
            @csrf
            <label class="cx-label" for="proof_photo">1. Photo of the item (proof of pickup, JPG or PNG, up to 5 MB)</label>
            <input type="file" id="proof_photo" name="proof_photo" accept="image/jpeg,image/png" capture="environment" class="input" required>
            @error('proof_photo')<p class="cx-alert cx-alert-error" style="margin-top:8px;">{{ $message }}</p>@enderror
            <label class="cx-label cx-field-mt" for="tracking_number">2. Scan the waybill barcode or type the tracking number</label>
            <input id="tracking_number" name="tracking_number" class="input" autocomplete="off" value="{{ old('tracking_number') }}">
            @error('tracking_number')<p class="cx-alert cx-alert-error" style="margin-top:8px;">{{ $message }}</p>@enderror
            <button class="cx-btn cx-btn-orange cx-btn-block" style="margin-top:12px;">Confirm pickup</button>
        </form>
        <script>document.getElementById('return-form').addEventListener('submit', function (e) { if (this.dataset.sent) { e.preventDefault(); return; } this.dataset.sent = 1; });</script>
    @endif
@elseif ($rs->status === 'to_seller' && $toSeller)
    <form method="POST" action="{{ route('courier.rider.return-pickups.handed', $rs->id) }}" enctype="multipart/form-data" class="card" style="padding:18px 20px;">
        @csrf
        <label class="cx-label" for="handed_photo">Photo of the hand-over (optional)</label>
        <input type="file" id="handed_photo" name="proof_photo" accept="image/jpeg,image/png" capture="environment" class="input">
        <button class="cx-btn cx-btn-orange cx-btn-block" style="margin-top:12px;" onclick="return confirm('Confirm you handed the returned item to the seller?')">Handed back to seller</button>
    </form>
@endif

@if ($rs->pickup_proof_path)
    <div class="card" style="padding:18px 20px;margin-top:20px;">
        <p class="cx-section-title" style="margin-top:0;">Proof of pickup</p>
        <img src="{{ \App\Support\ProtectedFile::url($rs->pickup_proof_path) }}" alt="Proof of pickup" style="max-width:100%;max-height:320px;border-radius:8px;">
    </div>
@endif
</x-courier-layout>
