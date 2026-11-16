@extends('sc.layout')
@section('title', 'Parcel Sorting')
@section('icon', 'sort')

@php
    $withParcels = $areas->filter(fn ($a) => ($a->sorted_count ?? 0) > 0)->sortByDesc('sorted_count')->values();
    $empty       = $areas->filter(fn ($a) => ($a->sorted_count ?? 0) === 0 || $a->sorted_count === null)->values();
    $ready       = (int) $areas->sum('sorted_count');
    $toSort      = $pending->count();
    $outside     = $pending->filter(fn ($p) => ! ($covered[$p->id] ?? true))->count();
@endphp

@section('content')
<style>
    .ps-stats { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
    @media (max-width: 900px) { .ps-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ps-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
    .ps-stat-ic { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 11px; }
    .ps-stat-n { font-size: 24px; font-weight: 800; line-height: 1; color: var(--text); }
    .ps-stat-l { margin-top: 3px; font-size: 12px; color: var(--text-muted); }

    .ps-title { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; margin: 22px 0 10px; }
    .ps-title h2 { font-size: 15px; font-weight: 700; color: var(--text); }
    .ps-title span { font-size: 12px; color: var(--text-muted); }

    .ps-row-suggest { display: inline-flex; align-items: center; gap: 4px; margin-top: 5px; font-size: 11.5px; font-weight: 600; }
    .ps-addr { max-width: 280px; font-size: 12.5px; line-height: 1.35; color: var(--text-sub); }

    .ps-bins { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); }
    .ps-bin { display: flex; flex-direction: column; gap: 10px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); background: #fff; text-decoration: none; transition: border-color .15s, box-shadow .15s, transform .15s; }
    .ps-bin:hover { border-color: var(--accent-blue); box-shadow: 0 10px 22px -16px rgba(0,43,77,.55); transform: translateY(-1px); }
    .ps-bin-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .ps-bin-name { font-size: 13.5px; font-weight: 700; color: var(--text); }
    .ps-bin-n { font-size: 26px; font-weight: 800; line-height: 1; color: var(--accent-orange); }
    .ps-bin-go { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--accent-blue); }
    .ps-bar { height: 4px; border-radius: 999px; background: #EEF2F6; overflow: hidden; }
    .ps-bar i { display: block; height: 100%; border-radius: 999px; background: var(--accent-orange); }

    .ps-empty-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; cursor: pointer; list-style: none; }
    .ps-empty-head::-webkit-details-marker { display: none; }
    .ps-chips { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 16px 16px; }
    .ps-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; border: 1px solid var(--border); background: #F8FAFC; padding: 4px 10px; font-size: 12px; color: var(--text-sub); text-decoration: none; }
    .ps-chip:hover { border-color: var(--accent-blue); color: var(--text); }
    .ps-chip b { color: var(--text-muted); font-weight: 600; }
    .ps-search { width: 220px; max-width: 100%; }
</style>

<div class="page-header"><h1>Parcel Sorting</h1></div>
<div class="page-body">

    @if ($areas->isEmpty())
        <div class="card" style="padding:14px 18px;margin-bottom:16px;border-left:3px solid var(--accent-orange);">
            <div style="font-weight:700;color:var(--accent-orange);">No barangay coverage defined</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">
                Add the barangays your municipality covers under
                <a href="{{ route('sc.areas') }}" style="color:var(--accent-blue);font-weight:600;">Coverage Areas</a>
                before sorting parcels.
            </div>
        </div>
    @endif

    {{-- Summary --}}
    <div class="ps-stats" data-sorting-stats>
        @foreach ([
            ['Waiting to sort', $toSort, '#FFF4E5', 'var(--accent-orange)', 'inbox'],
            ['Sorted today', $sortedToday, '#E9F8EF', 'var(--accent-green)', 'check'],
            ['Ready for riders', $ready, '#EAF2FB', 'var(--accent-blue)', 'sort'],
            ['Barangays with parcels', $withParcels->count() . ' / ' . $areas->count(), '#F3F0FB', '#6D4AA5', 'location'],
        ] as [$label, $n, $bg, $fg, $ic])
            <div class="card ps-stat">
                <span class="ps-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 19, 'sw' => 2])</span>
                <div><div class="ps-stat-n">{{ $n }}</div><div class="ps-stat-l">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    {{-- Sorting queue: the work to do now --}}
    <div class="card" data-sorting-queue>
        <div class="card-header">
            <h2>Sorting queue <span style="margin-left:6px;" class="badge {{ $toSort ? 'badge-orange' : 'badge-green' }}">{{ $toSort }} {{ \Illuminate\Support\Str::plural('parcel', $toSort) }}</span></h2>
            <span class="card-meta">The barangay is read from the delivery address. Check it, then confirm.@if ($outside) {{ $outside }} {{ \Illuminate\Support\Str::plural('parcel', $outside) }} belong to another municipality.@endif</span>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Parcel</th><th>Delivery address</th><th>Weight</th><th>Destination barangay</th><th style="text-align:right;">Action</th></tr>
            </thead>
            <tbody>
            @forelse ($pending as $parcel)
                @php
                    $isCovered = $covered[$parcel->id] ?? true;
                    $suggest   = $suggested[$parcel->id] ?? null;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('sc.tracking.show', $parcel) }}" class="id-link">{{ $parcel->tracking_number }}</a>
                        <div class="text-sm text-muted" style="margin-top:2px;">{{ $parcel->receiver_name }}</div>
                    </td>
                    <td><div class="ps-addr">{{ $parcel->dropoff_address }}</div></td>
                    <td style="white-space:nowrap;">{{ $parcel->weight_kg ? rtrim(rtrim(number_format((float) $parcel->weight_kg, 2), '0'), '.') . ' kg' : '—' }}</td>
                    @if ($isCovered)
                        <td>
                            <form method="POST" action="{{ route('sc.parcels.sort', $parcel) }}" id="sort-{{ $parcel->id }}">
                                @csrf
                                <select name="area_id" class="form-select" style="width:190px;" required>
                                    <option value="">Select barangay…</option>
                                    @foreach ($areas as $area)
                                        <option value="{{ $area->id }}" @selected($suggest === $area->id)>{{ $area->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <div class="ps-row-suggest" style="color:{{ $suggest ? 'var(--accent-green)' : 'var(--accent-orange)' }};">
                                @include('sc.partials.icon', ['name' => $suggest ? 'check' : 'report', 'size' => 12, 'sw' => 2.4])
                                {{ $suggest ? 'Detected from the address' : 'No match: choose it' }}
                            </div>
                        </td>
                        <td style="text-align:right;"><button class="btn btn-green btn-sm" form="sort-{{ $parcel->id }}">Confirm sort</button></td>
                    @else
                        <td>
                            <form method="POST" action="{{ route('sc.parcels.sort-transfer', $parcel) }}" id="sortx-{{ $parcel->id }}">
                                @csrf
                                <select name="center_id" class="form-select" style="width:210px;" required aria-label="Sorting center for {{ $parcel->tracking_number }}">
                                    <option value="">Sorting center…</option>
                                    @foreach ($hubs as $hub)
                                        <option value="{{ $hub->id }}" @selected(($nextHub[$parcel->id] ?? null) === $hub->id)>{{ \App\Support\SortingCenters::displayName($hub) }} · {{ $hub->assigned_municipality }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <div class="ps-row-suggest" style="color:var(--accent-orange);">
                                @include('sc.partials.icon', ['name' => 'truck', 'size' => 12, 'sw' => 2.2])
                                Outside your area · to {{ $parcel->destination_municipality }}{{ ($nextHub[$parcel->id] ?? null) ? ' (detected)' : '' }}
                            </div>
                        </td>
                        <td style="text-align:right;"><button class="btn btn-blue btn-sm" form="sortx-{{ $parcel->id }}" data-sort-transfer>Sort for hub-to-hub</button></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:30px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;color:var(--accent-green);font-weight:600;">@include('sc.partials.icon', ['name' => 'check', 'size' => 16]) All received parcels are sorted.</span>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Sorted for other sorting centers: waiting for a hub-to-hub (linehaul) trip --}}
    @if ($forTransfer->isNotEmpty())
        <div class="ps-title" id="hub-to-hub">
            <h2>Ready for hub-to-hub transfer</h2>
            <span>{{ $forTransfer->flatten()->count() }} {{ \Illuminate\Support\Str::plural('parcel', $forTransfer->flatten()->count()) }}</span>
        </div>
        <div class="ps-bins" data-transfer-bins>
            @foreach ($forTransfer as $centerId => $group)
                <a href="{{ route('sc.trips', ['parcel' => $group->pluck('id')->all()]) }}#create" class="ps-bin" data-transfer-bin="{{ $centerId }}">
                    <div class="ps-bin-top">
                        <span class="ps-bin-name">{{ \App\Support\SortingCenters::nameOf($group->first()->nextCenter) ?? 'Another sorting center' }}</span>
                        <span class="ps-bin-n">{{ $group->count() }}</span>
                    </div>
                    <div class="text-muted text-sm">Create a linehaul trip →</div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Area bins: barangays with sorted parcels waiting for a rider --}}
    <div class="ps-title">
        <h2>Ready for rider assignment</h2>
        <span>{{ $ready }} {{ \Illuminate\Support\Str::plural('parcel', $ready) }} in {{ $withParcels->count() }} {{ \Illuminate\Support\Str::plural('barangay', $withParcels->count()) }}</span>
    </div>
    @if ($withParcels->isNotEmpty())
        @php $max = max(1, (int) $withParcels->max('sorted_count')); @endphp
        <div class="ps-bins" data-area-bins>
            @foreach ($withParcels as $area)
                <a href="{{ route('sc.delivery-assignment', ['area_id' => $area->id]) }}" class="ps-bin" data-area-bin="{{ $area->id }}">
                    <div class="ps-bin-top">
                        <span class="ps-bin-name">{{ $area->name }}</span>
                        <span class="ps-bin-n">{{ $area->sorted_count }}</span>
                    </div>
                    <div class="ps-bar"><i style="width:{{ round($area->sorted_count / $max * 100) }}%;"></i></div>
                    <span class="ps-bin-go">Assign riders &rarr;</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="card" style="padding:22px;text-align:center;color:var(--text-muted);font-size:13px;">No sorted parcels are waiting for a rider right now.</div>
    @endif

    {{-- The rest of the coverage, folded away --}}
    @if ($empty->isNotEmpty())
        <details class="card" style="margin-top:14px;" data-empty-bins>
            <summary class="ps-empty-head">
                <span style="font-size:13px;font-weight:600;color:var(--text-sub);">Other barangays · {{ $empty->count() }} with no parcels</span>
                <span style="font-size:12px;font-weight:600;color:var(--accent-blue);">Show</span>
            </summary>
            <div style="padding:0 16px 10px;">
                <input type="search" class="form-input ps-search" placeholder="Find a barangay…" data-bin-search aria-label="Find a barangay">
            </div>
            <div class="ps-chips">
                @foreach ($empty as $area)
                    <a href="{{ route('sc.delivery-assignment', ['area_id' => $area->id]) }}" class="ps-chip" data-bin-name="{{ strtolower($area->name) }}">{{ $area->name }} <b>0</b></a>
                @endforeach
            </div>
        </details>
    @endif

</div>

<script>
(function () {
    var box = document.querySelector('[data-bin-search]');
    if (!box) return;
    box.addEventListener('input', function () {
        var q = box.value.trim().toLowerCase();
        document.querySelectorAll('[data-bin-name]').forEach(function (c) { c.style.display = !q || c.dataset.binName.indexOf(q) !== -1 ? '' : 'none'; });
    });
})();
</script>
@endsection
