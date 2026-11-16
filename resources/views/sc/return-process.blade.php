@extends('sc.layout')
@section('title', 'Return ' . $rs->order?->number())
@section('icon', 'report')

{{--
    One returned item at this hub, processed like a parcel: 1 Sort → 2 Check → 3 Scan for the record → 4 Send.
    One step at a time (App\Services\ReturnShipments refuses them out of order); its record is on the right.
--}}
@php
    $tz = config('app.business_timezone');
    $step = $rs->stepAtHub();
    $order = ['sort' => 1, 'check' => 2, 'scan' => 3, 'send' => 4];
    $at = $step ? $order[$step] : ($rs->status === 'at_hub' && $rs->linehaul_trip_id ? 4 : 5);
    $steps = [1 => ['Sort', 'Where it goes next'], 2 => ['Check', 'Its condition'], 3 => ['Scan', 'Waybill for the record'], 4 => ['Send', 'To the seller or the next hub']];
    $seller = $rs->parcel?->seller?->storeName() ?? $rs->parcel?->seller?->name ?? 'Seller';
    $toHub = ($rs->next_stop ?? $next['stop'] ?? null) === 'hub';
    $nextHub = $rs->nextHub ?? ($next['hub'] ?? null);
@endphp

@section('content')
<style>
    .rp-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); align-items: start; }
    @media (max-width: 1000px) { .rp-grid { grid-template-columns: 1fr; } }
    .rp-body { padding: 16px 18px 18px; }
    .rp-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; padding: 16px 18px 6px; }
    .rp-step { display: flex; gap: 9px; align-items: flex-start; padding-top: 10px; border-top: 4px solid var(--border); }
    .rp-step b { display: flex; width: 26px; height: 26px; flex: none; align-items: center; justify-content: center; border-radius: 999px; background: #f2f4f7; color: var(--text-muted); font-size: 12px; }
    .rp-step span { font-size: 12.5px; font-weight: 700; color: var(--text-muted); }
    .rp-step small { display: block; font-size: 11px; font-weight: 500; color: var(--text-muted); }
    .rp-step.done { border-color: #16a34a; } .rp-step.done b { background: #16a34a; color: #fff; } .rp-step.done span { color: var(--text); }
    .rp-step.now { border-color: #fa4e1c; } .rp-step.now b { background: #fa4e1c; color: #fff; } .rp-step.now span { color: var(--text); }
    @media (max-width: 640px) { .rp-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .rp-route { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 12px; background: var(--card-hover, #f8f8f8); font-size: 13px; }
    .rp-route b { font-weight: 800; }
    .rp-go { background: #fa4e1c !important; border-color: #fa4e1c !important; color: #fff !important; min-height: 42px; padding-left: 18px; padding-right: 18px; font-size: 13.5px; }
    .rp-cond { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    @media (max-width: 640px) { .rp-cond { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .rp-cond label { display: flex; align-items: center; gap: 7px; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; }
    .rp-cond label:has(input:checked) { border-color: #fa4e1c; background: #fff7f3; }
    .rp-log { list-style: none; margin: 0; padding: 14px 18px; }
    .rp-log li { position: relative; padding: 0 0 12px 18px; border-left: 2px solid var(--border); margin-left: 5px; font-size: 12.5px; }
    .rp-log li::before { content: ''; position: absolute; left: -6px; top: 3px; width: 10px; height: 10px; border-radius: 999px; background: #cbd5e1; }
    .rp-log li:last-child::before { background: #fa4e1c; }
    .rp-log b { display: block; font-size: 13px; color: var(--text); }
</style>

<div class="page-body">
    <p style="margin:0 0 12px;"><a href="{{ route('sc.returns', ['tab' => 'process']) }}" class="text-muted">&larr; Return Items</a></p>

    <div class="rp-grid">
        <div class="card" data-return-process="{{ $rs->id }}" data-step="{{ $step ?? 'none' }}">
            <div class="card-header">
                <h2>Return {{ $rs->order?->number() }} · waybill {{ $rs->code() }}</h2>
                <span class="badge {{ $step ? 'badge-orange' : 'badge-green' }}">{{ $rs->statusLabel() }}</span>
            </div>
            <div class="rp-steps">
                @foreach ($steps as $n => [$name, $hint])
                    <div class="rp-step {{ $n < $at ? 'done' : ($n === $at ? 'now' : '') }}"><b>{{ $n < $at ? '✓' : $n }}</b><span>{{ $name }}<small>{{ $hint }}</small></span></div>
                @endforeach
            </div>

            <div class="rp-body">
                <div class="rp-route" data-return-route>
                    <span>{{ $rs->order?->full_name }} (buyer)</span> →
                    <b>{{ $rs->currentHub?->code ?? 'this hub' }}</b> →
                    @if ($toHub && $nextHub)<b>{{ $nextHub->code }}</b> <span class="text-muted">({{ \App\Support\SortingCenters::nameOf($nextHub->company) }})</span> →@endif
                    <span>{{ $seller }} (seller{{ ($next['town'] ?? null) ? ', ' . $next['town'] : '' }})</span>
                </div>

                <div style="margin-top:16px;">
                @if ($step === 'sort')
                    <h3 style="font-size:15px;font-weight:800;margin:0 0 6px;">1 · Sort</h3>
                    <p class="text-muted" style="font-size:13px;margin:0 0 12px;">
                        @if ($rs->status === 'picked_up')The rider is bringing it in. Once it is here, sort it: this also records it as received. @endif
                        The seller is in {{ $next['town'] ?? 'their town' }}:
                        {!! ($next['stop'] ?? 'seller') === 'hub' ? 'it goes to <b>' . e($next['hub']->code) . '</b> (' . e(\App\Support\SortingCenters::nameOf($next['company'])) . ') on a hub-to-hub trip.' : 'it goes to the seller <b>from this hub</b>.' !!}
                    </p>
                    <form method="POST" action="{{ route('sc.returns.sort', $rs) }}">@csrf
                        <button class="btn rp-go" data-step-sort>Sort {{ ($next['stop'] ?? 'seller') === 'hub' ? 'for ' . $next['hub']->code : 'for the seller' }}</button>
                    </form>

                @elseif ($step === 'check')
                    <h3 style="font-size:15px;font-weight:800;margin:0 0 6px;">2 · Check the item</h3>
                    <form method="POST" action="{{ route('sc.returns.check', $rs) }}" enctype="multipart/form-data">@csrf
                        <div class="rp-cond">
                            @foreach (\App\Models\ReturnShipment::CONDITIONS as $k => $label)
                                <label><input type="radio" name="condition" value="{{ $k }}" @checked(old('condition', 'good') === $k) required> {{ $label }}</label>
                            @endforeach
                        </div>
                        <label class="text-muted" style="display:block;font-size:12.5px;font-weight:700;margin:12px 0 4px;">Photo <span style="font-weight:500;">(required unless Good)</span></label>
                        <input type="file" name="photo" accept="image/jpeg,image/png" capture="environment" class="form-input">
                        <label class="text-muted" style="display:block;font-size:12.5px;font-weight:700;margin:12px 0 4px;">Note</label>
                        <input name="note" maxlength="300" class="form-input" value="{{ old('note') }}" placeholder="e.g. Box dented on one corner, item inside OK.">
                        <button class="btn rp-go" style="margin-top:12px;" data-step-check>Record the check</button>
                    </form>

                @elseif ($step === 'scan')
                    <h3 style="font-size:15px;font-weight:800;margin:0 0 6px;">3 · Scan the waybill for the record</h3>
                    <p class="text-muted" style="font-size:13px;margin:0 0 12px;">Scan the QR or barcode on its label ({{ $rs->code() }}). A different label is refused.</p>
                    @include('partials.waybill-scanner', ['lookup' => route('sc.returns.show', $rs), 'fill' => '#rp-code', 'label' => 'Scan waybill'])
                    <form method="POST" action="{{ route('sc.returns.scan', $rs) }}" style="display:flex;gap:8px;margin-top:10px;">@csrf
                        <input id="rp-code" name="tracking_number" class="form-input" placeholder="or type the waybill / tracking number" required maxlength="50" autocomplete="off" style="flex:1;text-transform:uppercase;">
                        <button class="btn rp-go" data-step-scan>Record scan</button>
                    </form>

                @elseif ($step === 'send' && $rs->next_stop === 'hub')
                    <h3 style="font-size:15px;font-weight:800;margin:0 0 6px;">4 · Send it to {{ $rs->nextHub?->code }}</h3>
                    <p class="text-muted" style="font-size:13px;margin:0 0 12px;">It goes on a hub-to-hub trip to {{ $rs->nextHub?->code }} ({{ \App\Support\SortingCenters::nameOf($rs->nextHub?->company) }}).
                        An open trip on that route is used, or a new one is made. Then assign the truck; at loading, scan the driver's Handover QR as usual.</p>
                    <form method="POST" action="{{ route('sc.returns.send', $rs) }}">@csrf
                        <button class="btn rp-go" data-step-send>Put it on a trip to {{ $rs->nextHub?->code }}</button>
                    </form>

                @elseif ($step === 'send')
                    <h3 style="font-size:15px;font-weight:800;margin:0 0 6px;">4 · Send it to the seller</h3>
                    <p class="text-muted" style="font-size:13px;margin:0 0 12px;">{{ $seller }} · {{ $rs->sellerAddress() }}</p>
                    <form method="POST" action="{{ route('sc.returns.to-seller', $rs) }}" style="display:flex;gap:8px;flex-wrap:wrap;">@csrf
                        <select name="rider_id" class="form-select" style="flex:1 1 220px;" required aria-label="Rider to the seller">
                            @include('sc.partials.rider-options', ['placeholder' => 'Rider to the seller…', 'except' => null, 'bulky' => (bool) $rs->parcel?->isBulky()])
                        </select>
                        <button class="btn rp-go" data-step-to-seller>Assign rider</button>
                    </form>

                @else
                    <p class="text-muted" style="margin:0;" data-step-none>
                        @if ($rs->status === 'at_hub' && $rs->trip)On <b>{{ $rs->trip->code }}</b> to {{ $rs->nextHub?->code }}, waiting to leave ({{ $rs->trip->label() }}): <a href="{{ route('sc.trips.show', $rs->trip) }}" data-open-trip>open the trip</a> to assign the truck and load it with the driver's Handover QR.
                        @elseif ($rs->status === 'in_transit')On {{ $rs->trip?->code }} to {{ $rs->nextHub?->code }}: <a href="{{ route('sc.trips.show', $rs->trip) }}">open the trip</a>.
                        @elseif ($rs->status === 'to_seller')With {{ $rs->sellerRider?->full_name }}, on the way to the seller.
                        @elseif ($rs->status === 'returned')Handed back to the seller {{ $rs->returned_at?->copy()->tz($tz)->format('M j, g:i A') }}.
                        @else{{ $rs->statusLabel() }}.@endif
                    </p>
                @endif
                </div>
            </div>
        </div>

        {{-- The record: every step, who and when. --}}
        <div class="card" data-return-record>
            <div class="card-header"><h2>Record</h2><span class="card-meta">{{ $rs->returnRequest?->reason }}</span></div>
            <ol class="rp-log">
                @if ($rs->picked_up_at)
                    <li><b>Collected from the buyer</b>{{ $rs->picked_up_at->copy()->tz($tz)->format('M j, g:i A') }} · {{ $rs->pickupRider?->full_name }}
                        @if ($rs->pickup_proof_path) · <a href="{{ \App\Support\ProtectedFile::url($rs->pickup_proof_path) }}" target="_blank" rel="noopener">photo</a>@endif</li>
                @endif
                @foreach ($rs->scans as $e)
                    <li data-scan="{{ $e->type }}"><b>{{ $e->label() }}{{ $e->condition ? ': ' . strtolower(\App\Models\ReturnShipment::CONDITIONS[$e->condition] ?? $e->condition) : '' }}</b>
                        {{ $e->created_at->copy()->tz($tz)->format('M j, g:i A') }} · {{ $e->hub?->code }}{{ $e->actor ? ' · ' . (\App\Support\SortingCenters::nameOf($e->actor) ?? $e->actor->name) : '' }}
                        @if ($e->note)<div class="text-muted">{{ $e->note }}</div>@endif
                        @if ($e->photo_path)<a href="{{ \App\Support\ProtectedFile::url($e->photo_path) }}" target="_blank" rel="noopener">photo</a>@endif
                    </li>
                @endforeach
                @if (! $rs->picked_up_at && $rs->scans->isEmpty())<li><b>Nothing yet</b>The rider hasn't collected it.</li>@endif
            </ol>
        </div>
    </div>
</div>
@endsection
