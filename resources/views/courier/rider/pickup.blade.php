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

@if($pickup->status === 'assigned')
    <form method="POST" action="{{ route('courier.rider.pickups.confirm', $pickup->id) }}" class="card" style="padding:18px 20px;" id="pickup-form">
        @csrf
        <label class="cx-label" for="tracking_number">Scan the waybill barcode or type the tracking number</label>
        <input id="tracking_number" name="tracking_number" class="input" autocomplete="off" autofocus value="{{ old('tracking_number') }}" placeholder="e.g. {{ substr($p->tracking_number, 0, 4) }}…">
        @error('tracking_number')<p class="cx-alert cx-alert-error" style="margin-top:8px;">{{ $message }}</p>@enderror
        <button class="cx-btn cx-btn-orange cx-btn-block" style="margin-top:12px;">Confirm pickup</button>
    </form>
    <script>
    // One submit: a second scan of the same waybill doesn't send it twice.
    document.getElementById('pickup-form').addEventListener('submit', function (e) { if (this.dataset.sent) { e.preventDefault(); return; } this.dataset.sent = 1; });
    </script>
@endif
</x-courier-layout>
