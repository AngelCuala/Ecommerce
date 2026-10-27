@extends('sc.layout')
@section('title', 'Shipment ' . $parcel->tracking_number)
@section('icon', 'list')

@php
    $me = auth()->id();
    $label = 'font-size:11.5px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;';
@endphp

@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
    <div>
        <h1>{{ $parcel->waybill_number ?: $parcel->tracking_number }}</h1>
        <p class="text-muted" style="font-size:12.5px;">Tracking {{ $parcel->tracking_number }} · {{ $parcel->order?->number() }} · <strong>{{ $parcel->statusLabel() }}</strong></p>
    </div>
    <a href="{{ route('sc.tracking') }}" class="btn btn-ghost">Back to board</a>
</div>
<div class="page-body" style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:18px;align-items:start;">

<div style="display:flex;flex-direction:column;gap:18px;">
    {{-- Package --}}
    <div class="card">
        <div class="card-header"><h2>Package</h2></div>
        <table class="data-table">
            <tr><td class="text-muted" style="width:38%;">Seller (pickup)</td><td>{{ $parcel->seller?->storeName() ?? $parcel->seller?->name }} — {{ $parcel->pickup_address }}</td></tr>
            <tr><td class="text-muted">Deliver to</td><td>{{ $parcel->receiver_name }} — {{ $parcel->destination_municipality }}, {{ $parcel->destination_province }}</td></tr>
            <tr><td class="text-muted">Items</td><td>{{ $parcel->order?->items->map(fn ($i) => ($i->product->title ?? 'Item') . ' ×' . $i->quantity)->implode(', ') }}</td></tr>
            <tr><td class="text-muted">Current location</td><td>{{ $parcel->status === 'in_transfer' ? 'In transit between sorting centers' : (\App\Support\SortingCenters::nameOf($parcel->currentSortingCenter) ?? '—') }}</td></tr>
            <tr><td class="text-muted">Next destination</td><td>{{ $next['label'] ?? ($transfer ? 'Transfer to ' . \App\Support\SortingCenters::nameOf($transfer->toSortingCenter) : '—') }}</td></tr>
            @if ($parcel->received_at)<tr><td class="text-muted">Received</td><td>{{ $parcel->received_at->format('M j, Y g:i A') }} by {{ $parcel->receivedBy?->name ?? '—' }}</td></tr>@endif
        </table>
    </div>

    {{-- Confirm receipt: explicit, after checking the package --}}
    @if ($canReceive)
    <div class="card" style="border:2px solid var(--accent-blue);">
        <div class="card-header"><h2>Confirm Package Received</h2></div>
        <form method="POST" action="{{ route('sc.tracking.receive', $parcel) }}" style="padding:16px;display:grid;gap:12px;"
              onsubmit="return confirm('Record that {{ $parcel->tracking_number }} is physically at your center?')">
            @csrf
            @if ($transfer && $transfer->isInTransit() && (int) $transfer->to_sorting_center_id !== $me)
                <p style="background:#FEF2F2;color:#B91C1C;padding:10px;border-radius:8px;font-size:13px;">
                    This parcel is addressed to <strong>{{ \App\Support\SortingCenters::nameOf($transfer->toSortingCenter) }}</strong>, not your center. Confirming records a wrong-center exception for an administrator; it will not be received here.
                </p>
            @endif
            <p style="font-size:13px;">Check the label matches <strong>{{ $parcel->waybill_number }}</strong> and the receiver <strong>{{ $parcel->receiver_name }}</strong>, then record its condition.</p>
            <div style="display:flex;gap:16px;font-size:13px;">
                <label><input type="radio" name="condition" value="good" checked> Good condition</label>
                <label><input type="radio" name="condition" value="damaged"> Damaged</label>
            </div>
            <div><label style="{{ $label }}">Notes (required if damaged)</label><textarea name="notes" rows="2" class="form-input" maxlength="500">{{ old('notes') }}</textarea></div>
            <label style="font-size:13px;display:flex;gap:8px;align-items:flex-start;"><input type="checkbox" name="confirm_received" value="1" required> The parcel is physically here and I checked it.</label>
            @error('confirm_received')<p style="color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
            <button class="btn btn-blue" style="justify-self:start;">Confirm received</button>
        </form>
    </div>
    @endif

    {{-- Sorting / transfer --}}
    @if ($parcel->isAtHub() && (int) $parcel->current_sorting_center_id === $me && ! $transfer)
    <div class="card">
        @php $local = ($next['type'] ?? null) === 'local'; @endphp
        <div class="card-header"><h2>{{ $local ? 'Sort to barangay' : 'Sort or transfer' }}</h2></div>
        <div style="padding:16px;display:grid;gap:14px;">
            @if (! $local && $parcel->status === 'picked_up')
                <form method="POST" action="{{ route('sc.tracking.start-sorting', $parcel) }}" onsubmit="return confirm('Start sorting?')">@csrf<button class="btn btn-ghost">Start sorting</button></form>
            @endif

            @if ($local && in_array($parcel->status, ['picked_up', 'sorting'], true))
                {{-- The buyer lives in this hub's town: sort it to their barangay for last-mile delivery, no transfer. --}}
                <p style="font-size:13px;">The buyer lives in <strong>{{ $parcel->destination_municipality }}</strong>, which this center serves. Sort it to the buyer's barangay and the delivery is offered to your riders.</p>
                <form method="POST" action="{{ route('sc.parcels.sort', $parcel) }}" style="display:grid;gap:10px;" data-sort-form>
                    @csrf
                    <div>
                        <label style="{{ $label }}" for="area_id">Buyer's barangay</label>
                        <select name="area_id" id="area_id" class="form-input" required>
                            <option value="">— Choose —</option>
                            @foreach ($areas as $a)<option value="{{ $a->id }}" @selected((int) old('area_id', $suggestedArea?->id) === $a->id)>{{ $a->name }}{{ $suggestedArea?->id === $a->id ? ' (from the address)' : '' }}</option>@endforeach
                        </select>
                        <p class="text-muted" style="font-size:11.5px;margin-top:4px;">Deliver to: {{ $parcel->dropoff_address }}</p>
                    </div>
                    @error('area_id')<p style="color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
                    <button class="btn btn-blue" style="justify-self:start;">Sort to barangay</button>
                </form>
            @elseif ($local && $parcel->status === 'sorted')
                <p style="font-size:13px;" data-sorted-to>Sorted to <strong>{{ $parcel->area?->name ?? 'a barangay' }}</strong> and ready for last-mile delivery.
                    <a href="{{ route('sc.dispatch') }}" class="id-link">Rider Dispatch</a> shows who has the job.</p>
            @endif

            @if (in_array($parcel->status, ['picked_up', 'sorting', 'sorted'], true))
            @if ($local)<details><summary class="text-muted" style="font-size:12.5px;cursor:pointer;">Send to another sorting center instead</summary>@endif
            <form method="POST" action="{{ route('sc.transfers.initiate') }}" style="display:grid;gap:10px;{{ $local ? 'margin-top:10px;' : '' }}" onsubmit="return confirm('Create this transfer?')">
                @csrf
                <input type="hidden" name="parcel_id" value="{{ $parcel->id }}">
                <div>
                    <label style="{{ $label }}">Transfer to sorting center</label>
                    <select name="to_sorting_center_id" class="form-input" required>
                        <option value="">— Choose —</option>
                        @foreach ($hubs as $h)<option value="{{ $h->id }}" @selected(($next['center']->id ?? null) === $h->id)>{{ \App\Support\SortingCenters::displayName($h) }} — {{ $h->assigned_municipality }}{{ ($next['center']->id ?? null) === $h->id ? ' (serves this address)' : '' }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label style="{{ $label }}">Transporter (optional)</label>
                    <select name="transporter_rider_id" class="form-input">
                        <option value="">— None —</option>
                        @foreach ($transporters as $r)<option value="{{ $r->id }}">{{ $r->full_name }}</option>@endforeach
                    </select>
                    @if ($transporters->isEmpty())<p class="text-muted" style="font-size:11.5px;margin-top:4px;">No riders are allowed to transport transfers yet (set it on the rider's page).</p>@endif
                </div>
                <div><label style="{{ $label }}">Reason (required if not the suggested center)</label><input name="reason" class="form-input" maxlength="500"></div>
                @error('to_sorting_center_id')<p style="color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
                <button class="btn btn-{{ $local ? 'ghost' : 'blue' }}" style="justify-self:start;">Create transfer</button>
            </form>
            @if ($local)</details>@endif
            @endif
        </div>
    </div>
    @endif

    {{-- Active transfer --}}
    @if ($transfer)
    <div class="card">
        <div class="card-header"><h2>Transfer {{ $transfer->reference }}</h2><span class="badge {{ $transfer->isDelayed() || $transfer->status === 'exception' ? 'badge-red' : 'badge-blue' }}">{{ $transfer->statusLabel() }}</span></div>
        <div style="padding:16px;font-size:13px;display:grid;gap:8px;">
            <div>{{ \App\Support\SortingCenters::nameOf($transfer->fromSortingCenter) }} → {{ \App\Support\SortingCenters::nameOf($transfer->toSortingCenter) }}</div>
            @if ($transfer->transporter)<div class="text-muted">Transporter: {{ $transfer->transporter->full_name }}</div>@endif
            @if ($transfer->dispatched_at)<div class="text-muted">Dispatched {{ $transfer->dispatched_at->format('M j, g:i A') }} · due {{ $transfer->expected_by?->format('M j, g:i A') }}</div>@endif
            @if ($transfer->exception_reason)<div style="color:#B91C1C;">{{ $transfer->exception_reason }}</div>@endif
            @if ($transfer->isPending() && (int) $transfer->from_sorting_center_id === $me)
                <div style="display:flex;gap:8px;">
                    <form method="POST" action="{{ route('sc.transfers.dispatch', $transfer) }}" onsubmit="return confirm('Confirm the parcel is leaving now?')">@csrf<button class="btn btn-blue btn-sm">Confirm dispatch</button></form>
                    <form method="POST" action="{{ route('sc.transfers.cancel', $transfer) }}" onsubmit="return confirm('Cancel this transfer? The parcel stays here.')">@csrf<button class="btn btn-ghost btn-sm" style="color:#DC2626;">Cancel transfer</button></form>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Full history (staff see internal steps too) --}}
    <div class="card">
        <div class="card-header"><h2>Tracking history</h2></div>
        <ol style="padding:14px 18px;display:grid;gap:10px;">
            @forelse ($events as $e)
                <li style="display:grid;grid-template-columns:130px 1fr;gap:10px;font-size:13px;" data-event="{{ $e->event_type }}">
                    <span class="text-muted" style="font-size:12px;">{{ $e->occurred_at->format('M j, g:i A') }}</span>
                    <span>
                        <strong>{{ $e->label() }}</strong>
                        @unless ($e->isPublic())<span class="badge badge-gray" style="margin-left:4px;">internal</span>@endunless
                        @if ($e->note)<span class="text-muted"> — {{ $e->note }}</span>@endif
                        @if ($e->corrects)<div class="text-muted" style="font-size:11.5px;">Corrects: {{ $e->corrects->label() }} ({{ $e->corrects->occurred_at->format('M j, g:i A') }})</div>@endif
                        <div class="text-muted" style="font-size:11.5px;">{{ $e->actor?->name ? 'By ' . $e->actor->name : 'System' }}@if ($e->from_status) · {{ $e->from_status }} → {{ $e->to_status }}@endif</div>
                    </span>
                </li>
            @empty
                <li class="text-muted" style="font-size:13px;">No events yet.</li>
            @endforelse
        </ol>
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:18px;">
    {{-- Exceptions --}}
    <div class="card">
        <div class="card-header"><h2>Exceptions</h2></div>
        <div style="padding:14px 16px;display:grid;gap:10px;font-size:13px;">
            @forelse ($parcel->exceptions->sortByDesc('id') as $x)
                <div style="border:1px solid var(--border);border-radius:8px;padding:10px;" data-exception="{{ $x->type }}">
                    <span class="badge {{ $x->isOpen() ? 'badge-red' : 'badge-green' }}">{{ $x->isOpen() ? 'Open' : 'Resolved' }}</span>
                    <strong style="margin-left:6px;">{{ $x->typeLabel() }}</strong>
                    <p class="text-muted" style="margin-top:4px;">{{ $x->details }}</p>
                    @if ($x->resolution)<p style="margin-top:4px;">Resolution: {{ $x->resolution }}</p>@endif
                </div>
            @empty
                <p class="text-muted">None.</p>
            @endforelse
            <form method="POST" action="{{ route('sc.tracking.exception', $parcel) }}" style="display:grid;gap:8px;border-top:1px solid var(--border);padding-top:12px;"
                  onsubmit="return confirm('Flag this shipment for review?')">
                @csrf
                <label style="{{ $label }}">Flag shipment exception</label>
                <select name="type" class="form-input" required>
                    <option value="damaged_on_arrival">Damaged</option>
                    <option value="missing">Missing / not found</option>
                    <option value="other">Other</option>
                </select>
                <textarea name="details" rows="2" class="form-input" placeholder="What is wrong?" required minlength="5" maxlength="500"></textarea>
                @error('details')<p style="color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
                <button class="btn btn-ghost" style="justify-self:start;color:#DC2626;">Flag exception</button>
            </form>
        </div>
    </div>

    {{-- Transfers and riders --}}
    <div class="card">
        <div class="card-header"><h2>Transfers &amp; riders</h2></div>
        <div style="padding:14px 16px;display:grid;gap:8px;font-size:12.5px;">
            @foreach ($parcel->transfers->sortBy('id') as $t)
                <div>{{ $t->reference }}: {{ \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }} → {{ \App\Support\SortingCenters::nameOf($t->toSortingCenter) }} · <strong>{{ $t->statusLabel() }}</strong>
                    <span class="text-muted">{{ ($t->received_at ?? $t->dispatched_at ?? $t->created_at)->format('M j, g:i A') }}</span></div>
            @endforeach
            @foreach ($parcel->pickups->sortBy('id') as $pu)
                <div>Pickup rider {{ $pu->rider?->full_name }} · {{ $pu->statusLabel() }}{{ $pu->remarks ? ' (' . $pu->remarks . ')' : '' }}</div>
            @endforeach
            @foreach ($parcel->deliveries->sortBy('id') as $d)
                <div>Delivery attempt {{ $d->attempt }}: {{ $d->rider?->full_name }} · {{ $d->statusLabel() }}{{ $d->failure_reason ? ' (' . $d->failure_reason . ')' : '' }}</div>
            @endforeach
            @if ($parcel->transfers->isEmpty() && $parcel->pickups->isEmpty() && $parcel->deliveries->isEmpty())<p class="text-muted">None yet.</p>@endif
        </div>
    </div>
</div>
</div>
@endsection
