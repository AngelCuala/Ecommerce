@extends('sc.layout')
@section('title', 'Delivery Assignment')
@section('icon', 'location')

@php
    $areaId      = (int) request('area_id');
    $current     = $areaId ? $areas->firstWhere('id', $areaId) : null;
    $totalWait   = (int) $waiting->sum();
    $busyAreas   = $areas->filter(fn ($a) => ($waiting[$a->id] ?? 0) > 0)->sortByDesc(fn ($a) => $waiting[$a->id])->values();
    $busyIds     = $openJobs->keys()->all();
    $free        = $riders->reject(fn ($r) => in_array($r->id, $busyIds, true))->count();
    $ridersByArea = $riders->groupBy('area_id');
    $initials    = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

@section('content')

@include('partials.page-tabs', ['tabs' => [['Delivery assignment', 'sc.delivery-assignment', 'sc.delivery-assignment'], ['Rider dispatch', 'sc.dispatch', 'sc.dispatch*'], ['Delivery monitoring', 'sc.delivery-monitoring', 'sc.delivery-monitoring']]])
<style>
    .da-stats { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
    @media (max-width: 900px) { .da-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .da-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
    .da-stat-ic { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 11px; }
    .da-stat-n { font-size: 24px; font-weight: 800; line-height: 1; color: var(--text); }
    .da-stat-l { margin-top: 3px; font-size: 12px; color: var(--text-muted); }

    .da-title { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; margin: 4px 0 10px; }
    .da-title h2 { font-size: 15px; font-weight: 700; color: var(--text); }
    .da-title span, .da-title a { font-size: 12px; }

    .da-riders { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 20px; scrollbar-width: thin; }
    .da-rider { display: flex; flex: 0 0 auto; min-width: 230px; align-items: center; gap: 11px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); background: #fff; }
    .da-avatar { position: relative; display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 999px; background: #EAF2FB; color: var(--accent-blue); font-size: 13px; font-weight: 800; }
    .da-avatar i { position: absolute; right: -1px; bottom: -1px; width: 11px; height: 11px; border-radius: 999px; border: 2px solid #fff; background: #CBD5E1; }
    .da-avatar i.on { background: var(--accent-green); }
    .da-rider-name { font-size: 13.5px; font-weight: 700; color: var(--text); }
    .da-rider-area { font-size: 12px; color: var(--text-muted); }
    .da-pill { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; border-radius: 999px; padding: 1px 8px; font-size: 11px; font-weight: 700; }

    .da-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 14px; margin-bottom: 14px; }
    .da-filter label { font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .da-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; border: 1px solid var(--border); background: #fff; padding: 5px 11px; font-size: 12.5px; font-weight: 600; color: var(--text-sub); text-decoration: none; transition: border-color .15s, background .15s; }
    .da-chip:hover { border-color: var(--accent-blue); color: var(--text); }
    .da-chip b { border-radius: 999px; background: #FFF1E6; padding: 0 7px; font-size: 11px; color: var(--accent-orange); }
    .da-chip.is-on { border-color: var(--accent-blue); background: var(--accent-blue); color: #fff; }
    .da-chip.is-on b { background: rgba(255,255,255,.22); color: #fff; }

    .da-area { display: inline-block; border-radius: 6px; background: #FFF4E5; padding: 2px 8px; font-size: 12px; font-weight: 600; color: var(--accent-orange); }
    .da-addr { max-width: 230px; font-size: 12.5px; line-height: 1.35; color: var(--text-sub); }
</style>

<div class="page-header"><h1>Delivery Assignment</h1></div>
<div class="page-body">

    {{-- Summary --}}
    <div class="da-stats" data-assignment-stats>
        @foreach ([
            ['Waiting for a rider', $totalWait, '#FFF4E5', 'var(--accent-orange)', 'parcel'],
            ['Barangays with parcels', $busyAreas->count() . ' / ' . $areas->count(), '#F3F0FB', '#6D4AA5', 'location'],
            ['Riders free', $free . ' / ' . $riders->count(), '#E9F8EF', 'var(--accent-green)', 'rider'],
            ['On delivery', count($busyIds), '#EAF2FB', 'var(--accent-blue)', 'truck'],
        ] as [$label, $n, $bg, $fg, $ic])
            <div class="card da-stat">
                <span class="da-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 19, 'sw' => 2])</span>
                <div><div class="da-stat-n">{{ $n }}</div><div class="da-stat-l">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    {{-- Riders --}}
    <div class="da-title">
        <h2>Riders</h2>
        <a href="{{ route('sc.riders') }}" style="color:var(--accent-blue);font-weight:600;">Rider Management &rarr;</a>
    </div>
    @if ($riders->isEmpty())
        <div class="card" style="padding:18px;margin-bottom:20px;font-size:13px;color:var(--text-muted);">
            No active riders yet. Add riders under <a href="{{ route('sc.riders') }}" style="color:var(--accent-blue);font-weight:600;">Rider Management</a>.
        </div>
    @else
        <div class="da-riders" data-rider-cards>
            @foreach ($riders->sortBy(fn ($r) => in_array($r->id, $busyIds, true)) as $rider)
                @php $jobs = (int) ($openJobs[$rider->id] ?? 0); @endphp
                <div class="da-rider" data-rider="{{ $rider->id }}">
                    <span class="da-avatar">{{ $initials($rider->full_name) }}<i class="{{ $rider->is_online ? 'on' : '' }}" title="{{ $rider->is_online ? 'Online' : 'Offline' }}"></i></span>
                    <div style="min-width:0;">
                        <div class="da-rider-name">{{ $rider->full_name }}</div>
                        <div class="da-rider-area">{{ $rider->area->name ?? 'No barangay' }}</div>
                        @if ($jobs)
                            <span class="da-pill" style="background:#FFF4E5;color:#B45309;">On delivery · {{ $jobs }} {{ \Illuminate\Support\Str::plural('job', $jobs) }}</span>
                        @else
                            <span class="da-pill" style="background:#E9F8EF;color:#047857;">Available</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Barangay filter: a dropdown for all of them, quick chips for the ones with parcels --}}
    <div class="card da-filter" data-area-filter>
        <form method="GET" action="{{ route('sc.delivery-assignment') }}" style="display:flex;align-items:center;gap:8px;">
            <label for="da-area">Barangay</label>
            <select id="da-area" name="area_id" class="form-select" style="width:220px;" onchange="this.form.submit()">
                <option value="">All barangays ({{ $totalWait }})</option>
                @foreach ($areas as $a)
                    <option value="{{ $a->id }}" @selected($areaId === $a->id)>{{ $a->name }} ({{ $waiting[$a->id] ?? 0 }})</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-ghost btn-sm">Show</button></noscript>
        </form>
        <span style="width:1px;height:22px;background:var(--border);"></span>
        <a href="{{ route('sc.delivery-assignment') }}" class="da-chip {{ $areaId ? '' : 'is-on' }}">All <b>{{ $totalWait }}</b></a>
        @foreach ($busyAreas->take(8) as $a)
            <a href="{{ route('sc.delivery-assignment', ['area_id' => $a->id]) }}" class="da-chip {{ $areaId === $a->id ? 'is-on' : '' }}">{{ $a->name }} <b>{{ $waiting[$a->id] }}</b></a>
        @endforeach
        @if ($busyAreas->count() > 8)<span class="text-muted" style="font-size:12px;">+{{ $busyAreas->count() - 8 }} more in the list</span>@endif
        @if ($parcels->isNotEmpty())
            <a href="{{ route('sc.waybills', ['ids' => $parcels->pluck('id')->all()]) }}" target="_blank" class="btn btn-ghost btn-sm" style="gap:6px;margin-left:auto;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                Print waybills
            </a>
        @endif
    </div>

    {{-- Parcels --}}
    <div class="card" data-assignment-table>
        <div class="card-header">
            <h2>{{ $current ? $current->name : 'All barangays' }}</h2>
            <span class="card-meta">{{ $parcels->total() }} {{ \Illuminate\Support\Str::plural('parcel', $parcels->total()) }} waiting · oldest sorted first</span>
        </div>
        <table class="data-table">
            <thead>
                <tr><th>Parcel</th><th>Recipient</th><th>Barangay</th><th>Address</th><th>Weight</th><th>Rider</th><th style="text-align:right;">Action</th></tr>
            </thead>
            <tbody>
            @forelse ($parcels as $parcel)
                @php $areaRiders = $ridersByArea->get($parcel->area_id, collect()); @endphp
                <tr>
                    <td>
                        <a href="{{ route('sc.tracking.show', $parcel) }}" class="id-link">{{ $parcel->waybill_number ?? $parcel->tracking_number }}</a>
                        <div class="text-muted text-sm" style="white-space:nowrap;margin-top:2px;">
                            <a href="{{ route('sc.parcels.waybill', $parcel) }}" target="_blank" style="color:var(--accent-blue);font-weight:600;">Print waybill</a>
                        </div>
                        @if ($parcel->deliveries()->where('status', 'failed')->exists())
                            <span class="badge badge-red" style="font-size:10px;margin-top:4px;">Re-delivery</span>
                        @endif
                    </td>
                    <td>
                        {{ $parcel->receiver_name }}
                        <div class="text-muted text-sm">{{ $parcel->receiver_phone }}</div>
                    </td>
                    <td><span class="da-area">{{ $parcel->area->name ?? '—' }}</span></td>
                    <td><div class="da-addr">{{ $parcel->dropoff_address }}</div></td>
                    <td style="white-space:nowrap;">{{ $parcel->weight_kg ? rtrim(rtrim(number_format((float) $parcel->weight_kg, 2), '0'), '.') . ' kg' : '—' }}</td>
                    @if ($areaRiders->isEmpty() && $riders->isEmpty())
                        <td colspan="2">
                            <span class="badge badge-yellow">No rider for this barangay</span>
                            <div class="text-muted text-sm" style="margin-top:4px;">
                                <a href="{{ route('sc.riders') }}" style="color:var(--accent-blue);font-weight:600;">Add a rider</a> for {{ $parcel->area->name ?? 'this barangay' }}.
                            </div>
                        </td>
                    @elseif ($areaRiders->isEmpty())
                        {{-- No rider covers this barangay: any of the company's riders, nearest first (the first is picked). --}}
                        @php $nearest = \App\Services\NearestRiders::ranked($parcel, $riders); @endphp
                        <td>
                            <span class="badge badge-yellow" style="margin-bottom:4px;">No rider for this barangay · nearest first</span>
                            <form method="POST" action="{{ route('sc.parcels.assign', $parcel) }}" id="assign-{{ $parcel->id }}" data-nearest-riders>
                                @csrf
                                <select name="rider_id" class="form-select rider-select" required style="min-width:230px;">
                                    @foreach ($nearest as $i => $x)
                                        <option value="{{ $x['rider']->id }}" @selected($i === 0)>
                                            {{ $x['rider']->full_name }} · {{ $x['rider']->area->name ?? 'no barangay' }}{{ $x['km'] !== null ? ' · ' . $x['km'] . ' km' : '' }}{{ $x['rider']->is_online ? ' · online' : ' · offline' }}{{ in_array($x['rider']->id, $busyIds, true) ? ' · on delivery' : '' }}{{ $i === 0 ? ' (nearest)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td style="text-align:right;"><button class="btn btn-blue btn-sm" form="assign-{{ $parcel->id }}">Assign</button></td>
                    @else
                        <td>
                            <form method="POST" action="{{ route('sc.parcels.assign', $parcel) }}" id="assign-{{ $parcel->id }}">
                                @csrf
                                <select name="rider_id" class="form-select rider-select" required style="min-width:190px;">
                                    @foreach ($areaRiders->sortBy(fn ($r) => in_array($r->id, $busyIds, true)) as $r)
                                        <option value="{{ $r->id }}">{{ $r->full_name }}{{ in_array($r->id, $busyIds, true) ? ' (on delivery)' : ' (free)' }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td style="text-align:right;"><button class="btn btn-blue btn-sm" form="assign-{{ $parcel->id }}">Assign</button></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:34px;">
                    <div style="display:inline-flex;flex-direction:column;align-items:center;gap:6px;color:var(--text-muted);font-size:13px;">
                        <span style="color:var(--accent-green);">@include('sc.partials.icon', ['name' => 'check', 'size' => 22, 'sw' => 2])</span>
                        {{ $current ? 'No parcels waiting in ' . $current->name . '.' : 'No sorted parcels are waiting for a rider.' }}
                        @if ($current && $totalWait)<a href="{{ route('sc.delivery-assignment') }}" style="color:var(--accent-blue);font-weight:600;">See all {{ $totalWait }} waiting</a>@endif
                    </div>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $parcels->links() }}</div>
</div>
@endsection
