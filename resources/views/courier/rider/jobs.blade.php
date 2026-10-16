<x-courier-layout title="Hub Jobs" active="rider">
@php $pay = ['pickup' => \App\Services\RiderDispatch::PAY_PICKUP, 'delivery' => \App\Services\RiderDispatch::PAY_DELIVERY]; @endphp

<div class="cx-page-head">
    <h1 class="font-display">Hub Jobs</h1>
    <p>{{ $rider->companyName() }} · {{ $rider->full_name }}</p>
</div>

{{-- Online / Offline --}}
<div class="card" style="padding:18px 20px;margin-bottom:24px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
    <div>
        <p style="font-weight:700;">
            @if($rider->isSuspended())
                <span class="cx-pill cx-pill-red">Suspended</span>
            @elseif($rider->isOnlineNow())
                <span class="cx-pill cx-pill-green" data-status="online">Online</span>
            @else
                <span class="cx-pill cx-pill-gray" data-status="offline">Offline</span>
            @endif
        </p>
        <p style="font-size:13px;color:var(--muted, #8a7f78);margin-top:4px;">
            @if($rider->isSuspended())
                Your account is suspended by {{ $rider->companyName() }}. You can't go Online or receive jobs.
            @else
                Online riders get job offers. Active jobs: {{ $rider->activeJobsCount() }} of {{ \App\Services\RiderDispatch::MAX_ACTIVE }}.
            @endif
        </p>
    </div>
    <form method="POST" action="{{ route('courier.rider.online') }}">
        @csrf
        <input type="hidden" name="online" value="{{ $rider->isOnlineNow() ? 0 : 1 }}">
        <button class="cx-btn {{ $rider->isOnlineNow() ? 'cx-btn-outline' : 'cx-btn-green' }}" @disabled($rider->isSuspended())>{{ $rider->isOnlineNow() ? 'Go Offline' : 'Go Online' }}</button>
    </form>
</div>

{{-- Offers --}}
<h2 class="cx-section-title">Job offers</h2>
<div style="margin-bottom:28px;">
    @forelse($offers as $o)
        @php $p = $o->parcel; $order = $p->order; $cod = $order && strtoupper($order->payment_method) === 'COD' && $order->payment_status !== 'Paid'; @endphp
        <div class="card cx-item" data-offer="{{ $o->id }}">
            <div class="cx-item-row">
                <div>
                    <p class="cx-item-title">{{ ucfirst($o->kind) }} · {{ $p->tracking_number }}</p>
                    <p class="cx-item-meta">Pickup: {{ $p->seller->name ?? 'Seller' }} — {{ $p->pickup_address }}</p>
                    <p class="cx-item-meta">Drop-off: {{ $o->kind === 'pickup' ? $rider->companyName() . ' hub' : ($p->area->name ?? '') . ' ' . $p->destination_municipality }}</p>
                    <p class="cx-item-meta">COD: {{ $cod ? '₱' . number_format($order->total_price, 2) : 'None (paid)' }} · Pay: ₱{{ number_format($pay[$o->kind], 2) }} {{ $o->kind }}</p>
                </div>
                <div style="text-align:right;">
                    <p class="cx-pill cx-pill-amber"><span data-countdown="{{ $o->expires_at->toIso8601String() }}">{{ $o->secondsLeft() }}</span>s</p>
                    <div style="display:flex;gap:8px;margin-top:8px;">
                        <form method="POST" action="{{ route('courier.rider.offers.decline', $o->id) }}">@csrf<button class="cx-btn cx-btn-outline cx-btn-sm">Decline</button></form>
                        <form method="POST" action="{{ route('courier.rider.offers.accept', $o->id) }}" data-accept>@csrf<button class="cx-btn cx-btn-orange cx-btn-sm">Accept</button></form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card cx-empty"><p class="cx-empty-title">No offers right now</p><p class="cx-empty-sub">{{ $rider->isOnlineNow() ? 'Stay Online; new jobs appear here.' : 'Go Online to receive job offers.' }}</p></div>
    @endforelse
</div>

{{-- Active jobs --}}
<h2 class="cx-section-title">My active jobs</h2>
<div style="margin-bottom:28px;">
    @foreach($pickups as $pu)
        <a href="{{ route('courier.rider.pickups.show', $pu->id) }}" class="card cx-item" style="display:block;">
            <div class="cx-item-row">
                <div><p class="cx-item-title">Pickup · {{ $pu->parcel->tracking_number }}</p><p class="cx-item-meta">{{ $pu->parcel->seller->name ?? '' }} — {{ $pu->parcel->pickup_address }}</p></div>
                <span class="cx-pill {{ $pu->status === 'assigned' ? 'cx-pill-amber' : 'cx-pill-blue' }}">{{ $pu->statusLabel() }}</span>
            </div>
        </a>
    @endforeach
    @foreach($deliveries as $d)
        <a href="{{ route('courier.rider.deliveries.show', $d->id) }}" class="card cx-item" style="display:block;">
            <div class="cx-item-row">
                <div><p class="cx-item-title">Delivery · {{ $d->parcel->tracking_number }}</p><p class="cx-item-meta">{{ $d->parcel->receiver_name }} — {{ $d->parcel->dropoff_address }} · attempt {{ $d->attempt }} of {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }}</p></div>
                <span class="cx-pill {{ $d->status === 'assigned' ? 'cx-pill-amber' : 'cx-pill-blue' }}">{{ $d->statusLabel() }}</span>
            </div>
        </a>
    @endforeach
    @foreach($returns as $rp)
        <div class="card cx-item" data-return="{{ $rp->tracking_number }}">
            <div class="cx-item-row">
                <div><p class="cx-item-title">Return to seller · {{ $rp->tracking_number }}</p><p class="cx-item-meta">{{ $rp->seller->name ?? '' }} — {{ $rp->pickup_address }}</p></div>
                <form method="POST" action="{{ route('courier.rider.returns.returned', $rp->id) }}">@csrf
                    <button class="cx-pill cx-pill-blue" style="border:0;cursor:pointer;">Handed back to seller</button>
                </form>
            </div>
        </div>
    @endforeach
    @if($pickups->isEmpty() && $deliveries->isEmpty() && $returns->isEmpty())
        <div class="card cx-empty"><p class="cx-empty-title">No active jobs</p></div>
    @endif
</div>

<script>
// Offer countdown: at 0 the page reloads so the expired offer disappears (it went to the next rider).
document.querySelectorAll('[data-countdown]').forEach(function (el) {
    var end = new Date(el.dataset.countdown).getTime();
    var form = el.closest('[data-offer]').querySelector('[data-accept] button');
    var t = setInterval(function () {
        var s = Math.max(0, Math.round((end - Date.now()) / 1000));
        el.textContent = s;
        if (s <= 0) { clearInterval(t); form.disabled = true; setTimeout(function () { location.reload(); }, 800); }
    }, 1000);
});
// Online riders: look for new offers every 15 seconds.
@if($rider->isOnlineNow() && $offers->isEmpty())
setTimeout(function () { location.reload(); }, 15000);
@endif
</script>
</x-courier-layout>
