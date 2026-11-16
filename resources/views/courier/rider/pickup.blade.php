<x-courier-layout title="Pickup" active="rider">
@php $p = $pickup->parcel; $app = $p->seller?->sellerApplication; @endphp
<a href="{{ route('courier.rider') }}" class="cx-link-back">&larr; Hub Jobs</a>
<div class="cx-page-head">
    <h1 class="font-display">Pickup · {{ $p->tracking_number }}</h1>
    <p><span class="cx-pill {{ $pickup->status === 'assigned' ? 'cx-pill-amber' : 'cx-pill-blue' }}">{{ $pickup->statusLabel() }}</span>
        @if($pickup->picked_up_at) · picked up {{ $pickup->picked_up_at->format('M j, Y g:i A') }} @endif</p>
</div>

<div class="card" style="padding:18px 20px;margin-bottom:20px;">
    <dl class="cx-dl">
        <dt>Seller</dt><dd data-seller>{{ $app->shop_name ?? $p->seller->name ?? '—' }}</dd>
        <dt>Pickup address</dt><dd>{{ $p->pickup_address }}</dd>
        <dt>Contact number</dt><dd>{{ $app->phone ?? $p->seller->contact_no ?? '—' }}</dd>
        <dt>Tracking number</dt><dd>{{ $p->tracking_number }}</dd>
        <dt>Parcels</dt><dd>1 parcel{{ $p->order ? ' · ' . $p->order->items->sum('quantity') . ' item(s)' : '' }}</dd>
        <dt>Bring to</dt><dd>{{ $rider->companyName() }} hub</dd>
    </dl>
</div>

@if($pickup->status === 'assigned' && ! $pickup->acknowledged_at)
    <form method="POST" action="{{ route('courier.rider.jobs.acknowledge', ['kind' => 'pickup', 'job' => $pickup->id]) }}" class="card" style="padding:18px 20px;">
        @csrf
        <p style="margin:0 0 10px;">Accept this pickup job, then go to the seller.</p>
        <button class="cx-btn cx-btn-orange cx-btn-block">Accept job</button>
    </form>
@elseif($pickup->status === 'assigned')
    <div style="margin-bottom:10px;">@include('partials.waybill-scanner', ['lookup' => 'fill:pickup', 'fill' => '#tracking_number', 'label' => 'Scan the waybill with the camera'])</div>
    <form method="POST" action="{{ route('courier.rider.pickups.confirm', $pickup->id) }}" enctype="multipart/form-data" class="card" style="padding:18px 20px;" id="pickup-form">
        @csrf
        <label class="cx-label" for="proof_photo">1. Photo of the parcel (proof of pickup, JPG or PNG, up to 5 MB)</label>
        <input type="file" id="proof_photo" name="proof_photo" accept="image/jpeg,image/png" capture="environment" class="input" required>
        @error('proof_photo')<p class="cx-alert cx-alert-error" style="margin-top:8px;">{{ $message }}</p>@enderror
        <label class="cx-label cx-field-mt" for="tracking_number">2. Scan the waybill barcode or type the tracking number</label>
        <input id="tracking_number" name="tracking_number" class="input" autocomplete="off" value="{{ old('tracking_number') }}" placeholder="e.g. {{ substr($p->tracking_number, 0, 4) }}…">
        @error('tracking_number')<p class="cx-alert cx-alert-error" style="margin-top:8px;">{{ $message }}</p>@enderror
        <button class="cx-btn cx-btn-orange cx-btn-block" style="margin-top:12px;">Confirm pickup</button>
    </form>
    <script>
    // One submit: a second scan of the same waybill doesn't send it twice.
    document.getElementById('pickup-form').addEventListener('submit', function (e) { if (this.dataset.sent) { e.preventDefault(); return; } this.dataset.sent = 1; });
    </script>
@endif
@if($pickup->proof_path)
    <div class="card" style="padding:18px 20px;margin-top:20px;">
        <p class="cx-section-title" style="margin-top:0;">Proof of pickup</p>
        <img src="{{ \App\Support\ProtectedFile::url($pickup->proof_path) }}" alt="Proof of pickup" style="max-width:100%;max-height:320px;border-radius:8px;">
    </div>
@endif
</x-courier-layout>
