<x-courier-layout title="Courier Dashboard" active="dashboard">

<div class="cx-page-head">
    <h1 class="font-display">Courier Dashboard</h1>
    <p>Welcome back, {{ $courier->first_name }}. Here are your active tasks.</p>
</div>

{{-- Stat cards --}}
<div class="cx-grid cx-cols-4" style="margin-bottom:32px;">
    @php
        $stats = [
            ['label'=>'Pending Pickup',   'value'=>$forPickup->count(),   'color'=>'var(--amber)'],
            ['label'=>'Out for Delivery', 'value'=>$forDelivery->count(), 'color'=>'var(--blue)'],
            ['label'=>'Completed Today',  'value'=>$todayDone,            'color'=>'var(--green)'],
            ['label'=>'Total Earnings',   'value'=>'₱'.number_format($totalEarnings,2), 'color'=>'var(--accent)'],
        ];
    @endphp
    @foreach($stats as $s)
        <div class="card cx-stat">
            <p class="cx-stat-value" style="color:{{ $s['color'] }};">{{ $s['value'] }}</p>
            <p class="cx-stat-label">{{ $s['label'] }}</p>
        </div>
    @endforeach
</div>

{{-- Items for Pickup --}}
<h2 class="cx-section-title">Items for Pickup</h2>
<div style="margin-bottom:32px;">
    @forelse($forPickup as $d)
        <div class="card cx-item">
            <div class="cx-item-row">
                <div class="min-w-0">
                    <p class="cx-item-title">
                        Order #{{ str_pad($d->order_id,6,'0',STR_PAD_LEFT) }}
                        <span class="cx-pill cx-pill-amber" style="margin-left:8px;">Ready for Pickup</span>
                    </p>
                    <p class="cx-item-meta">Seller: <strong style="color:#374151;">{{ $d->pickupName() }}</strong></p>
                    <p class="cx-item-meta">Deliver to: {{ $d->order->full_name ?? '—' }} · {{ $d->order->city ?? '' }}</p>
                    @if($d->pickup_scheduled_at)<p class="cx-item-meta">Pickup: {{ $d->pickup_scheduled_at->format('M d, Y h:i A') }}</p>@endif
                    <p class="cx-item-meta">{{ $d->itemCount() }} item(s) · ₱{{ number_format($d->delivery_fee,2) }} fee</p>
                </div>
                <div class="cx-actions">
                    <form action="{{ route('courier.deliveries.pickup',$d->id) }}" method="POST"
                          onsubmit="return confirm('Confirm you have picked up this package from the seller?')">
                        @csrf
                        <button class="cx-btn cx-btn-navy cx-btn-sm">Confirm Picked Up</button>
                    </form>
                    <a href="{{ route('courier.deliveries.show',$d->id) }}" class="cx-btn cx-btn-outline cx-btn-sm">View Pickup</a>
                </div>
            </div>
        </div>
    @empty
        <div class="card cx-empty">
            <p class="cx-empty-sub">No packages waiting for pickup.</p>
        </div>
    @endforelse
</div>

{{-- Items for Delivery --}}
<h2 class="cx-section-title">Items for Delivery</h2>
<div style="margin-bottom:32px;">
    @forelse($forDelivery as $d)
        @php $isTransit = $d->status === 'in_transit'; @endphp
        <div class="card cx-item">
            <div class="cx-item-row">
                <div class="min-w-0">
                    <p class="cx-item-title">
                        Order #{{ str_pad($d->order_id,6,'0',STR_PAD_LEFT) }}
                        <span class="cx-pill {{ $isTransit ? 'cx-pill-blue' : 'cx-pill-navy' }}" style="margin-left:8px;">
                            {{ $isTransit ? 'Out for Delivery' : 'Picked Up' }}
                        </span>
                    </p>
                    <p class="cx-item-meta">Buyer: <strong style="color:#374151;">{{ $d->order->full_name ?? '—' }}</strong></p>
                    <p class="cx-item-meta">{{ $d->order->fullAddress() ?? '' }}</p>
                    <p class="cx-item-meta">{{ $d->itemCount() }} item(s) · ₱{{ number_format($d->delivery_fee,2) }} fee</p>
                </div>
                <div class="cx-actions">
                    @if(! $isTransit)
                        <form action="{{ route('courier.deliveries.in-transit',$d->id) }}" method="POST">
                            @csrf
                            <button class="cx-btn cx-btn-blue cx-btn-sm">Start Delivery</button>
                        </form>
                    @else
                        <form action="{{ route('courier.deliveries.complete',$d->id) }}" method="POST"
                              onsubmit="return confirm('Confirm this package was delivered to the buyer?')">
                            @csrf
                            <button class="cx-btn cx-btn-green cx-btn-sm">Confirm Delivered</button>
                        </form>
                    @endif
                    <a href="{{ route('courier.deliveries.show',$d->id) }}" class="cx-btn cx-btn-outline cx-btn-sm">View Delivery</a>
                </div>
            </div>
        </div>
    @empty
        <div class="card cx-empty">
            <p class="cx-empty-sub">No packages out for delivery.</p>
        </div>
    @endforelse
</div>

{{-- Available jobs --}}
<h2 class="cx-section-title">Available Delivery Requests</h2>
@forelse($available as $d)
    <div class="card cx-item">
        <div class="cx-item-row" style="align-items:center;">
            <div>
                <p class="cx-item-title">Order #{{ str_pad($d->order_id,6,'0',STR_PAD_LEFT) }}</p>
                <p class="cx-item-meta">Pickup from: {{ $d->pickupName() }}</p>
                <p class="cx-item-meta">Deliver to: {{ $d->order->full_name ?? '—' }} · {{ $d->order->city ?? '' }}</p>
                @if($d->pickup_scheduled_at)<p class="cx-item-meta">Pickup: {{ $d->pickup_scheduled_at->format('M d, Y h:i A') }}</p>@endif
                <p class="cx-item-meta">{{ $d->itemCount() }} item(s) · Earn ₱{{ number_format($d->delivery_fee,2) }}</p>
            </div>
            <form action="{{ route('courier.deliveries.accept',$d->id) }}" method="POST"
                  onsubmit="return confirm('Accept this delivery job?')">
                @csrf
                <button class="cx-btn cx-btn-navy">Accept Job</button>
            </form>
        </div>
    </div>
@empty
    <div class="card cx-empty">
        <p class="cx-empty-title">No delivery requests available right now</p>
        <p class="cx-empty-sub">Check back soon for new orders.</p>
    </div>
@endforelse

</x-courier-layout>
