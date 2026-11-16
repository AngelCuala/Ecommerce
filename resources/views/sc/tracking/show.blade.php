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
        {{-- Waybill: the same barcode as the printed label (scannable from the screen too) --}}
        <style>.wb-code svg { display:block; width:100%; height:46px; }</style>
        <div data-waybill style="display:flex;flex-wrap:wrap;align-items:center;gap:14px 18px;padding:14px 16px;border-bottom:1px solid var(--border);background:#FAFBFC;">
            <div style="flex:1 1 260px;min-width:0;">
                <div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);">Waybill</div>
                <div class="wb-code" style="margin-top:6px;max-width:340px;background:#fff;border:1px solid var(--border);border-radius:8px;padding:8px 10px 6px;">
                    {!! \App\Support\Barcode::svg($parcel->waybill_number ?: $parcel->tracking_number, 46, 2) !!}
                    <div style="margin-top:4px;text-align:center;font-family:'SF Mono',Consolas,monospace;font-size:14px;font-weight:700;letter-spacing:.08em;color:var(--text);" data-waybill-number>{{ $parcel->waybill_number ?: $parcel->tracking_number }}</div>
                </div>
            </div>
            @if ((int) $parcel->current_sorting_center_id === (int) $me)
                <a href="{{ route('sc.parcels.waybill', $parcel) }}" target="_blank" rel="noopener" class="btn btn-blue" style="gap:6px;" data-print-waybill>
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print waybill
                </a>
            @endif
        </div>
        <table class="data-table">
            <tr><td class="text-muted" style="width:38%;">Seller (pickup)</td><td>{{ $parcel->seller?->storeName() ?? $parcel->seller?->name }} — {{ $parcel->pickup_address }}</td></tr>
            <tr><td class="text-muted">Deliver to</td><td>{{ $parcel->receiver_name }} — {{ $parcel->destination_municipality }}, {{ $parcel->destination_province }}</td></tr>
            <tr><td class="text-muted">Items</td><td>{{ $parcel->order?->items->map(fn ($i) => ($i->product->title ?? 'Item') . ' ×' . $i->quantity)->implode(', ') }}</td></tr>
            <tr><td class="text-muted">Current location</td><td>{{ $parcel->status === 'in_transfer' ? 'In transit between sorting centers' : (\App\Support\SortingCenters::nameOf($parcel->currentSortingCenter) ?? '—') }}</td></tr>
            <tr><td class="text-muted">Next destination</td><td>{{ $next['label'] ?? ($transfer ? 'Transfer to ' . \App\Support\SortingCenters::nameOf($transfer->toSortingCenter) : '—') }}</td></tr>
            @if ($parcel->received_at)<tr><td class="text-muted">Received</td><td>{{ $parcel->received_at->copy()->tz(config('app.business_timezone'))->format('M j, Y g:i A') }} by {{ $parcel->receivedBy?->name ?? '—' }}</td></tr>@endif
        </table>
    </div>

    @if ($parcel->status === 'pickup_approved' && (int) $parcel->current_sorting_center_id === $me)
    <div class="card" style="padding:14px 16px;font-size:13px;color:var(--text-muted);">
        Waiting for pickup. You can receive this parcel after the rider marks it <strong>Picked Up</strong> at the seller.
    </div>
    @endif

    @php $pu = $parcel->pickups->whereIn('status', ['picked_up', 'received'])->sortByDesc('id')->first(); @endphp
    @if ($pu)
    <div class="card" style="padding:14px 16px;font-size:13px;">
        <strong>Picked up by {{ $pu->rider?->full_name ?? '—' }}</strong>
        @if ($pu->picked_up_at) on {{ $pu->picked_up_at->copy()->tz(config('app.business_timezone'))->format('M j, g:i A') }}@endif · waybill scanned
        @if ($pu->proof_path)
            <div style="margin-top:10px;"><a href="{{ \App\Support\ProtectedFile::url($pu->proof_path) }}" target="_blank" rel="noopener">
                <img src="{{ \App\Support\ProtectedFile::url($pu->proof_path) }}" alt="Proof of pickup" style="max-height:220px;border-radius:8px;border:1px solid var(--border);"></a></div>
        @else
            <div class="text-muted" style="margin-top:6px;">No proof photo (picked up from the mobile app).</div>
        @endif
    </div>
    @endif

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

    {{-- What happens to the parcel next, as steps: done (green), now (orange, with its one button), still to come. --}}
    @if ($parcel->isAtHub() && (int) $parcel->current_sorting_center_id === $me && ! $transfer)
    @php
        $type = $next['type'] ?? 'review';
        $local = $type === 'local';
        $sortedForCenter = $parcel->status === 'sorted' && $parcel->next_center_id;
        $toCenter = $sortedForCenter ? \App\Models\User::find($parcel->next_center_id) : ($next['center'] ?? null);
        $toName = $toCenter ? \App\Support\SortingCenters::displayName($toCenter) : 'the other sorting center';
        $openDelivery = $parcel->deliveries->first(fn ($d) => in_array($d->status, \App\Models\ParcelDelivery::OPEN, true));
        $received = ['Received at hub', $parcel->received_at?->copy()->tz(config('app.business_timezone'))->format('M j, g:i A'), 'done'];

        if ($local) {
            $sorted = $parcel->status === 'sorted';
            $steps = [
                $received,
                ['Sort to barangay', $sorted ? ($parcel->area?->name) : null, $sorted ? 'done' : 'now'],
                ['Give to a rider', $openDelivery ? $openDelivery->rider?->full_name : null, $openDelivery ? 'done' : ($sorted ? 'now' : 'todo')],
                ['Delivered to buyer', null, $openDelivery ? 'now' : 'todo'],
            ];
        } else {
            $steps = [
                $received,
                // Putting it on a trip also counts as sorting it for that center.
                ['Sort for ' . ($toCenter ? $toName : 'a sorting center'), null, ($sortedForCenter || $trip) ? 'done' : 'now'],
                ['Put on a linehaul trip', $trip ? $trip->code : null, $trip ? 'done' : ($sortedForCenter ? 'now' : 'todo')],
                ['Received by ' . $toName, $trip ? $trip->label() : null, $trip ? 'now' : 'todo'],
            ];
        }
    @endphp
    <div class="card" data-next-steps>
        <div class="card-header"><h2>{{ $local ? 'Sort to barangay' : 'Send to another sorting center' }}</h2></div>
        <div style="padding:16px;">
            <p class="text-muted" style="font-size:12.5px;margin:0 0 4px;">
                @if ($local)
                    The buyer lives in <strong>{{ $parcel->destination_municipality }}</strong>, which this center serves: a rider of the buyer's barangay delivers it.
                @elseif ($type === 'transfer' || $sortedForCenter)
                    This center doesn't deliver to <strong>{{ $parcel->destination_municipality ?: 'this address' }}</strong>. A linehaul trip takes it to <strong>{{ $toName }}</strong> (serves this address), which delivers it.
                @else
                    No sorting center is set up for <strong>{{ $parcel->destination_municipality ?: 'this address' }}</strong> yet. Choose the one that should deliver it.
                @endif
            </p>

            <ol class="ps-steps" aria-label="Steps">
                @foreach ($steps as $i => [$stepLabel, $at, $state])
                    <li class="ps-step is-{{ $state }}">
                        <span class="ps-dot">@if ($state === 'done')<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>@else{{ $i + 1 }}@endif</span>
                        <span class="ps-label">{{ $stepLabel }}</span>
                        @if ($at)<span class="ps-at">{{ $at }}</span>@endif
                    </li>
                @endforeach
            </ol>

            <div class="ps-next">
                @if ($local && in_array($parcel->status, ['picked_up', 'sorting'], true))
                    {{-- Step 2: sort it to the buyer's barangay. --}}
                    <form method="POST" action="{{ route('sc.parcels.sort', $parcel) }}" class="ps-form" data-sort-form>
                        @csrf
                        <div style="flex:1 1 220px;">
                            <label style="{{ $label }}" for="area_id"><strong>Next:</strong> choose the buyer's barangay</label>
                            <select name="area_id" id="area_id" class="form-input" required>
                                <option value="">— Choose —</option>
                                @foreach ($areas as $a)<option value="{{ $a->id }}" @selected((int) old('area_id', $suggestedArea?->id) === $a->id)>{{ $a->name }}{{ $suggestedArea?->id === $a->id ? ' (from the address)' : '' }}</option>@endforeach
                            </select>
                            <p class="text-muted" style="font-size:11.5px;margin-top:4px;">Deliver to: {{ $parcel->dropoff_address }}</p>
                            @error('area_id')<p style="color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn btn-blue">Sort to barangay</button>
                    </form>
                @elseif ($local && $parcel->status === 'sorted')
                    <p class="ps-hint" data-sorted-to>Sorted to <strong>{{ $parcel->area?->name ?? 'a barangay' }}</strong>. <strong>Next:</strong> give it to that barangay's rider in Rider Dispatch.</p>
                    <a href="{{ route('sc.dispatch') }}" class="btn btn-blue">Open Rider Dispatch</a>
                @elseif (! $local && $trip)
                    <p class="ps-hint">On trip <strong>{{ $trip->code }}</strong>: {{ \Illuminate\Support\Str::lcfirst($trip->stageLabel()) }}. {{ $toName }} receives it when the truck arrives.</p>
                    <a href="{{ route('sc.trips.show', $trip) }}" class="btn btn-blue">Open trip</a>
                @elseif (! $local && ! $sortedForCenter && in_array($parcel->status, ['picked_up', 'sorting'], true))
                    {{-- Step 2: sort it for the sorting center that serves the address. --}}
                    <form method="POST" action="{{ route('sc.parcels.sort-transfer', $parcel) }}" class="ps-form">
                        @csrf
                        <div style="flex:1 1 220px;">
                            <label style="{{ $label }}" for="center_id"><strong>Next:</strong> sort it for the sorting center that delivers it</label>
                            <select name="center_id" id="center_id" class="form-input" required>
                                <option value="">— Choose —</option>
                                @foreach ($hubs as $h)<option value="{{ $h->id }}" @selected((int) old('center_id', $next['center']->id ?? 0) === $h->id)>{{ \App\Support\SortingCenters::displayName($h) }}{{ ($next['center']->id ?? 0) === $h->id ? ' (serves this address)' : '' }}</option>@endforeach
                            </select>
                            @error('center_id')<p style="color:#B91C1C;font-size:12px;">{{ $message }}</p>@enderror
                        </div>
                        <button class="btn btn-blue">Sort for this center</button>
                    </form>
                @elseif (! $local && ! $trip)
                    <p class="ps-hint"><strong>Next:</strong> put it on a linehaul trip to {{ $toName }}. Parcels sorted for the same center travel together.</p>
                    <a href="{{ route('sc.trips', ['parcel' => [$parcel->id], 'origin_hub_id' => $parcel->current_hub_id]) }}#create" class="btn btn-blue">Send on a linehaul trip</a>
                @endif
            </div>

            @if ($local && in_array($parcel->status, ['picked_up', 'sorting'], true))
                {{-- Rarely needed: a local parcel can still go to another center. --}}
                <p class="text-muted" style="font-size:12px;margin:12px 0 0;">
                    Or send it to another sorting center on a linehaul trip:
                    <a href="{{ route('sc.trips', ['parcel' => [$parcel->id], 'origin_hub_id' => $parcel->current_hub_id]) }}#create" style="color:var(--accent-blue);font-weight:600;">Send on a linehaul trip</a>
                </p>
            @endif
        </div>
    </div>
    @push('styles')
    <style>
        .ps-steps { list-style: none; margin: 16px 0 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); }
        .ps-step { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 5px; min-width: 0; padding: 0 4px; }
        .ps-step:not(:last-child)::after { content: ""; position: absolute; top: 12px; left: calc(50% + 16px); right: calc(-50% + 16px); height: 2px; background: var(--border); }
        .ps-step.is-done:not(:last-child)::after { background: var(--accent-green); }
        .ps-dot { width: 26px; height: 26px; border-radius: 999px; display: grid; place-items: center; font-size: 12px; font-weight: 700;
                  background: var(--border-dim); color: var(--text-muted); border: 2px solid var(--border); }
        .ps-step.is-done .ps-dot { background: var(--accent-green); border-color: var(--accent-green); color: #fff; }
        .ps-step.is-now .ps-dot { background: #fff1ee; border-color: var(--accent-blue); color: var(--accent-blue); }
        .ps-label { font-size: 12px; font-weight: 600; color: var(--text-sub); line-height: 1.3; }
        .ps-step.is-now .ps-label { color: var(--text); }
        .ps-at { font-size: 11px; color: var(--text-muted); }
        .ps-next { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
                   margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-dim); }
        .ps-next:empty { display: none; }
        .ps-hint { font-size: 12.5px; color: var(--text-sub); margin: 0; flex: 1 1 260px; }
        .ps-hint strong { color: var(--text); }
        .ps-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; }
    </style>
    @endpush
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
    @if ((int) $parcel->current_sorting_center_id === (int) $me && ! in_array($parcel->status, \App\Models\Parcel::CLOSED_STATUSES, true))
    {{-- Lost / damaged: closes the parcel and opens a claim. --}}
    <div class="card">
        <div style="padding:14px 16px;display:grid;gap:10px;font-size:13px;">
                <details data-mark-lost>
                    <summary style="cursor:pointer;font-size:12.5px;font-weight:600;color:#B91C1C;">Mark lost or damaged…</summary>
                    <form method="POST" action="{{ route('sc.parcels.exception', $parcel) }}" enctype="multipart/form-data" style="display:grid;gap:8px;margin-top:8px;"
                          data-confirm-title="Mark this parcel?" data-confirm="The parcel is closed and a claim is opened. The buyer and seller are notified." data-confirm-ok="Mark it">
                        @csrf
                        <select name="type" class="form-input" aria-label="Lost or damaged"><option value="lost">Lost</option><option value="damaged">Damaged</option></select>
                        <input name="reason" class="form-input" placeholder="What happened (10–300 characters)" minlength="10" maxlength="300" required aria-label="Reason">
                        <label class="text-muted" style="font-size:12px;">Photo (needed for Damaged) <input type="file" name="photo" accept="image/jpeg,image/png" style="font-size:11px;margin-top:2px;"></label>
                        <button class="btn btn-red btn-sm" style="justify-self:start;">Mark {{ $parcel->tracking_number }}</button>
                    </form>
                </details>
        </div>
    </div>
    @endif

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
