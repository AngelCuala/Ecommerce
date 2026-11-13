@extends('sc.layout')
@section('title', $trip->code)
@section('icon', 'truck')

@php
    $labels = \App\Http\Controllers\SortingCenter\TripsController::PARCEL_LABELS;
    $eventLabels = \App\Http\Controllers\SortingCenter\TripsController::EVENT_LABELS;
    $onBoard = $items->where('status', '!=', 'removed');
    $toReceive = $onBoard->whereIn('status', ['pending', 'loaded']);
    $parcelBadge = fn ($s) => match ($s) {
        'received' => 'badge-green', 'loaded' => 'badge-blue', 'damaged', 'missing', 'missing_in_transit' => 'badge-red', 'removed' => 'badge-gray', default => 'badge-yellow',
    };
    $senderName = \App\Support\SortingCenters::nameOf($trip->company);
    $receiverName = \App\Support\SortingCenters::nameOf($trip->destinationCompany ?? $trip->company);
    // The five delivery steps (LinehaulTrip::STAGES) and when each was reached.
    $stage = $trip->stage();
    $stageAt = [1 => $trip->created_at, 2 => $trip->assigned_at, 3 => $stage >= 3 ? ($trip->items->where('status', 'loaded')->max('loaded_at') ?? $trip->loading_at) : null,
        4 => $trip->departed_at, 5 => $trip->completed_at];
    $steps = collect(\App\Models\LinehaulTrip::STAGES)->map(fn ($label, $n) => [
        $n === 5 ? 'Received by ' . ($trip->destination->code ?? 'the hub') : $label, $stage >= $n ? ($stageAt[$n] ?? $trip->updated_at) : null, $n === $stage])->values()->all();
    $canMessage = $trip->crossCompany();
@endphp

@section('content')
<style>
    .ts-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); align-items: start; }
    @media (max-width: 1000px) { .ts-grid { grid-template-columns: 1fr; } }
    .ts-route { display: flex; align-items: center; gap: 14px; padding: 18px; }
    .ts-hub { flex: 1; }
    .ts-hub b { display: block; font-size: 20px; font-weight: 800; }
    .ts-hub span { display: block; font-size: 12.5px; color: var(--text-muted); }
    .ts-steps { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 6px; padding: 0 18px 18px; }
    .ts-step { font-size: 11.5px; color: var(--text-muted); }
    .ts-step i { display: block; height: 5px; border-radius: 99px; background: #E2E8F0; margin-bottom: 6px; }
    .ts-step.is-done i { background: var(--accent-orange); }
    .ts-step.is-done b { color: var(--text); }
    .ts-step.is-now b { color: var(--accent-orange); }
    @media (max-width: 700px) { .ts-steps { grid-auto-flow: row; grid-auto-columns: auto; } }
    .ts-next { margin: 0 18px 16px; padding: 10px 12px; border-radius: 10px; background: #F0F7FF; color: #1E3A5F; font-size: 13px; }
    .ts-kv { display: flex; justify-content: space-between; gap: 10px; padding: 7px 0; font-size: 13px; border-bottom: 1px solid #F1F5F9; }
    .ts-kv span:first-child { color: var(--text-muted); }
    /* History: a compact timeline, newest first. On wide screens it fills the side column down to where the main
       column ends and scrolls inside, so neither column leaves a blank space; stacked (phones) it scrolls at 360px. */
    .ts-grid { align-items: stretch; }
    .ts-main, .ts-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; min-height: 0; }
    /* The last card of the main column (Messages) grows to the bottom too: its message list takes the extra room. */
    .ts-main > [data-trip-messages] { display: flex; flex-direction: column; flex: 1 0 auto; }
    .ts-main > [data-trip-messages] .ts-msgs { flex: 1 1 auto; }
    .ts-side > [data-trip-history] { display: flex; flex-direction: column; flex: 1 1 0; min-height: 200px; }
    .ts-log { position: relative; flex: 1 1 0; min-height: 0; overflow-y: auto; padding: 12px 18px 14px; overscroll-behavior: contain; }
    @media (max-width: 1000px) { .ts-side > [data-trip-history], .ts-main > [data-trip-messages] { flex: none; } .ts-log { flex: none; max-height: 360px; } }
    .ts-ev { position: relative; padding: 0 0 12px 20px; font-size: 12.5px; line-height: 1.35; }
    .ts-ev:last-child { padding-bottom: 0; }
    .ts-ev::before { content: ''; position: absolute; left: 4px; top: 5px; width: 8px; height: 8px; border-radius: 99px; background: #CBD5E1; }
    .ts-ev::after { content: ''; position: absolute; left: 7.5px; top: 16px; bottom: 0; width: 1px; background: #E2E8F0; }
    .ts-ev:last-child::after { display: none; }
    .ts-ev:first-child::before { background: var(--accent-orange); box-shadow: 0 0 0 3px rgba(250,78,28,.15); }
    .ts-ev b { font-weight: 700; color: var(--text); }
    .ts-ev small { display: block; margin-top: 1px; color: var(--text-muted); font-size: 11px; }
    .ts-alert { margin: 0 0 16px; padding: 14px 16px; border-radius: 14px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .ts-msgs { display: flex; flex-direction: column; gap: 8px; padding: 12px 18px; max-height: 340px; overflow: auto; }
    .ts-msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; font-size: 13px; background: #F1F5F9; }
    .ts-msg.is-mine { align-self: flex-end; background: #FFF4E5; }
    .ts-msg small { display: block; margin-top: 3px; font-size: 11px; color: var(--text-muted); }
    .ts-recv label { display: flex; align-items: center; gap: 10px; padding: 8px 18px; border-bottom: 1px solid #F1F5F9; font-size: 13px; cursor: pointer; }
</style>

<div class="page-header" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <a href="{{ route('sc.trips') }}" class="btn btn-sm" style="background:#F1F5F9;">← Trips</a>
    <div><div class="text-muted" style="font-size:11.5px;font-weight:700;letter-spacing:.4px;">DELIVERY CODE</div><h1 style="margin:0;font-family:'SF Mono',Consolas,monospace;">{{ $trip->code }}</h1></div>
    <span class="badge {{ $stage >= 5 ? 'badge-green' : ($stage === 0 ? 'badge-gray' : ($stage >= 2 ? 'badge-blue' : 'badge-yellow')) }}" title="{{ $trip->label() }}" data-trip-stage="{{ $stage }}">{{ $trip->stageLabel() }}</span>
    @if ($trip->crossCompany())
        <span class="text-muted" style="font-size:13px;">{{ $sender ? 'Outgoing to ' . $receiverName : 'Incoming from ' . $senderName }}</span>
    @endif
</div>
<div class="page-body">

    {{-- What this sorting center has to do now --}}
    @if ($receiver && $trip->awaitingHandover())
        <div class="ts-alert" style="background:#FFF4E5;" data-handover>
            <div style="flex:1;min-width:240px;">
                <b>{{ $senderName }} wants to send {{ $onBoard->count() }} parcel(s) to your hub {{ $trip->destination->code }}.</b>
                <div class="text-muted" style="font-size:12.5px;">The parcels are listed below{{ $onBoard->isEmpty() ? ' once they add them' : '' }}.</div>
                <div class="text-muted" style="font-size:12.5px;">The truck can load and depart only after you accept. If you reject, the trip is cancelled and the parcels stay with them.</div>
            </div>
            <form method="POST" action="{{ route('sc.trips.respond', $trip) }}">@csrf<input type="hidden" name="decision" value="accept">
                <button class="btn btn-green">Accept trip</button></form>
            <form method="POST" action="{{ route('sc.trips.respond', $trip) }}" style="display:flex;gap:6px;" data-confirm="The trip is cancelled and the parcels stay at {{ $trip->origin->code }}." data-confirm-title="Reject this trip?" data-confirm-ok="Reject">
                @csrf<input type="hidden" name="decision" value="reject">
                <input name="note" class="form-input" placeholder="Reason (e.g. hub full today)" required minlength="5" maxlength="300" style="min-width:200px;">
                <button class="btn btn-red">Reject</button>
            </form>
        </div>
    @elseif ($sender && $trip->awaitingHandover())
        <div class="ts-alert" style="background:#FFF4E5;" data-handover>
            <b>Waiting for {{ $receiverName }} to accept this trip.</b>
            <span class="text-muted" style="font-size:12.5px;">The rider can accept it, but cannot load or depart until they accept.</span>
        </div>
    @elseif ($trip->handover_status === 'rejected')
        <div class="ts-alert" style="background:#FEF2F2;color:var(--accent-red);" data-handover>
            <b>Rejected by {{ $receiverName }}</b>{{ $trip->handover_note ? ': ' . $trip->handover_note : '' }}
        </div>
    @endif

    <div class="ts-grid">
        <div class="ts-main">
            {{-- Route and progress --}}
            <div class="card" data-trip-route>
                <div class="ts-route">
                    <div class="ts-hub"><b>{{ $trip->origin->code ?? '?' }}</b><span>{{ $trip->origin->name ?? '' }}</span>@if($trip->crossCompany())<span>{{ $senderName }}</span>@endif</div>
                    <span style="color:var(--accent-orange);">@include('sc.partials.icon', ['name' => 'truck', 'size' => 26, 'sw' => 2])</span>
                    <div class="ts-hub" style="text-align:right;"><b>{{ $trip->destination->code ?? '?' }}</b><span>{{ $trip->destination->name ?? '' }}</span>@if($trip->crossCompany())<span>{{ $receiverName }}</span>@endif</div>
                </div>
                <div class="ts-steps">
                    @foreach ($steps as $n => [$label, $at, $current])
                        <div class="ts-step {{ $at ? 'is-done' : '' }} {{ $current ? 'is-now' : '' }}" data-stage="{{ $n + 1 }}"><i></i><b>{{ $n + 1 }}. {{ $label }}</b><div>{{ $at?->format('M j, g:i A') ?? '—' }}</div></div>
                    @endforeach
                </div>
                @if ($sender && $stage >= 1 && $stage <= 3)
                    <div class="ts-next" data-trip-next>
                        <b>Next:</b>
                        @if ($stage === 1 && $onBoard->isEmpty())
                            assign the parcels below. <b>Auto-assign</b> puts in every parcel going to {{ $trip->destination->code }}, or tick them yourself.
                        @elseif ($stage === 1)
                            assign a truck (right side). The courier then scans each parcel onto the truck.
                        @elseif ($stage === 2)
                            {{ $trip->courier?->fullName() ?? 'The courier' }} is loading: {{ $onBoard->where('status', 'loaded')->count() }} of {{ $onBoard->count() }} parcel(s) scanned onto the truck.
                        @else
                            all parcels are on the truck. {{ $trip->courier?->fullName() ?? 'The courier' }} departs for {{ $trip->destination->code }}.
                        @endif
                    </div>
                @endif
                @if ($trip->delayed && ! $trip->arrived_at)
                    <div style="margin:0 18px 16px;padding:10px 12px;border-radius:10px;background:#FEF2F2;color:var(--accent-red);font-size:13px;">
                        Delayed{{ $trip->delay_reason ? ': ' . $trip->delay_reason : '' }}{{ $trip->eta_at ? ' · new ETA ' . $trip->eta_at->format('M j, g:i A') : '' }}
                    </div>
                @endif
            </div>

            {{-- Receiving (the receiving sorting center, after arrival) --}}
            @if ($receiver && $trip->status === 'arrived')
                <div class="card" data-trip-receive>
                    <div class="card-header"><h2>Receive the parcels</h2><span class="card-meta">Arrived {{ $trip->arrived_at?->format('M j, g:i A') }}</span></div>
                        <form method="POST" action="{{ route('sc.trips.receive', $trip) }}" class="ts-recv"
                              data-confirm="Ticked parcels become yours and wait in Parcel Sorting. Unticked ones are marked Missing in Transit." data-confirm-title="Receive this trip?" data-confirm-ok="Receive">
                            @csrf
                            <div class="text-muted" style="font-size:13px;padding:12px 18px 6px;">Untick any parcel that is not on the truck.</div>
                            @foreach ($toReceive as $i)
                                <label><input type="checkbox" name="found[]" value="{{ $i->parcel_id }}" checked> <span style="font-weight:700;">{{ $i->parcel->tracking_number ?? '—' }}</span> <span class="text-muted">{{ $i->parcel->receiver_name ?? '' }} · {{ $i->parcel->destination_municipality ?? '' }}</span></label>
                            @endforeach
                            <div style="padding:12px 18px;"><button class="btn btn-green" style="width:100%;">Receive {{ $toReceive->count() }} parcel(s) — ready for sorting</button></div>
                        </form>
                </div>
            @endif

            {{-- Add parcels (the sending sorting center, until the truck departs; no limit) --}}
            @if ($sender && in_array($trip->status, \App\Models\LinehaulTrip::BEFORE_DEPARTURE, true))
                @php $forHere = $addable->filter(fn ($p) => $p->best_hub?->id === (int) $trip->destination_hub_id); @endphp
                <div class="card" data-trip-add>
                    <div class="card-header"><h2>Assign parcels</h2><span class="card-meta">{{ $addable->count() }} waiting at {{ $trip->origin->code }} · {{ $forHere->count() }} nearest to {{ $trip->destination->code }}</span></div>
                    <div style="padding:14px 18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <form method="POST" action="{{ route('sc.trips.auto', $trip) }}">@csrf
                            <button class="btn btn-orange" @disabled($forHere->isEmpty())>Auto-assign {{ $forHere->count() }} parcel(s)</button>
                        </form>
                        <span class="text-muted" style="font-size:12.5px;">Puts in every parcel waiting at {{ $trip->origin->code }} whose buyer is in the area of {{ $receiverName }} ({{ $trip->destination->code }}).</span>
                    </div>
                    @if ($addable->isNotEmpty())
                        <form method="POST" action="{{ route('sc.trips.parcels', $trip) }}" class="ts-recv">
                            @csrf
                            <div class="text-muted" style="font-size:12.5px;padding:0 18px 6px;">Or tick parcels to add:</div>
                            @foreach ($addable as $p)
                                <label data-add-pick="{{ $p->tracking_number }}">
                                    <input type="checkbox" name="parcel_ids[]" value="{{ $p->id }}">
                                    <span style="font-weight:700;">{{ $p->tracking_number }}</span>
                                    <span class="text-muted">{{ $p->receiver_name }} · {{ $p->destination_municipality }}</span>
                                    <span style="margin-left:auto;font-size:12px;" class="{{ $p->best_hub?->id === (int) $trip->destination_hub_id ? '' : 'text-muted' }}">
                                        {{ $p->best_hub ? 'Nearest: ' . \App\Support\SortingCenters::nameOf($p->best_hub->company) : 'You deliver it' }}
                                    </span>
                                </label>
                            @endforeach
                            <div style="padding:12px 18px;"><button class="btn btn-blue btn-sm">Add selected</button></div>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Parcels on this trip --}}
            <div class="card" data-trip-parcels>
                <div class="card-header"><h2>Parcels on this trip</h2>
                    <span class="card-meta">{{ $onBoard->count() }} parcels · {{ $onBoard->where('status', 'loaded')->count() }} loaded · {{ $onBoard->where('status', 'received')->count() }} received · {{ number_format($trip->weightKg(), 2) }} kg</span>
                </div>
                <table class="data-table">
                    <thead><tr><th>Waybill</th><th>Receiver</th><th>Going to</th><th>Weight</th><th>Status</th><th>Loaded</th><th>Received</th>@if($sender && in_array($trip->status, \App\Models\LinehaulTrip::BEFORE_DEPARTURE, true))<th></th>@endif</tr></thead>
                    <tbody>
                    @forelse ($items as $i)
                        <tr data-trip-parcel="{{ $i->parcel->tracking_number ?? $i->parcel_id }}">
                            <td>
                                @if ($i->parcel && (int) $i->parcel->current_sorting_center_id === (int) auth()->id())
                                    <a href="{{ route('sc.tracking.show', $i->parcel) }}" class="id-link">{{ $i->parcel->waybill_number ?? $i->parcel->tracking_number }}</a>
                                @else
                                    <span class="id-link">{{ $i->parcel->waybill_number ?? $i->parcel->tracking_number ?? '—' }}</span>
                                @endif
                            </td>
                            <td>{{ $i->parcel->receiver_name ?? '—' }}</td>
                            <td style="font-size:12.5px;">{{ trim(($i->parcel->destination_municipality ?? '') . ', ' . ($i->parcel->destination_province ?? ''), ', ') ?: '—' }}</td>
                            <td>{{ $i->parcel?->weight_kg ? number_format($i->parcel->weight_kg, 2) . ' kg' : '—' }}</td>
                            <td><span class="badge {{ $parcelBadge($i->status) }}">{{ $labels[$i->status] ?? ucfirst(str_replace('_', ' ', $i->status)) }}</span></td>
                            <td style="font-size:12.5px;">{{ $i->loaded_at?->format('M j, g:i A') ?? '—' }}</td>
                            <td style="font-size:12.5px;">{{ $i->received_at?->format('M j, g:i A') ?? '—' }}</td>
                            @if($sender && in_array($trip->status, \App\Models\LinehaulTrip::BEFORE_DEPARTURE, true))
                                <td>@if(in_array($i->status, ['pending', 'loaded']) && $i->parcel)
                                    <form method="POST" action="{{ route('sc.trips.parcels.remove', [$trip, $i->parcel]) }}">@csrf<button class="btn btn-ghost btn-sm" style="color:var(--accent-red);">Remove</button></form>
                                @endif</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">No parcels on this delivery yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Messages between the two sorting centers --}}
            @if ($canMessage)
                <div class="card" id="messages" data-trip-messages>
                    <div class="card-header"><h2>Messages</h2><span class="card-meta">{{ $sender ? $receiverName : $senderName }} sees these too</span></div>
                    <div class="ts-msgs">
                        @forelse ($messages as $m)
                            <div class="ts-msg {{ (int) $m->user_id === (int) auth()->id() ? 'is-mine' : '' }}">
                                {{ $m->body }}
                                <small>{{ \App\Support\SortingCenters::nameOf($m->user) ?? $m->user?->name }} · {{ $m->created_at?->format('M j, g:i A') }}</small>
                            </div>
                        @empty
                            <p class="text-muted" style="margin:0;font-size:13px;">No messages yet. Use this for delays, problems or handover details.</p>
                        @endforelse
                    </div>
                    @if ($trip->status !== 'cancelled')
                        <form method="POST" action="{{ route('sc.trips.message', $trip) }}" style="display:flex;gap:8px;padding:0 18px 16px;">
                            @csrf
                            <input name="body" class="form-input" placeholder="Message {{ $sender ? $receiverName : $senderName }}…" maxlength="1000" required style="flex:1;">
                            <button class="btn btn-blue">Send</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div class="ts-side">
            {{-- Courier and truck --}}
            <div class="card" style="padding:16px 18px;" data-trip-courier>
                <div style="font-weight:800;margin-bottom:8px;">Courier <span class="badge badge-blue" style="font-size:10px;vertical-align:middle;">truck</span></div>
                @if ($trip->courier)
                    <div class="ts-kv"><span>Name</span><span>{{ $trip->courier->fullName() }}</span></div>
                    <div class="ts-kv"><span>Vehicle</span><span>{{ $trip->courier->vehicle_type ?: '—' }}</span></div>
                    <div class="ts-kv"><span>Plate</span><span>{{ $trip->courier->plate_number ?: '—' }}</span></div>
                    @if ($trip->courier->contact_no)<div class="ts-kv"><span>Phone</span><span>{{ $trip->courier->contact_no }}</span></div>@endif
                @else
                    <p class="text-muted" style="margin:0 0 8px;font-size:13px;">No rider assigned yet.</p>
                @endif
                @if ($sender && in_array($trip->status, \App\Models\LinehaulTrip::BEFORE_DEPARTURE, true))
                    <form method="POST" action="{{ route('sc.trips.assign', $trip) }}" style="display:flex;gap:6px;margin-top:10px;">
                        @csrf
                        <select name="courier_id" class="form-select" style="flex:1;" required aria-label="Courier">
                            <option value="">{{ $couriers->isEmpty() ? 'No truck courier online' : ($trip->courier ? 'Reassign to…' : 'Choose an online truck courier…') }}</option>
                            @foreach ($couriers as $c)<option value="{{ $c->id }}">{{ $c->fullName() }} · {{ $c->vehicle_type }}</option>@endforeach
                        </select>
                        <button class="btn btn-blue btn-sm" @disabled($couriers->isEmpty() || $onBoard->isEmpty())>{{ $trip->courier ? 'Reassign' : 'Assign' }}</button>
                    </form>
                    @if ($onBoard->isEmpty())<p class="text-muted" style="font-size:12px;margin-top:6px;" data-truck-after-parcels>Assign the parcels first; then the truck.</p>@endif
                    @if ($offDuty->isNotEmpty())
                        <p class="text-muted" style="font-size:12px;margin-top:6px;">
                            Not available now: {{ $offDuty->map(fn ($c) => $c->fullName() . ' (' . $c->vehicle_type . ', ' . ($c->activeTrip() ? 'on ' . $c->activeTrip()->code : 'Off Duty') . ')')->implode(', ') }}.
                            A courier goes On Duty in the ALVY app (pre-trip checklist).
                        </p>
                    @elseif ($couriers->isEmpty())
                        <p class="text-muted" style="font-size:12px;margin-top:6px;">No courier yet. Riders with a van, truck or car are your Linehaul couriers.</p>
                    @endif
                @endif
                @if ($trip->courier_note)<div class="ts-kv"><span>Courier note</span><span>{{ $trip->courier_note }}</span></div>@endif
            </div>

            {{-- Where the truck was last seen --}}
            <div class="card" style="padding:16px 18px;" data-trip-location>
                <div style="font-weight:800;margin-bottom:8px;">Last location</div>
                @if ($last)
                    <p style="margin:0 0 10px;font-size:13px;">Seen {{ $last->recorded_at->format('M j, g:i A') }} ({{ $last->recorded_at->diffForHumans() }})</p>
                    <a href="https://www.google.com/maps?q={{ $last->lat }},{{ $last->lng }}" target="_blank" rel="noopener" class="btn btn-blue btn-sm" style="width:100%;">Open in Google Maps</a>
                @else
                    <p class="text-muted" style="margin:0;font-size:13px;">The rider's phone sends its location once the trip is on the road.</p>
                @endif
            </div>

            {{-- Edit (the sending sorting center, before departure) --}}
            @if ($sender && in_array($trip->status, \App\Models\LinehaulTrip::BEFORE_DEPARTURE, true))
                <form method="POST" action="{{ route('sc.trips.update', $trip) }}" class="card" style="padding:16px 18px;display:grid;gap:8px;" data-trip-edit
                      @unless ($trip->courier_id) data-confirm="Changing where it goes takes its parcels off (they stay at {{ $trip->origin->code }}) and the new sorting center accepts it first." data-confirm-title="Save changes to {{ $trip->code }}?" data-confirm-ok="Save" @endunless>
                    @csrf
                    <div style="font-weight:800;">Edit trip</div>
                    <label class="text-muted" style="font-size:12px;">Goes to
                        <select name="destination_hub_id" class="form-select" @disabled($trip->courier_id) style="margin-top:4px;">
                            @foreach ($destinations as $h)
                                <option value="{{ $h->id }}" @selected((int) $trip->destination_hub_id === $h->id)>{{ \App\Support\SortingCenters::nameOf($h->company) }} — {{ $h->code }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($trip->courier_id)<div class="text-muted" style="font-size:11.5px;">A truck is assigned: to change where it goes, cancel or remove this trip and create a new one.</div>@endif
                    <label class="text-muted" style="font-size:12px;">Planned departure <span style="font-weight:400;">(empty: as soon as loaded)</span>
                        <input type="datetime-local" name="scheduled_departure_at" class="form-input" style="margin-top:4px;"
                               value="{{ $trip->scheduled_departure_at?->copy()->tz(config('app.business_timezone'))->format('Y-m-d\TH:i') }}">
                    </label>
                    @error('destination_hub_id')<p style="color:var(--accent-red);font-size:12px;">{{ $message }}</p>@enderror
                    @error('scheduled_departure_at')<p style="color:var(--accent-red);font-size:12px;">{{ $message }}</p>@enderror
                    <button class="btn btn-blue btn-sm" style="justify-self:start;">Save changes</button>
                </form>
            @endif

            {{-- Cancel (the sending sorting center, before departure) --}}
            @if ($sender && in_array($trip->status, \App\Models\LinehaulTrip::BEFORE_DEPARTURE, true))
                <form method="POST" action="{{ route('sc.trips.cancel', $trip) }}" class="card" style="padding:16px 18px;display:grid;gap:8px;"
                      data-confirm="The parcels stay at {{ $trip->origin->code }}." data-confirm-title="Cancel {{ $trip->code }}?" data-confirm-ok="Cancel trip">
                    @csrf
                    <div style="font-weight:800;">Cancel trip</div>
                    <input name="reason" class="form-input" placeholder="Reason" required minlength="5" maxlength="300">
                    <button class="btn btn-red btn-sm">Cancel trip</button>
                </form>
            @endif
            {{-- Remove (the sending sorting center): a trip that has not departed, or a cancelled one, is deleted for good. --}}
            @if ($sender && in_array($trip->status, [...\App\Models\LinehaulTrip::BEFORE_DEPARTURE, 'cancelled'], true))
                <form method="POST" action="{{ route('sc.trips.remove', $trip) }}" class="card" style="padding:16px 18px;display:grid;gap:8px;" data-trip-remove
                      data-confirm="{{ $trip->code }} and its history are deleted for good. Its parcels stay at {{ $trip->origin->code }}." data-confirm-title="Remove {{ $trip->code }}?" data-confirm-ok="Remove">
                    @csrf
                    <div style="font-weight:800;">Remove trip</div>
                    <div class="text-muted" style="font-size:12.5px;">Deletes the trip and its history. Its parcels stay at {{ $trip->origin->code }}.</div>
                    <button class="btn btn-ghost btn-sm" style="color:var(--accent-red);justify-self:start;">Remove trip</button>
                </form>
            @endif

            {{-- History (newest first) --}}
            <div class="card" data-trip-history>
                <div class="card-header"><h2>History</h2><span class="card-meta">{{ $events->count() }} {{ \Illuminate\Support\Str::plural('update', $events->count()) }}</span></div>
                <div class="ts-log">
                    @forelse ($events->reverse() as $e)
                        <div class="ts-ev">
                            <b>{{ $eventLabels[$e->type] ?? ucfirst(str_replace('_', ' ', $e->type)) }}</b>
                            @if ($e->reason) — {{ $e->reason }}@endif
                            
                            <small>{{ $e->created_at?->format('M j, g:i A') }}{{ $e->courier ? ' · ' . $e->courier->fullName() : '' }}{{ $e->user && ! $e->courier ? ' · ' . (\App\Support\SortingCenters::nameOf($e->user) ?? $e->user->name) : '' }}</small>
                        </div>
                    @empty
                        <p class="text-muted" style="margin:0;font-size:13px;">No history yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
