<x-courier-layout title="Delivery History" active="history">

<div class="cx-page-head">
    <h1 class="font-display">Delivery History</h1>
    <p>Search and review your past and in-progress deliveries.</p>
</div>

<form method="GET" action="{{ route('courier.history') }}" class="card cx-filters">
    <div class="cx-field">
        <label>Order ID</label>
        <input type="text" name="order_id" value="{{ $orderId }}" placeholder="e.g. 1001" class="input">
    </div>
    <div class="cx-field">
        <label>Status</label>
        <select name="status" class="input">
            <option value="">All</option>
            @foreach(['accepted'=>'Accepted','picked_up'=>'Picked Up','in_transit'=>'Out for Delivery','delivered'=>'Delivered','failed'=>'Failed'] as $val=>$label)
                <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="cx-field">
        <label>Delivered On</label>
        <input type="date" name="date" value="{{ $date }}" class="input">
    </div>
    <div class="cx-actions">
        <button type="submit" class="cx-btn cx-btn-navy" style="flex:1;">Filter</button>
        <a href="{{ route('courier.history') }}" class="cx-btn cx-btn-outline">Clear</a>
    </div>
</form>

@forelse($deliveries as $delivery)
    @php
        $pill = match($delivery->status) {
            'delivered' => 'cx-pill-green', 'failed' => 'cx-pill-red', default => 'cx-pill-orange'
        };
    @endphp
    <div class="card cx-item">
        <div class="cx-item-row">
            <div>
                <p class="cx-item-title">Order #{{ str_pad($delivery->order_id,6,'0',STR_PAD_LEFT) }}</p>
                <p class="cx-item-meta">Seller: {{ $delivery->pickupName() }}</p>
                @if($delivery->order)
                    <p class="cx-item-meta">Buyer: {{ $delivery->order->full_name }} · {{ $delivery->order->city }}</p>
                @endif
                <p class="cx-item-meta" style="color:#9db3c4;">
                    {{ $delivery->delivered_at ? 'Delivered '.$delivery->delivered_at->format('M d, Y H:i') : 'Updated '.$delivery->updated_at->format('M d, Y H:i') }}
                </p>
            </div>
            <div style="text-align:right;">
                <span class="cx-pill {{ $pill }}">{{ $delivery->courierStatusLabel() }}</span>
                <p style="margin-top:4px;font-weight:700;color:{{ $delivery->status === 'delivered' ? 'var(--accent)' : 'var(--text-muted)' }};">
                    {{ $delivery->status === 'delivered' ? '+' : '' }}₱{{ number_format($delivery->delivery_fee,2) }}
                </p>
                <a href="{{ route('courier.deliveries.show',$delivery->id) }}" class="cx-btn cx-btn-outline cx-btn-sm" style="margin-top:8px;">View</a>
            </div>
        </div>
    </div>
@empty
    <div class="card cx-empty">
        <span class="cx-empty-icon">@include('courier.partials.icon', ['name' => 'history', 'size' => 26])</span>
        <p class="cx-empty-title">No delivery history yet</p>
        <p class="cx-empty-sub">Completed deliveries will appear here.</p>
    </div>
@endforelse

<div style="margin-top:16px;">{{ $deliveries->links() }}</div>

</x-courier-layout>
