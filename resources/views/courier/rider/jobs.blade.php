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
                Your hub assigns you the pickups and deliveries of your barangay. Active jobs: {{ $rider->activeJobsCount() }}.
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

{{-- Tasks, by stage. Each rider sees only their own jobs. --}}
@php
    $newPickups = $pickups->where('status', 'assigned')->whereNull('acknowledged_at');
    $newDeliveries = $deliveries->where('status', 'assigned')->whereNull('acknowledged_at');
    $okPickups = $pickups->where('status', 'assigned')->whereNotNull('acknowledged_at');
    $okDeliveries = $deliveries->where('status', 'assigned')->whereNotNull('acknowledged_at');
    $collected = $pickups->where('status', 'picked_up');
    $active = $deliveries->where('status', 'out_for_delivery');
    $line = 'display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--line, #EEF2F5);';
    $small = 'padding:6px 12px;font-size:12px;';
@endphp

<h2 class="cx-section-title">New assignments</h2>
<div style="margin-bottom:28px;" data-section="new">
    @foreach ([['pickup', $newPickups], ['delivery', $newDeliveries]] as [$kind, $jobs])
        @foreach ($jobs as $job)
            @php $p = $job->parcel; @endphp
            <div class="card cx-item">
                <a href="{{ route($kind === 'pickup' ? 'courier.rider.pickups.show' : 'courier.rider.deliveries.show', $job->id) }}" class="cx-item-row" style="display:flex;">
                    <div>
                        <p class="cx-item-title">{{ ucfirst($kind) }} · {{ $p->waybill_number ?: $p->tracking_number }}</p>
                        <p class="cx-item-meta">{{ $kind === 'pickup' ? 'Collect at ' . ($p->seller->name ?? 'the seller') . ' — ' . $p->pickup_address : 'Collect at ' . $rider->companyName() . ' hub, deliver to ' . ($p->area->name ?? '') . ' ' . $p->destination_municipality }}</p>
                        @if ($kind === 'pickup' && $p->notes)<p class="cx-item-meta">Instructions: {{ $p->notes }}</p>@endif
                    </div>
                    <span class="cx-pill cx-pill-amber">New · {{ $job->created_at->format('M j, g:i A') }}</span>
                </a>
                <div style="{{ $line }}">
                    <form method="POST" action="{{ route('courier.rider.jobs.acknowledge', ['kind' => $kind, 'job' => $job->id]) }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf<button class="cx-btn cx-btn-orange" style="{{ $small }}" data-acknowledge="{{ $kind }}-{{ $job->id }}">Accept</button>
                    </form>
                    {{-- Jobs are assigned per barangay: the rider takes them (the hub reassigns when needed). --}}
                </div>
            </div>
        @endforeach
    @endforeach
    @if ($newPickups->isEmpty() && $newDeliveries->isEmpty())<div class="card cx-empty"><p class="cx-empty-title">No new assignments</p></div>@endif
</div>

<h2 class="cx-section-title">Accepted tasks</h2>
<div style="margin-bottom:28px;" data-section="accepted">
    @foreach ($okPickups as $pu)
        <div class="card cx-item">
            <a href="{{ route('courier.rider.pickups.show', $pu->id) }}" class="cx-item-row" style="display:flex;">
                <div><p class="cx-item-title">Pickup · {{ $pu->parcel->waybill_number ?: $pu->parcel->tracking_number }}</p><p class="cx-item-meta">{{ $pu->parcel->seller->name ?? '' }} — {{ $pu->parcel->pickup_address }}</p></div>
                <span class="cx-pill {{ $pu->en_route_at ? 'cx-pill-blue' : 'cx-pill-amber' }}">{{ $pu->en_route_at ? 'On the way' : 'Accepted' }}</span>
            </a>
            <div style="{{ $line }}">
                <span class="cx-item-meta" data-acknowledged="pickup-{{ $pu->id }}">Accepted {{ $pu->acknowledged_at->format('M j, g:i A') }}</span>
                @unless ($pu->en_route_at)
                    <form method="POST" action="{{ route('courier.rider.pickups.arriving', $pu->id) }}">@csrf<button class="cx-btn cx-btn-outline" style="{{ $small }}" data-arriving="{{ $pu->id }}">On my way</button></form>
                @endunless
                <a href="{{ route('courier.rider.pickups.show', $pu->id) }}" class="cx-btn cx-btn-orange" style="{{ $small }}">Confirm pickup at seller</a>
            </div>
        </div>
    @endforeach
    @foreach ($okDeliveries as $d)
        <div class="card cx-item">
            <a href="{{ route('courier.rider.deliveries.show', $d->id) }}" class="cx-item-row" style="display:flex;">
                <div><p class="cx-item-title">Delivery · {{ $d->parcel->waybill_number ?: $d->parcel->tracking_number }}</p><p class="cx-item-meta">{{ $d->parcel->receiver_name }} — {{ $d->parcel->dropoff_address }} · attempt {{ $d->attempt }} of {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }}</p></div>
                <span class="cx-pill cx-pill-amber">Collect at hub</span>
            </a>
            <div style="{{ $line }}"><span class="cx-item-meta" data-acknowledged="delivery-{{ $d->id }}">Accepted {{ $d->acknowledged_at->format('M j, g:i A') }}</span></div>
        </div>
    @endforeach
    @if ($okPickups->isEmpty() && $okDeliveries->isEmpty())<div class="card cx-empty"><p class="cx-empty-title">Nothing accepted yet</p></div>@endif
</div>

<h2 class="cx-section-title">Packages collected</h2>
<div style="margin-bottom:28px;" data-section="collected">
    @forelse ($collected as $pu)
        <a href="{{ route('courier.rider.pickups.show', $pu->id) }}" class="card cx-item" style="display:block;">
            <div class="cx-item-row"><div><p class="cx-item-title">{{ $pu->parcel->waybill_number ?: $pu->parcel->tracking_number }}</p><p class="cx-item-meta">Take it to {{ $rider->companyName() }} hub. The hub confirms receipt when you hand it over.</p></div>
            <span class="cx-pill cx-pill-blue">Collected</span></div>
        </a>
    @empty
        <div class="card cx-empty"><p class="cx-empty-title">No packages on the way to the hub</p></div>
    @endforelse
</div>

<h2 class="cx-section-title">Active deliveries</h2>
<div style="margin-bottom:28px;" data-section="active">
    @forelse ($active as $d)
        <a href="{{ route('courier.rider.deliveries.show', $d->id) }}" class="card cx-item" style="display:block;">
            <div class="cx-item-row"><div><p class="cx-item-title">{{ $d->parcel->waybill_number ?: $d->parcel->tracking_number }}</p><p class="cx-item-meta">{{ $d->parcel->receiver_name }} — {{ $d->parcel->dropoff_address }}</p></div>
            <span class="cx-pill cx-pill-blue">Out for delivery</span></div>
        </a>
    @empty
        <div class="card cx-empty"><p class="cx-empty-title">No deliveries in progress</p></div>
    @endforelse
</div>

@if ($transfers->isNotEmpty())
<h2 class="cx-section-title">Hub transfer tasks</h2>
<div style="margin-bottom:28px;" data-section="transfers">
    @foreach ($transfers as $t)
        <div class="card cx-item"><div class="cx-item-row">
            <div><p class="cx-item-title">{{ $t->reference }} · {{ $t->parcel->waybill_number ?: $t->parcel->tracking_number }}</p>
                <p class="cx-item-meta">{{ \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }} → {{ \App\Support\SortingCenters::nameOf($t->toSortingCenter) }}. The hubs confirm dispatch and receipt.</p></div>
            <span class="cx-pill {{ $t->isInTransit() ? 'cx-pill-blue' : 'cx-pill-amber' }}">{{ $t->statusLabel() }}</span>
        </div></div>
    @endforeach
</div>
@endif

<h2 class="cx-section-title">Returned packages</h2>
<div style="margin-bottom:28px;" data-section="returns">
    @foreach ($returnJobs as $rj)
        <div class="card cx-item" data-return-job="{{ $rj->id }}">
            <div class="cx-item-row">
                @if ($rj->status === 'pickup_assigned')
                    <div><p class="cx-item-title">Collect return · {{ $rj->order?->number() }}</p><p class="cx-item-meta">Buyer {{ $rj->order?->full_name }} — {{ $rj->buyerAddress() }}</p>
                        <p class="cx-item-meta">{{ $rj->acknowledged_at ? 'Accepted · photo + waybill scan at the buyer' : 'New: accept it first' }}</p></div>
                    <a href="{{ route('courier.rider.return-pickups.show', $rj->id) }}" class="cx-pill cx-pill-amber">Open</a>
                @else
                    <div><p class="cx-item-title">Return to seller · {{ $rj->order?->number() }}</p><p class="cx-item-meta">{{ $rj->parcel?->seller?->storeName() ?? $rj->parcel?->seller?->name }} — {{ $rj->sellerAddress() }}</p></div>
                    <a href="{{ route('courier.rider.return-pickups.show', $rj->id) }}" class="cx-pill cx-pill-blue">Open</a>
                @endif
            </div>
        </div>
    @endforeach
    @forelse ($returns as $rp)
        <div class="card cx-item" data-return="{{ $rp->tracking_number }}">
            <div class="cx-item-row">
                <div><p class="cx-item-title">Return to seller · {{ $rp->tracking_number }}</p><p class="cx-item-meta">{{ $rp->seller->name ?? '' }} — {{ $rp->pickup_address }}</p></div>
                <form method="POST" action="{{ route('courier.rider.returns.returned', $rp->id) }}">@csrf
                    <button class="cx-pill cx-pill-blue" style="border:0;cursor:pointer;">Handed back to seller</button>
                </form>
            </div>
        </div>
    @empty
        @if ($returnJobs->isEmpty())<div class="card cx-empty"><p class="cx-empty-title">No returns to bring back</p></div>@endif
    @endforelse
</div>

<h2 class="cx-section-title">Recent results</h2>
<div style="margin-bottom:28px;display:grid;gap:8px;" data-section="history">
    @forelse ($recent as $d)
        <div class="card cx-item"><div class="cx-item-row">
            <div><p class="cx-item-title">{{ $d->parcel->waybill_number ?: $d->parcel->tracking_number }}</p><p class="cx-item-meta">{{ ($d->delivered_at ?? $d->updated_at)->format('M j, g:i A') }}{{ $d->failure_reason ? ' · ' . $d->failure_reason : '' }}</p></div>
            <span class="cx-pill {{ $d->status === 'delivered' ? 'cx-pill-green' : 'cx-pill-red' }}">{{ $d->status === 'delivered' ? 'Completed' : ($d->status === 'failed' ? 'Failed attempt' : $d->statusLabel()) }}</span>
        </div></div>
    @empty
        <div class="card cx-empty"><p class="cx-empty-title">No finished deliveries yet</p></div>
    @endforelse
    <a href="{{ route('courier.rider.history') }}" class="cx-item-meta" style="text-align:right;">Full history →</a>
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
