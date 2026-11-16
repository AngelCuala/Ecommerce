@extends('sc.layout')
@section('title', 'Linehaul Trips')
@section('icon', 'truck')

@php
    $badge = fn ($t) => match (true) {
        $t->status === 'cancelled'                      => 'badge-gray',
        $t->status === 'completed_discrepancy' || $t->seal_discrepancy => 'badge-red',
        in_array($t->status, ['loading', 'in_transit']) => ($t->delayed ? 'badge-red' : 'badge-blue'),
        $t->status === 'completed'                      => 'badge-green',
        default                                         => 'badge-yellow',
    };
    $selected = old('parcel_ids', $preselect);
@endphp

@section('content')
<style>
    .lt-stats { display: grid; gap: 12px; grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 18px; }
    @media (max-width: 1000px) { .lt-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .lt-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
    .lt-stat-ic { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 11px; }
    .lt-stat-n { font-size: 24px; font-weight: 800; line-height: 1; color: var(--text); }
    .lt-stat-l { margin-top: 3px; font-size: 12px; color: var(--text-muted); }
    .lt-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
    .lt-tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
              background: #F1F5F9; color: var(--text); text-decoration: none; }
    .lt-tab.is-on { background: var(--accent-orange); color: #fff; }
    .lt-tab.is-alert:not(.is-on) { background: #FFF4E5; color: var(--accent-orange); }
    .lt-tab span { font-size: 11.5px; opacity: .8; }
    .lt-code { font-family: 'SF Mono', Consolas, monospace; font-weight: 700; white-space: nowrap; }
    .lt-sub { margin-top: 2px; font-size: 11.5px; color: var(--text-muted); }
    .lt-dir { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .lt-form { display: grid; gap: 14px; padding: 18px; }
    .lt-route { display: grid; gap: 12px; grid-template-columns: minmax(0, 1.1fr) auto minmax(0, 1.25fr) minmax(190px, .6fr); align-items: end; }
    .lt-route .form-select, .lt-route .form-input { width: 100%; }
    .lt-arrow { display: flex; width: 38px; height: 38px; align-items: center; justify-content: center; border-radius: 999px; background: #FFF4E5; color: var(--accent-orange); }
    @media (max-width: 900px) { .lt-route { grid-template-columns: 1fr; } .lt-arrow { display: none; } }
    .lt-label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .lt-suggest { display: flex; align-items: flex-start; gap: 7px; margin: -4px 0 0; font-size: 12.5px; color: var(--text-muted); }
    .lt-suggest svg { flex: none; margin-top: 2px; color: var(--accent-green); }
    .lt-suggest b { color: var(--accent-blue); }
    .lt-box { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
    .lt-auto { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--border); background: #FAFBFC; cursor: pointer; }
    .lt-auto input { margin-top: 3px; }
    .lt-auto b { display: block; font-size: 13.5px; color: var(--text); }
    .lt-auto small { display: block; font-size: 12px; color: var(--text-muted); }
    .lt-box-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px 6px; font-size: 12.5px; font-weight: 700; color: var(--text-sub); }
    .lt-empty { margin: 0; padding: 4px 14px 14px; font-size: 13px; color: var(--text-muted); }
    .lt-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
    .lt-foot > span { font-size: 12.5px; color: var(--text-muted); }
    .lt-foot .btn { gap: 6px; min-width: 190px; justify-content: center; }
    .lt-parcels { max-height: 240px; overflow: auto; border-top: 1px solid #F1F5F9; }
    .lt-parcels label { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #F1F5F9; font-size: 13px; cursor: pointer; }
</style>

<div class="page-header"><h1>Linehaul Trips</h1></div>
<div class="page-body">
    <p class="text-muted" style="margin:-4px 0 16px;font-size:13px;">
        Send parcels hub to hub with your truck couriers to the sorting center nearer the buyer.
        The trip loads and departs only after that sorting center accepts it. Both of you can track the trip and message each other on it.
    </p>

    <div class="lt-stats" data-trip-stats>
        @foreach ([
            ['To accept', $stats['to_accept'], 'inbox', '#FFF4E5', 'var(--accent-orange)'],
            ['On the road', $stats['on_road'], 'truck', '#EAF2FB', 'var(--accent-blue)'],
            ['Coming to you', $stats['incoming'], 'transfer', '#F1F5F9', 'var(--text-muted)'],
            ['Arrived, to receive', $stats['arrived'], 'parcel', '#E9F8EF', 'var(--accent-green)'],
            ['Delayed', $stats['delayed'], 'clock', '#FEF2F2', 'var(--accent-red)'],
        ] as [$label, $n, $ic, $bg, $fg])
            <div class="card lt-stat">
                <span class="lt-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 19, 'sw' => 2])</span>
                <div><div class="lt-stat-n" style="{{ in_array($label, ['Delayed', 'To accept']) && $n ? 'color:' . ($label === 'Delayed' ? 'var(--accent-red)' : 'var(--accent-orange)') . ';' : '' }}">{{ $n }}</div><div class="lt-stat-l">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    {{-- Create a trip --}}
    <div class="card" style="margin-bottom:18px;" id="create" data-trip-create>
        <div class="card-header"><h2>Create a delivery</h2><span class="card-meta">Hub to hub, to the sorting center nearest the buyers</span></div>
        {{-- Changing "From" reloads the page for that hub's waiting parcels (its select belongs to this form). --}}
        <form method="GET" action="{{ route('sc.trips') }}#create" id="lt-origin-form" hidden>
            @foreach ($preselect as $id)<input type="hidden" name="parcel[]" value="{{ $id }}">@endforeach
        </form>
        <form method="POST" action="{{ route('sc.trips.store') }}" class="lt-form"
              data-confirm="The delivery is created at {{ $origin->code ?? 'your hub' }} Then assign its parcels on the delivery's page." data-confirm-title="Create this delivery?" data-confirm-ok="Create delivery">
            @csrf
            <input type="hidden" name="origin_hub_id" value="{{ $origin?->id }}">

            {{-- Route: from your hub → to the receiving sorting center, and when --}}
            <div class="lt-route">
                <div>
                    <label class="lt-label" for="lt-origin">From (your hub)</label>
                    <select id="lt-origin" name="origin_hub_id" form="lt-origin-form" class="form-select" onchange="this.form.submit()">
                        @foreach ($hubs as $h)<option value="{{ $h->id }}" @selected($origin && $origin->id === $h->id)>{{ $h->code }} — {{ $h->name }}</option>@endforeach
                    </select>
                </div>
                <span class="lt-arrow" aria-hidden="true">@include('sc.partials.icon', ['name' => 'truck', 'size' => 18, 'sw' => 2])</span>
                <div>
                    <label class="lt-label" for="lt-dest">To (sorting center)</label>
                    <select id="lt-dest" name="destination_hub_id" class="form-select" required>
                        <option value="">Choose the sorting center…</option>
                        {{-- Nearest first; the suggested one is picked for you and can be changed. --}}
                        @foreach ($destinations as $h)
                            <option value="{{ $h->id }}" @selected((int) old('destination_hub_id', $suggested?->id) === $h->id)>{{ \App\Support\SortingCenters::nameOf($h->company) }} — {{ $h->code }}{{ $h->km !== null ? ' · ' . number_format($h->km / 1000, 1) . ' km' : '' }}{{ $suggested && $suggested->id === $h->id ? ' (suggested)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="lt-label" for="lt-when">Planned departure <span style="font-weight:500;">(optional)</span></label>
                    <input id="lt-when" type="datetime-local" name="scheduled_departure_at" class="form-input" value="{{ old('scheduled_departure_at') }}">
                </div>
            </div>
            @if ($suggested)
                <p class="lt-suggest" data-suggested="{{ $suggested->code }}">
                    @include('sc.partials.icon', ['name' => 'check', 'size' => 14, 'sw' => 2.4])
                    <span>Picked automatically: <b>{{ $suggested->code }}</b>{{ $suggested->company?->assigned_municipality ? ' (' . $suggested->company->assigned_municipality . ')' : '' }}, {{ $suggestedWhy }}. You can choose another.</span>
                </p>
            @endif

            <div class="lt-foot">
                <span>After you create it, open the delivery to assign its parcels, then a truck.</span>
                <button class="btn btn-orange">@include('sc.partials.icon', ['name' => 'truck', 'size' => 15, 'sw' => 2]) Create delivery</button>
            </div>
        </form>
    </div>

    <div class="lt-tabs">
        @foreach ($tabs as $t)
            <a href="{{ route('sc.trips', ['status' => $t['key']]) }}" class="lt-tab {{ $tab === $t['key'] ? 'is-on' : '' }} {{ $t['key'] === 'incoming' && $t['count'] ? 'is-alert' : '' }}">{{ $t['label'] }} <span>{{ $t['count'] }}</span></a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead><tr><th>Trip</th><th>Route</th><th>Rider</th><th>Parcels</th><th>Status</th><th>Departure</th><th></th></tr></thead>
            <tbody>
            @forelse ($trips as $t)
                @php $outgoing = (int) $t->sorting_center_id === $me; @endphp
                <tr data-trip="{{ $t->code }}">
                    <td>
                        <span class="lt-code">{{ $t->code }}</span>
                        <div style="margin-top:4px;">
                            @if ($outgoing)
                                <span class="lt-dir" style="background:#EAF2FB;color:var(--accent-blue);">Outgoing</span>
                            @else
                                <span class="lt-dir" style="background:#E9F8EF;color:var(--accent-green);">Incoming</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <strong>{{ $t->route() }}</strong>
                        <div class="lt-sub">{{ $t->origin->name ?? '—' }} → {{ $t->destination->name ?? '—' }}</div>
                        @if ($t->crossCompany())
                            <div class="lt-sub">{{ $outgoing ? 'To ' . \App\Support\SortingCenters::nameOf($t->destinationCompany) : 'From ' . \App\Support\SortingCenters::nameOf($t->company) }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($t->courier)
                            {{ $t->courier->fullName() }}
                            <div class="lt-sub">{{ trim($t->courier->vehicle_type . ' · ' . $t->courier->plate_number, ' ·') }}</div>
                        @else
                            <span class="text-muted">No rider yet</span>
                        @endif
                    </td>
                    <td>{{ $t->parcels_count }}</td>
                    <td>
                        <span class="badge {{ $badge($t) }}" title="{{ $t->label() }}">{{ $t->stageLabel() }}</span>
                        @if ($t->handover_status === 'pending')
                            <div class="lt-sub" style="color:var(--accent-orange);font-weight:700;">{{ $outgoing ? 'Waiting for them to accept' : 'Needs your acceptance' }}</div>
                        @elseif ($t->handover_status === 'rejected')
                            <div class="lt-sub" style="color:var(--accent-red);">Rejected by the receiver</div>
                        @endif
                        @if ($t->delayed && in_array($t->status, ['loading', 'in_transit']))<div class="lt-sub" style="color:var(--accent-red);">Delayed{{ $t->delay_reason ? ': ' . $t->delay_reason : '' }}</div>@endif
                    </td>
                    <td style="font-size:12.5px;">
                        @if ($t->departed_at)
                            Left {{ $t->departed_at->format('M j, g:i A') }}
                            @if ($t->eta_at && ! $t->arrived_at)<div class="lt-sub">ETA {{ $t->eta_at->format('M j, g:i A') }}</div>@endif
                            @if ($t->arrived_at)<div class="lt-sub">Arrived {{ $t->arrived_at->format('M j, g:i A') }}</div>@endif
                        @elseif ($t->scheduled_departure_at)
                            Planned {{ $t->scheduled_departure_at->format('M j, g:i A') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><a href="{{ route('sc.trips.show', $t) }}" class="btn btn-sm {{ $t->handover_status === 'pending' && ! $outgoing ? 'btn-orange' : 'btn-blue' }}">{{ $t->handover_status === 'pending' && ! $outgoing ? 'Review' : 'Track' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--text-muted);">No trips here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:14px;">{{ $trips->links() }}</div>
</div>
@endsection
