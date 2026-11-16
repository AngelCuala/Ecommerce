@extends('sc.layout')
@section('title', 'Scan driver QR')
@section('icon', 'truck')

@section('content')
<style>
    .ho-grid { display: grid; gap: 18px; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); align-items: start; }
    @media (max-width: 960px) { .ho-grid { grid-template-columns: 1fr; } }
    .ho-body { padding: 18px 20px 20px; }

    /* Steps */
    .ho-steps { display: flex; gap: 10px; margin: 0 0 18px; padding: 0; list-style: none; }
    .ho-steps li { flex: 1; display: flex; gap: 9px; align-items: flex-start; font-size: 12.5px; line-height: 1.4; color: var(--text-sub); }
    .ho-steps b { display: flex; width: 22px; height: 22px; flex: none; align-items: center; justify-content: center; border-radius: 999px; background: #fff1ee; color: var(--accent-orange); font-size: 11.5px; }
    @media (max-width: 560px) { .ho-steps { flex-direction: column; } }

    .ho-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: var(--text-sub); }
    .ho-hub { width: 100%; min-height: 44px; font-size: 14px; }

    /* The camera: one big target */
    .ho-camera { margin: 16px 0 0; padding: 18px; border: 1.5px dashed var(--border); border-radius: 14px; text-align: center; }
    .ho-camera .wbs-trigger { width: 100%; justify-content: center; min-height: 52px; font-size: 16px; border-radius: 12px; }
    .ho-camera p { margin: 10px 0 0; font-size: 12.5px; color: var(--text-muted); }

    .ho-or { display: flex; align-items: center; gap: 12px; margin: 16px 0 12px; font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
    .ho-or::before, .ho-or::after { content: ''; flex: 1; height: 1px; background: var(--border); }

    /* The 6-digit code: big digits, normal placeholder */
    .ho-code-row { display: flex; gap: 10px; }
    .ho-code { flex: 1; min-width: 0; min-height: 48px; font: 800 22px/1 ui-monospace, Menlo, monospace; letter-spacing: .35em; text-align: center; }
    .ho-code::placeholder { font: 500 14px/1 Inter, system-ui, sans-serif; letter-spacing: 0; }
    .ho-verify { min-height: 48px; min-width: 110px; justify-content: center; font-size: 14px; border-radius: 10px; }
    .ho-note { margin: 14px 0 0; font-size: 12px; color: var(--text-muted); display: flex; gap: 6px; align-items: flex-start; }

    /* Waiting trucks */
    .ho-trip { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 14px 20px; border-top: 1px solid var(--border); color: inherit; text-decoration: none; transition: background .15s; }
    .ho-trip:hover { background: var(--card-hover, #faf8f6); }
    .ho-trip-code { font: 800 13.5px/1.3 ui-monospace, Menlo, monospace; }
    .ho-trip-route { font-size: 13.5px; font-weight: 600; }
    .ho-trip-who { margin-top: 3px; font-size: 12.5px; color: var(--text-muted); }
    .ho-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .ho-chip { padding: 3px 9px; border-radius: 999px; background: var(--card-hover, #f5f2ef); font-size: 11.5px; font-weight: 600; color: var(--text-sub); }
    .ho-empty { padding: 28px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }
</style>

<div class="page-body">
<div class="ho-grid">
    <div class="card" data-handover-scan>
        <div class="card-header"><h2>Scan the driver's Handover QR</h2><span class="card-meta">Loading and arrival</span></div>
        <div class="ho-body">
            <ol class="ho-steps">
                <li><b>1</b><span>Choose the hub you are at</span></li>
                <li><b>2</b><span>Scan the QR on the driver's phone</span></li>
                <li><b>3</b><span>Check the driver, then scan the parcels</span></li>
            </ol>

            @if ($hubs->isEmpty())
                <p class="text-muted">Add a hub first: the driver's QR is checked against the hub you are at.</p>
            @else
                <form method="GET" action="{{ route('sc.handover') }}">
                    <label for="ho-hub" class="ho-label">You are at</label>
                    <select id="ho-hub" name="hub" class="form-select ho-hub" onchange="this.form.submit()" data-handover-hub>
                        @foreach ($hubs as $hub)<option value="{{ $hub->id }}" @selected($hub->id === $hubId)>{{ $hub->label() }}</option>@endforeach
                    </select>
                </form>

                <div class="ho-camera">
                    @include('partials.waybill-scanner', ['lookup' => route('sc.handover.verify'), 'label' => 'Scan driver QR'])
                    <p>Opens the camera. Point it at the QR on the driver's phone.</p>
                </div>

                <div class="ho-or">or type the code</div>
                <form method="POST" action="{{ route('sc.handover.verify') }}" class="ho-code-row">@csrf
                    <input type="hidden" name="hub_id" value="{{ $hubId }}">
                    <input name="code" class="form-input ho-code" placeholder="6-digit code" required maxlength="60" autocomplete="off" inputmode="numeric"
                           value="{{ old('code') }}" aria-label="The 6-digit code on the driver's phone" data-handover-code>
                    <button class="btn ho-verify" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Verify</button>
                </form>
                <p class="ho-note">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex:none;margin-top:1px;" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                    The QR and code change every {{ \App\Services\TripHandover::period() }} seconds and work once, so a screenshot or an old code is refused.
                </p>
            @endif
        </div>
    </div>

    <div class="card" data-handover-waiting>
        <div class="card-header"><h2>Waiting for a handover</h2><span class="card-meta">{{ $waiting->count() }} at your {{ \Illuminate\Support\Str::plural('hub', $hubs->count()) }}</span></div>
        @forelse ($waiting as $t)
            @php $stage = \App\Services\TripHandover::stageOf($t); $c = $t->counts(); @endphp
            <a href="{{ route('sc.trips.show', $t) }}" class="ho-trip" data-waiting-trip="{{ $t->code }}">
                <div>
                    <div><span class="ho-trip-code">{{ $t->code }}</span> · <span class="ho-trip-route">{{ $t->route() }}</span></div>
                    <div class="ho-trip-who">{{ $t->courier?->fullName() }} · {{ $t->courier?->plate_number }}</div>
                    <div class="ho-chips">
                        <span class="ho-chip">{{ $c['expected'] }} {{ \Illuminate\Support\Str::plural('parcel', $c['expected']) }}</span>
                        <span class="ho-chip">{{ $stage === 'load' ? $c['loaded'] . ' loaded' : $c['unloaded'] . ' unloaded' }}</span>
                        <span class="ho-chip">{{ rtrim(rtrim(number_format($c['weight_kg'], 2), '0'), '.') ?: 0 }} kg</span>
                    </div>
                </div>
                <span class="badge {{ $stage === 'load' ? 'badge-orange' : 'badge-blue' }}">{{ $stage === 'load' ? 'Loading at ' . $t->origin?->code : 'Arrived at ' . $t->destination?->code }}</span>
            </a>
        @empty
            <div class="ho-empty">No truck is loading or has arrived at your hubs right now.</div>
        @endforelse
    </div>
</div>
</div>
@endsection
