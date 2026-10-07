<x-courier-layout title="Delivery Details" active="dashboard">

<a href="{{ route('courier.dashboard') }}" class="cx-link-back" style="margin-bottom:16px;">
    @include('courier.partials.icon', ['name' => 'back', 'size' => 16, 'sw' => 2.2]) Back to Dashboard
</a>

<div class="cx-page-head">
    <h1 class="font-display">Delivery Details</h1>
    <p>Pickup, delivery, and earnings details for this job.</p>
</div>

<div class="card" style="overflow:hidden;max-width:640px;">

    {{-- Header --}}
    <div style="padding:16px 24px;background:var(--accent-soft);border-bottom:1px solid var(--border);">
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;">
            <div>
                <p class="font-display" style="font-weight:700;color:var(--text);">Order #{{ str_pad($delivery->order_id,6,'0',STR_PAD_LEFT) }}</p>
                <p style="font-size:12px;margin-top:2px;color:var(--text-muted);">Accepted {{ $delivery->accepted_at?->format('M d, Y H:i') ?? '—' }}</p>
            </div>
            @php
                $pill = match($delivery->status) { 'delivered'=>'cx-pill-green','failed'=>'cx-pill-red', default=>'cx-pill-orange' };
            @endphp
            <span class="cx-pill {{ $pill }}">{{ $delivery->courierStatusLabel() }}</span>
        </div>
    </div>

    {{-- Status progression --}}
    @php
        $steps = ['accepted'=>'Accepted','picked_up'=>'Picked Up','in_transit'=>'Out for Delivery','delivered'=>'Delivered'];
        $order = array_keys($steps);
        $currentIdx = array_search($delivery->status, $order);
        if ($currentIdx === false) $currentIdx = $delivery->status === 'failed' ? count($order) : -1;
    @endphp
    <div class="cx-steps" style="padding:16px 24px;border-bottom:1px solid var(--border);">
        @foreach($steps as $key => $label)
            @php $done = $loop->index <= $currentIdx; @endphp
            <div class="cx-step">
                <span class="cx-step-dot" style="background:{{ $done ? 'var(--green)' : 'var(--border)' }};">{{ $loop->iteration }}</span>
                <span style="font-size:11px;font-weight:600;color:{{ $done ? 'var(--green)' : '#9db3c4' }};">{{ $label }}</span>
            </div>
            @if(! $loop->last)<span class="cx-step-line"></span>@endif
        @endforeach
    </div>

    {{-- Pickup address --}}
    @php $seller = $delivery->seller(); @endphp
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);">
        <p style="font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-muted);">PICK UP FROM (Seller)</p>
        <p style="font-weight:600;font-size:14px;color:var(--text);">{{ $delivery->pickupName() }}</p>
        @if($seller && $seller->phone)<p style="font-size:14px;margin-top:2px;color:#6b7280;">{{ $seller->phone }}</p>@endif
        @if($seller && ($seller->address || $seller->city))<p style="font-size:14px;margin-top:2px;color:#6b7280;">{{ trim(($seller->address ?? '').' '.($seller->city ?? '')) }}</p>@endif
        @if($delivery->pickup_scheduled_at)<p style="font-size:14px;margin-top:2px;color:#6b7280;">Pickup: {{ $delivery->pickup_scheduled_at->format('M d, Y h:i A') }}</p>@endif
        @if($delivery->notes)<p style="font-size:14px;margin-top:2px;color:#6b7280;">Notes: {{ $delivery->notes }}</p>@endif
    </div>

    {{-- Delivery address --}}
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);">
        <p style="font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-muted);">DELIVER TO (Buyer)</p>
        @if($delivery->order)
            <p style="font-weight:600;font-size:14px;color:var(--text);">{{ $delivery->order->full_name }}</p>
            @if($delivery->order->phone)<p style="font-size:14px;margin-top:2px;color:#6b7280;">{{ $delivery->order->phone }}</p>@endif
            <p style="font-size:14px;margin-top:2px;color:#6b7280;">{{ $delivery->order->fullAddress() }}</p>
        @endif
    </div>

    {{-- Items --}}
    @if($delivery->order?->items->count())
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);">
            <p style="font-size:12px;font-weight:600;margin-bottom:12px;color:var(--text-muted);">ITEMS</p>
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach($delivery->order->items as $item)
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img src="{{ $item->product && $item->product->image ? asset('storage/'.$item->product->image) : 'https://placehold.co/40x54/F5F0EB/4A2C17' }}"
                             style="height:48px;width:36px;border-radius:4px;object-fit:cover;flex-shrink:0;" alt="{{ $item->product->title ?? '' }}">
                        <div>
                            <p style="font-size:14px;font-weight:500;color:var(--text);">{{ $item->product->title ?? '—' }}</p>
                            <p style="font-size:12px;color:var(--text-muted);">x{{ $item->quantity }} · ₱{{ number_format($item->price,2) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Earnings --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid var(--border);">
        <span style="font-size:14px;color:#6b7280;">Delivery Fee</span>
        <span class="font-display" style="font-weight:700;color:var(--accent);">₱{{ number_format($delivery->delivery_fee,2) }}</span>
    </div>

    {{-- Actions --}}
    @if(in_array($delivery->status, ['accepted','picked_up','in_transit']))
        <div style="padding:20px 24px;display:flex;flex-direction:column;gap:12px;">
            @if($delivery->status === 'accepted')
                <form action="{{ route('courier.deliveries.pickup',$delivery->id) }}" method="POST">
                    @csrf
                    <button class="cx-btn cx-btn-navy cx-btn-block">Confirm Pickup</button>
                </form>
            @elseif($delivery->status === 'picked_up')
                <form action="{{ route('courier.deliveries.in-transit',$delivery->id) }}" method="POST">
                    @csrf
                    <button class="cx-btn cx-btn-blue cx-btn-block">Start Delivery (Out for Delivery)</button>
                </form>
            @elseif($delivery->status === 'in_transit')
                <form action="{{ route('courier.deliveries.complete',$delivery->id) }}" method="POST">
                    @csrf
                    <div style="margin-bottom:12px;">
                        <label class="cx-label">Delivery Notes (optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Left at gate" class="input cx-field-mt">
                    </div>
                    <button class="cx-btn cx-btn-green cx-btn-block">
                        @include('courier.partials.icon', ['name' => 'check-plain', 'size' => 15, 'sw' => 2.4]) Complete Delivery
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>

</x-courier-layout>
