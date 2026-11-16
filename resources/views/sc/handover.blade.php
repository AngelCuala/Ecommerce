@extends('sc.layout')
@section('title', 'Scan driver QR')
@section('icon', 'truck')

@section('content')
<style>
    .ho-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; }
    @media (max-width: 900px) { .ho-grid { grid-template-columns: 1fr; } }
    .ho-body { padding: 16px 18px 18px; }
    .ho-steps { margin: 0 0 14px; padding-left: 18px; font-size: 13px; color: var(--text-sub); line-height: 1.7; }
    .ho-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .ho-code { flex: 1 1 200px; font: 700 18px/1.2 ui-monospace, Menlo, monospace; letter-spacing: .12em; text-transform: uppercase; }
    .ho-big .wbs-trigger { min-height: 48px; padding: 12px 20px; font-size: 15px; }
    .ho-trip { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 18px; border-top: 1px solid var(--border); font-size: 13px; }
    .ho-trip b { font-family: ui-monospace, Menlo, monospace; }
</style>

<div class="ho-grid">
    <div class="card" data-handover-scan>
        <div class="card-header"><h2>Scan the driver's Handover QR</h2><span class="card-meta">Loading and arrival</span></div>
        <div class="ho-body">
            <ol class="ho-steps">
                <li>Choose the hub you are at.</li>
                <li>Scan the QR on the driver's phone (or type the 6-digit code under it).</li>
                <li>Check the driver, truck and trip, then scan the parcels on or off and confirm.</li>
            </ol>

            <form method="GET" action="{{ route('sc.handover') }}" class="ho-row" style="margin-bottom:14px;">
                <label for="ho-hub" class="text-muted" style="font-size:12.5px;font-weight:700;">You are at</label>
                <select id="ho-hub" name="hub" class="form-select" style="width:auto;flex:1 1 220px;" onchange="this.form.submit()" data-handover-hub>
                    @foreach ($hubs as $hub)<option value="{{ $hub->id }}" @selected($hub->id === $hubId)>{{ $hub->label() }}</option>@endforeach
                </select>
            </form>

            @if ($hubs->isEmpty())
                <p class="text-muted">Add a hub first (Hubs page): the driver's QR is checked against the hub you are at.</p>
            @else
                <div class="ho-row ho-big" style="margin-bottom:12px;">
                    @include('partials.waybill-scanner', ['lookup' => route('sc.handover.verify'), 'label' => 'Scan driver QR'])
                </div>
                <form method="POST" action="{{ route('sc.handover.verify') }}" class="ho-row">@csrf
                    <input type="hidden" name="hub_id" value="{{ $hubId }}">
                    <input name="code" class="form-input ho-code" placeholder="6-digit code" required maxlength="60" autocomplete="off" inputmode="numeric"
                           value="{{ old('code') }}" aria-label="The 6-digit code on the driver's phone" data-handover-code>
                    <button class="btn btn-orange" style="min-height:44px;">Verify</button>
                </form>
                <p class="text-muted" style="font-size:12px;margin:10px 0 0;">The QR and code change every {{ \App\Services\TripHandover::period() }} seconds and work once, so a screenshot or an old code is refused.</p>
            @endif
        </div>
    </div>

    <div class="card" data-handover-waiting>
        <div class="card-header"><h2>Waiting for a handover</h2><span class="card-meta">{{ $waiting->count() }} at your hubs</span></div>
        @forelse ($waiting as $t)
            @php $stage = \App\Services\TripHandover::stageOf($t); $c = $t->counts(); @endphp
            <div class="ho-trip" data-waiting-trip="{{ $t->code }}">
                <div>
                    <b>{{ $t->code }}</b> · {{ $t->route() }}
                    <div class="text-muted">{{ $t->courier?->fullName() }} · {{ $t->courier?->plate_number }}</div>
                </div>
                <div style="text-align:right;">
                    <span class="badge {{ $stage === 'load' ? 'badge-orange' : 'badge-blue' }}">{{ $stage === 'load' ? 'Loading at ' . $t->origin?->code : 'Arrived at ' . $t->destination?->code }}</span>
                    <div class="text-muted">{{ $c['expected'] }} parcels · {{ $stage === 'load' ? $c['loaded'] . ' loaded' : $c['unloaded'] . ' unloaded' }} · {{ $c['weight_kg'] }} kg</div>
                </div>
            </div>
        @empty
            <div class="ho-body text-muted">No truck is loading or has arrived at your hubs right now.</div>
        @endforelse
    </div>
</div>
@endsection
