@extends('sc.layout')
@section('title', ($h->isLoad() ? 'Loading · ' : 'Arrival · ') . $trip->code)
@section('icon', 'truck')

@php
    $driver = $trip->courier;
    $photo = $driver?->user?->profile_photo_url;
    $load = $h->isLoad();
    $done = $load ? $counts['loaded'] : $counts['unloaded'];
    $badge = fn ($s) => match ($s) { 'loaded', 'received' => 'badge-green', 'damaged', 'missing' => 'badge-red', default => 'badge-gray' };
    $word = fn ($s) => match ($s) { 'pending' => 'Not loaded', 'loaded' => $load ? 'Loaded' : 'On truck', 'received' => 'Unloaded', default => ucfirst($s) };
@endphp

@section('content')
<style>
    .hs-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr); align-items: start; }
    @media (max-width: 1000px) { .hs-grid { grid-template-columns: 1fr; } }
    .hs-body { padding: 16px 18px 18px; }
    .hs-who { display: flex; gap: 14px; align-items: center; }
    .hs-photo { width: 72px; height: 72px; flex: none; border-radius: 16px; object-fit: cover; background: #fff1ee; color: var(--accent-orange); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 900; }
    .hs-who h2 { margin: 0; font-size: 19px; font-weight: 800; }
    .hs-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
    .hs-facts div { padding: 10px 12px; border-radius: 10px; background: var(--card-hover, #f8f8f8); }
    .hs-facts small { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); }
    .hs-facts b { font-size: 14px; }
    .hs-counts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .hs-counts div { padding: 12px; border-radius: 12px; border: 1px solid var(--border); text-align: center; }
    .hs-counts small { display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); }
    .hs-counts b { display: block; font-size: 30px; font-weight: 900; line-height: 1.15; }
    .hs-warn { margin: 12px 0 0; padding: 10px 12px; border-radius: 10px; font-size: 13px; font-weight: 700; background: #fffbeb; color: #b45309; }
    .hs-warn.is-ok { background: #ecfdf3; color: var(--accent-green); }
    .hs-scan { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 14px; }
    .hs-scan .wbs-trigger { min-height: 46px; padding: 11px 18px; font-size: 15px; }
    .hs-list { width: 100%; border-collapse: collapse; font-size: 13px; }
    .hs-list td, .hs-list th { padding: 9px 18px; border-top: 1px solid var(--border); text-align: left; }
    .hs-list th { font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); }
</style>

<p style="margin:0 0 12px;"><a href="{{ route('sc.handover') }}" class="text-muted">&larr; Scan driver QR</a> · <a href="{{ route('sc.trips.show', $trip) }}" class="text-muted">Open {{ $trip->code }}</a></p>

<div class="hs-grid" data-handover="{{ $h->id }}" data-open="{{ $open ? 1 : 0 }}" data-live="{{ route('sc.handover.live', $h) }}">
    {{-- Who and what: check before taking the parcels. --}}
    <div class="card">
        <div class="card-header">
            <h2>{{ $h->stageLabel() }} at {{ $h->hub?->code }}</h2>
            <span class="badge {{ $h->confirmed_at ? 'badge-green' : ($open ? 'badge-orange' : 'badge-gray') }}" data-handover-state>
                {{ $h->confirmed_at ? ($load ? 'Sealed' : 'Received') : ($open ? 'Verified · in progress' : 'Closed') }}</span>
        </div>
        <div class="hs-body">
            <div class="hs-who" data-handover-driver>
                @if ($photo)<img src="{{ $photo }}" alt="" class="hs-photo">@else<span class="hs-photo">{{ mb_strtoupper(mb_substr($driver?->fullName() ?? '?', 0, 1)) }}</span>@endif
                <div>
                    <h2>{{ $driver?->fullName() ?? '—' }}</h2>
                    <div class="text-muted">Linehaul driver · {{ \App\Support\SortingCenters::nameOf($trip->company) }}</div>
                    <span class="badge badge-green" style="margin-top:4px;">Active</span>
                </div>
            </div>
            <div class="hs-facts">
                <div><small>Truck</small><b>{{ $h->plate_number ?? $driver?->plate_number }}</b><div class="text-muted" style="font-size:12px;">{{ $driver?->vehicle_type }}{{ $counts['capacity_kg'] ? ' · up to ' . number_format($counts['capacity_kg']) . ' kg' : '' }}</div></div>
                <div><small>Trip</small><b>{{ $trip->code }}</b><div class="text-muted" style="font-size:12px;">{{ $trip->route() }}</div></div>
                <div><small>Expected</small><b>{{ $h->expected }} {{ \Illuminate\Support\Str::plural('parcel', $h->expected) }}</b><div class="text-muted" style="font-size:12px;">{{ $counts['weight_kg'] }} kg</div></div>
                <div><small>Verified</small><b>{{ $h->verified_at->format('g:i A') }}</b><div class="text-muted" style="font-size:12px;">{{ $h->method === 'qr' ? 'QR scanned' : '6-digit code' }} by {{ \App\Support\SortingCenters::nameOf($h->scanner) ?? $h->scanner?->name }}</div></div>
            </div>
            @if ($h->confirmed_at)
                <p class="hs-warn is-ok">{{ $load ? 'Sealed' : 'Receipt confirmed' }} {{ $h->confirmed_at->format('M j, g:i A') }}: {{ $h->scanned }}/{{ $h->expected }} parcels.{{ $h->note ? ' Note: ' . $h->note : '' }}</p>
            @endif
        </div>
    </div>

    {{-- The parcels: scan them on / off, with live counts. --}}
    <div class="card">
        <div class="card-header"><h2>{{ $load ? 'Load the truck' : 'Unload the truck' }}</h2><span class="card-meta">Updates by itself</span></div>
        <div class="hs-body">
            <div class="hs-counts">
                <div><small>Expected</small><b data-c="expected">{{ $counts['expected'] }}</b></div>
                <div><small>{{ $load ? 'Loaded' : 'Unloaded' }}</small><b data-c="{{ $load ? 'loaded' : 'unloaded' }}">{{ $done }}</b></div>
                <div><small>Weight</small><b style="font-size:20px;padding-top:6px;"><span data-c="weight_kg">{{ $counts['weight_kg'] }}</span> kg</b></div>
            </div>
            <p class="hs-warn" data-c-warn @if ($done === $counts['expected'] || $h->confirmed_at) hidden @endif>
                Expected {{ $counts['expected'] }}, {{ $load ? 'loaded' : 'unloaded' }} {{ $done }}.</p>
            @if ($counts['over_capacity'])<p class="hs-warn" style="background:#fef2f2;color:#b91c1c;">Over the truck's capacity ({{ $counts['weight_kg'] }} of {{ $counts['capacity_kg'] }} kg).</p>@endif

            @if ($open)
                <div class="hs-scan">
                    @include('partials.waybill-scanner', ['lookup' => route('sc.handover.scan', $h), 'label' => $load ? 'Scan parcels on' : 'Scan parcels off'])
                    <form method="POST" action="{{ route('sc.handover.scan', $h) }}" style="display:flex;gap:8px;flex:1 1 240px;">@csrf
                        <input name="tracking_number" class="form-input" placeholder="or type the waybill / tracking number" required maxlength="50" autocomplete="off" style="text-transform:uppercase;">
                        <button class="btn btn-ghost">{{ $load ? 'Load' : 'Unload' }}</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('sc.handover.confirm', $h) }}" style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);" data-handover-confirm>
                    @csrf
                    <label for="hs-note" class="text-muted" style="display:block;font-size:12.5px;font-weight:700;margin-bottom:5px;">
                        Note <span data-note-need @if ($done === $counts['expected']) hidden @endif style="color:#b45309;">(required: the count doesn't match)</span></label>
                    <textarea id="hs-note" name="note" class="form-input" rows="2" maxlength="300" placeholder="{{ $load ? 'e.g. 1 parcel not found on the shelf; it stays at the hub.' : 'e.g. 1 parcel was not on the truck.' }}">{{ old('note') }}</textarea>
                    <button class="btn {{ $load ? 'btn-orange' : 'btn-green' }}" style="width:100%;margin-top:10px;min-height:46px;font-size:15px;">
                        {{ $load ? 'Confirm handover · seal the truck' : 'Confirm receipt' }}</button>
                    <p class="text-muted" style="font-size:12px;margin:6px 0 0;">{{ $load ? 'Parcels not scanned come off the trip and stay at ' . $trip->origin?->code . '.' : 'Parcels not scanned become Missing in Transit.' }}</p>
                </form>
            @endif
        </div>
        <table class="hs-list">
            <thead><tr><th>Parcel</th><th>To</th><th>Weight</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($items as $i)
                <tr data-hs-item="{{ $i->parcel?->tracking_number }}">
                    <td style="font-family:monospace;font-weight:700;">{{ $i->parcel?->tracking_number }}</td>
                    <td>{{ $i->parcel?->destination_municipality }}</td>
                    <td>{{ $i->parcel?->weight_kg ? number_format((float) $i->parcel->weight_kg, 2) . ' kg' : '—' }}</td>
                    <td><span class="badge {{ $badge($i->status) }}" data-hs-status>{{ $word($i->status) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if ($open)
<script>
(function () {
    var root = document.querySelector('[data-handover]'), load = {{ $load ? 'true' : 'false' }};
    var words = { pending: 'Not loaded', loaded: load ? 'Loaded' : 'On truck', received: 'Unloaded', damaged: 'Damaged', missing: 'Missing' };
    var badges = { loaded: 'badge-green', received: 'badge-green', damaged: 'badge-red', missing: 'badge-red' };
    function show(d) {
        if (d.confirmed) { location.reload(); return; }
        var c = d.counts, done = load ? c.loaded : c.unloaded;
        root.querySelector('[data-c="expected"]').textContent = c.expected;
        root.querySelector('[data-c="' + (load ? 'loaded' : 'unloaded') + '"]').textContent = done;
        root.querySelector('[data-c="weight_kg"]').textContent = c.weight_kg;
        var warn = root.querySelector('[data-c-warn]');
        warn.hidden = done === c.expected;
        warn.textContent = 'Expected ' + c.expected + ', ' + (load ? 'loaded ' : 'unloaded ') + done + '.';
        var need = root.querySelector('[data-note-need]'); if (need) need.hidden = done === c.expected;
        (d.items || []).forEach(function (i) {
            var row = root.querySelector('[data-hs-item="' + i.tracking + '"] [data-hs-status]');
            if (row) { row.textContent = words[i.status] || i.status; row.className = 'badge ' + (badges[i.status] || 'badge-gray'); }
        });
    }
    function poll() {
        fetch(root.dataset.live, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) show(d); }).catch(function () {});
    }
    // Each scan from the pop-up updates the counts at once; the rest every few seconds (another phone scanning too).
    document.addEventListener('waybill:scanned', poll);
    setInterval(poll, 4000);
})();
</script>
@endif
@endsection
