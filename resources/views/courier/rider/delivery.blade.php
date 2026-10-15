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
        <dt>Address</dt><dd>{{ $p->dropoff_address }}</dd>
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
    <form method="POST" action="{{ route('courier.rider.deliveries.deliver', $delivery->id) }}" enctype="multipart/form-data" class="card" style="padding:18px 20px;margin-bottom:16px;">
        @csrf
        <p class="cx-section-title" style="margin-top:0;">Mark Delivered</p>
        <label class="cx-label" for="pod_photo">Proof-of-delivery photo (JPG or PNG, up to 5 MB)</label>
        <input type="file" id="pod_photo" name="pod_photo" accept="image/jpeg,image/png" capture="environment" class="input">
        @error('pod_photo')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror

        <label class="cx-label cx-field-mt" for="otp">OTP from the buyer</label>
        <input id="otp" name="otp" inputmode="numeric" maxlength="6" class="input" autocomplete="one-time-code" @disabled($delivery->otpLocked())>
        @error('otp')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        @if($delivery->otpLocked())<p class="cx-alert cx-alert-error">OTP entry is locked until {{ $delivery->otp_locked_until->format('g:i A') }}.</p>@endif

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
        <p style="font-size:12px;color:#8a7f78;margin-top:6px;">"Refused" or a 3rd failed attempt returns the parcel to the seller.</p>
        <button class="cx-btn cx-btn-danger-outline cx-btn-block" style="margin-top:10px;">Mark failed</button>
    </form>
@endif

@if($delivery->status === 'delivered' && $delivery->pod_path)
    <div class="card" style="padding:18px 20px;"><img src="{{ asset('storage/' . $delivery->pod_path) }}" alt="Proof of delivery" style="max-height:220px;border-radius:8px;"></div>
@endif
</x-courier-layout>
