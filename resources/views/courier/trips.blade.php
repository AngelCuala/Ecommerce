<x-courier-layout title="Hub Jobs" active="trips">
@php
    $hub = $courier->homeHub;
    $online = $courier->isOnDutyNow();
    $suspended = $courier->isSuspended();
    $spot = fn ($h) => $h ? ['lat' => (float) $h->latitude, 'lng' => (float) $h->longitude] : null;
    $tripSpots = $trip ? ['origin' => $spot($trip->origin), 'destination' => $spot($trip->destination)] : (object) [];
@endphp

@push('styles')
<style>
    .tr-duty { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 18px; padding: 22px 24px; margin-bottom: 22px; }
    @media (max-width: 720px) { .tr-duty { grid-template-columns: 1fr; } }
    .tr-truck { display: flex; width: 58px; height: 58px; align-items: center; justify-content: center; border-radius: 18px; background: #f1f5f9; color: #64748b; }
    .tr-duty.is-on .tr-truck { background: #ecfdf5; color: #059669; }
    .tr-state { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 800; letter-spacing: .02em; color: #64748b; }
    .tr-state i { width: 9px; height: 9px; border-radius: 999px; background: #94a3b8; }
    .tr-duty.is-on .tr-state { color: #047857; }
    .tr-duty.is-on .tr-state i { background: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.18); }
    .tr-duty h2 { margin: 4px 0 3px; font-size: 19px; font-weight: 800; }
    .tr-duty p { margin: 0; font-size: 13px; color: #8a7f78; }
    .tr-btn-on { min-width: 170px; justify-content: center; font-size: 15px; padding: 12px 20px; }
    .tr-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    @media (max-width: 720px) { .tr-grid { grid-template-columns: 1fr; } }
    .tr-stat { padding: 16px 18px; }
    .tr-stat small { display: block; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #8a7f78; }
    .tr-stat b { display: block; margin-top: 4px; font-size: 16px; }
    .tr-route { display: flex; align-items: center; gap: 14px; margin: 10px 0 4px; font-size: 20px; font-weight: 800; }
    .tr-route span { color: #fa4e1c; }
    .tr-modal { position: fixed; inset: 0; margin: auto; height: fit-content; max-height: calc(100vh - 32px); overflow: auto; width: min(520px, calc(100vw - 32px)); padding: 0; border: 0; border-radius: 18px; box-shadow: 0 30px 70px -20px rgba(0,0,0,.45); }
    .tr-modal::backdrop { background: rgba(15,23,42,.45); }
    .tr-modal form { padding: 22px 22px 18px; }
    .tr-check { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1ede9; font-size: 14px; font-weight: 600; }
    .tr-seg { display: inline-flex; gap: 4px; padding: 3px; border-radius: 10px; background: #f5f2ef; }
    .tr-seg label { cursor: pointer; }
    .tr-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .tr-seg span { display: block; padding: 6px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; color: #78716c; }
    .tr-seg input:checked + span.ok { background: #10b981; color: #fff; }
    .tr-seg input:checked + span.bad { background: #e5484d; color: #fff; }
    .tr-seg input:focus-visible + span { outline: 2px solid #fa4e1c; outline-offset: 1px; }
    .tr-note { width: 100%; min-height: 80px; margin-top: 12px; padding: 10px 12px; border: 1px solid #e7e0da; border-radius: 10px; font: inherit; font-size: 13.5px; }
    .tr-loc { margin-top: 12px; font-size: 12.5px; color: #8a7f78; }
    .tr-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin: 16px 0 4px; padding: 0; list-style: none; }
    .tr-steps li { display: flex; align-items: center; gap: 8px; padding-top: 9px; border-top: 4px solid #e7e0da; font-size: 12.5px; font-weight: 700; color: #a8a29e; }
    .tr-steps li b { display: flex; width: 22px; height: 22px; flex: none; align-items: center; justify-content: center; border-radius: 999px; background: #f5f2ef; font-size: 11.5px; }
    .tr-steps li.done { border-color: #10b981; color: #047857; }
    .tr-steps li.done b { background: #10b981; color: #fff; }
    .tr-steps li.now { border-color: #fa4e1c; color: #1c1917; }
    .tr-steps li.now b { background: #fa4e1c; color: #fff; }
    @media (max-width: 560px) { .tr-steps li { font-size: 0; } .tr-steps li b { font-size: 11.5px; } }
    .tr-next { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; padding: 14px 16px; border-radius: 12px; background: #faf8f6; font-size: 13.5px; color: #44403c; }
    .tr-next p { margin: 0; flex: 1 1 280px; }
    .tr-wait { color: #b45309; }
    .tr-ok { color: #047857; }
    .tr-scan { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 14px; }
    .tr-type { display: flex; gap: 8px; flex: 1 1 260px; }
    .tr-type .cx-input { flex: 1; text-transform: uppercase; }
    .tr-type .cx-input::placeholder, #seal_number::placeholder { text-transform: none; }
    .tr-bar { flex: 1 0 100%; height: 6px; border-radius: 999px; background: #f1ede9; overflow: hidden; }
    .tr-bar i { display: block; height: 100%; border-radius: inherit; background: #10b981; transition: width .3s; }
    .tr-parcels { margin-top: 14px; }
    .tr-depart { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1ede9; }
    .tr-label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 700; color: #78716c; }
    .tr-hint { font-size: 12.5px; color: #8a7f78; }
    .tr-delay { margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1ede9; }
    .tr-delay summary { cursor: pointer; font-size: 13.5px; font-weight: 700; color: #b45309; }
    .tr-delay form { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 6px; }
    .tr-delay .cx-input { width: auto; flex: 1 1 180px; }
    .tr-notecard { padding: 18px 20px; margin-bottom: 18px; border-left: 4px solid #f59e0b; font-size: 13.5px; }
</style>
@endpush

<div class="cx-page-head">
    <h1 class="font-display">Hub Jobs</h1>
    <p>{{ $courier->hubName() }} · {{ $courier->fullName() }} · {{ $courier->vehicle_type }} ({{ $courier->plate_number }}) · 4-wheeler: you carry parcels in bulk from hub to hub</p>
</div>

{{-- On Duty / Off Duty --}}
<div class="card tr-duty {{ $courier->on_duty ? 'is-on' : '' }}" data-duty-card>
    <span class="tr-truck"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></span>
    <div>
        <span class="tr-state" data-duty-state><i></i>{{ $suspended ? 'SUSPENDED' : ($courier->on_duty ? 'ONLINE' : 'OFFLINE') }}</span>
        @if ($suspended)
            <h2>Your account is suspended</h2>
            <p>{{ $courier->hubName() }} suspended your courier account. You can't go Online or take trips.</p>
        @elseif ($courier->on_duty)
            <h2>You're Online</h2>
            <p>{{ $courier->hubName() }} can assign you hub-to-hub trips now. Keep this page or the ALVY app open: with both closed you show as Offline after {{ \App\Models\Courier::ONLINE_MINUTES }} minutes.</p>
        @else
            <h2>You're Offline</h2>
            <p>Go Online to get hub-to-hub trips from {{ $courier->hubName() }}. You'll answer a quick truck checklist first.</p>
        @endif
    </div>
    @if (! $suspended)
        @if ($courier->on_duty)
            <form method="POST" action="{{ route('courier.trips.duty') }}">@csrf<input type="hidden" name="on" value="0">
                <button class="cx-btn cx-btn-outline tr-btn-on" data-go-off>Go Offline</button>
            </form>
        @else
            <button type="button" class="cx-btn cx-btn-green tr-btn-on" data-open-checklist>Go Online</button>
        @endif
    @endif
</div>

<div class="tr-grid">
    <div class="card tr-stat"><small>Home hub</small><b>{{ $hub?->code ?? '—' }}</b><span style="font-size:12.5px;color:#8a7f78;">{{ $hub?->name }}</span></div>
    <div class="card tr-stat"><small>Truck</small><b>{{ $courier->vehicle_type }}</b><span style="font-size:12.5px;color:#8a7f78;">{{ $courier->plate_number }}{{ $courier->load_capacity_kg ? ' · up to ' . number_format($courier->load_capacity_kg) . ' kg' : '' }}</span></div>
    <div class="card tr-stat"><small>Earned today</small><b>₱{{ number_format($today, 2) }}</b><span style="font-size:12.5px;color:#8a7f78;">{{ $lastCheck ? 'Last checklist ' . $lastCheck->created_at->diffForHumans() . ($lastCheck->passed ? ' · passed' : ' · failed') : 'No checklist yet' }}</span></div>
</div>

{{-- The current trip --}}
<h2 class="cx-section-title">Your trip</h2>
@if ($trip)
    <div class="card" style="padding:20px 22px;margin-bottom:24px;" data-trip="{{ $trip->id }}">
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
            <p style="font-family:monospace;font-weight:800;font-size:15px;">{{ $trip->code }}</p>
            <span class="cx-pill {{ $trip->status === 'assigned' ? 'cx-pill-amber' : 'cx-pill-green' }}">{{ $trip->label() }}</span>
        </div>
        <div class="tr-route">{{ $trip->origin?->code }} <span>&rarr;</span> {{ $trip->destination?->code }}</div>
        <p style="font-size:13px;color:#8a7f78;">{{ $trip->origin?->name }} to {{ $trip->destination?->name }} · {{ $trip->items()->count() }} {{ \Illuminate\Support\Str::plural('parcel', $trip->items()->count()) }}</p>
        @if ($trip->status === 'assigned')
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;align-items:center;">
                <form method="POST" action="{{ route('courier.trips.accept', $trip->id) }}">@csrf<button class="cx-btn cx-btn-orange">Accept trip</button></form>
                <form method="POST" action="{{ route('courier.trips.decline', $trip->id) }}" style="display:flex;gap:8px;align-items:center;">
                    @csrf
                    <select name="reason" required class="cx-input" style="width:auto;padding:9px 10px;" aria-label="Reason for declining">
                        <option value="">Decline because…</option>
                        @foreach ($declineReasons as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                    </select>
                    <button class="cx-btn cx-btn-outline">Decline</button>
                </form>
            </div>
        @else
            @php
                $loaded = $parcels->where('status', 'loaded')->count();
                $total = $parcels->count();
                $scanning = $trip->status === 'loading' || ($trip->status === 'in_transit' && $trip->needs_rescan);
                $steps = ['accepted' => 1, 'loading' => 2, 'in_transit' => 3, 'arrived' => 4];
                $at = $steps[$trip->status] ?? 0;
            @endphp
            {{-- Where the trip is: Accepted → Loading → In Transit → Arrived (the receiving hub then receives it). --}}
            <ol class="tr-steps">
                @foreach (['Accepted', 'Loading', 'In Transit', 'Arrived'] as $i => $name)
                    <li class="{{ $i + 1 < $at ? 'done' : ($i + 1 === $at ? 'now' : '') }}"><b>{{ $i + 1 < $at ? '✓' : $i + 1 }}</b>{{ $name }}</li>
                @endforeach
            </ol>

            {{-- The next thing to do --}}
            <div class="tr-next">
                @if ($trip->status === 'accepted')
                    @if ($trip->awaitingHandover())
                        <p class="tr-wait">Waiting for {{ \App\Support\SortingCenters::nameOf($trip->destinationCompany) ?? 'the receiving sorting center' }} to accept this trip. You can start loading once they do.</p>
                    @else
                        <p><b>Next:</b> go to {{ $trip->origin?->code }} and start loading the truck.</p>
                        <form method="POST" action="{{ route('courier.trips.loading', $trip->id) }}">@csrf<button class="cx-btn cx-btn-orange">Start loading</button></form>
                    @endif
                @elseif ($trip->status === 'loading')
                    <p><b>Next:</b> scan the QR code on every parcel as it goes on the truck ({{ $loaded }}/{{ $total }} loaded), then seal the truck and depart.</p>
                @elseif ($trip->status === 'in_transit' && $trip->needs_rescan)
                    <p class="tr-wait">You took over this trip after a breakdown. Scan every parcel again before continuing ({{ $loaded }}/{{ $total }}).</p>
                @elseif ($trip->status === 'in_transit')
                    <p><b>On the road</b> since {{ $trip->departed_at?->format('g:i A') }} · seal {{ $trip->seal_number }}{{ $trip->delayed && $trip->eta_at ? ' · delayed (' . $trip->delay_reason . '), new ETA ' . $trip->eta_at->timezone(config('app.business_timezone'))->format('M j, g:i A') : '' }}.
                        When you reach {{ $trip->destination?->code }}, mark the trip Arrived.</p>
                    <form method="POST" action="{{ route('courier.trips.arrived', $trip->id) }}" data-located="destination">@csrf
                        <input type="hidden" name="lat"><input type="hidden" name="lng">
                        <button class="cx-btn cx-btn-green">I've arrived at {{ $trip->destination?->code }}</button>
                    </form>
                @elseif ($trip->status === 'arrived')
                    <p class="tr-ok">Arrived at {{ $trip->destination?->code }} {{ $trip->arrived_at?->format('g:i A') }}. Hand the truck over: {{ $trip->destination?->code }} checks seal {{ $trip->seal_number }} and scans the parcels in. You're paid when they finish receiving.</p>
                @endif
            </div>

            {{-- Loading: scan, list, seal and depart --}}
            @if ($scanning)
                <div class="tr-scan">
                    @include('partials.waybill-scanner', ['lookup' => route('courier.trips.scan', $trip->id), 'label' => 'Scan parcel QR'])
                    <form method="POST" action="{{ route('courier.trips.scan', $trip->id) }}" class="tr-type">@csrf
                        <input name="tracking_number" class="cx-input" placeholder="or type the waybill or tracking number" required maxlength="50" autocomplete="off" aria-label="Tracking number">
                        <button class="cx-btn cx-btn-outline">Load</button>
                    </form>
                    <div class="tr-bar" aria-label="{{ $loaded }} of {{ $total }} loaded"><i style="width:{{ $total ? round($loaded / $total * 100) : 0 }}%"></i></div>
                </div>
            @endif
            @if ($total)
                <table class="cx-table tr-parcels">
                    <thead><tr><th>Tracking</th><th>Going to</th><th>Weight</th><th>On truck</th></tr></thead>
                    <tbody>
                    @foreach ($parcels as $item)
                        <tr data-trip-parcel="{{ $item->parcel?->tracking_number }}">
                            <td style="font-family:monospace;font-weight:700;">{{ $item->parcel?->tracking_number }}@if($item->parcel?->waybill_number)<div style="font-weight:500;font-size:11.5px;color:#8a7f78;">Waybill {{ $item->parcel->waybill_number }}</div>@endif</td>
                            <td>{{ $item->parcel?->destination_municipality }}</td>
                            <td>{{ $item->parcel?->weight_kg ? number_format((float) $item->parcel->weight_kg, 2) . ' kg' : '—' }}</td>
                            <td>@if (in_array($item->status, ['loaded', 'received'], true))<span class="cx-pill cx-pill-green">{{ ucfirst($item->status) }}</span>@else<span class="cx-pill cx-pill-gray">Not yet</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
            @if ($trip->status === 'loading')
                <form method="POST" action="{{ route('courier.trips.depart', $trip->id) }}" class="tr-depart" data-located="origin">@csrf
                    <input type="hidden" name="lat"><input type="hidden" name="lng">
                    <div>
                        <label class="tr-label" for="seal_number">Seal number</label>
                        <input id="seal_number" name="seal_number" class="cx-input" placeholder="BH-0004521" value="{{ old('seal_number') }}" required pattern="[Bb][Hh]-\d{7}" title="BH- followed by 7 digits" maxlength="10" style="text-transform:uppercase;">
                    </div>
                    <button class="cx-btn cx-btn-orange" @disabled($loaded < $total || ! $total)>Seal &amp; depart</button>
                    @if ($loaded < $total)<span class="tr-hint">Scan every parcel first ({{ $total - $loaded }} left).</span>@endif
                </form>
            @endif

            {{-- In Transit: report a delay --}}
            @if ($trip->status === 'in_transit')
                <details class="tr-delay" @if(old('reason')) open @endif>
                    <summary>Report a delay</summary>
                    <form method="POST" action="{{ route('courier.trips.delay', $trip->id) }}">@csrf
                        <select name="reason" class="cx-input" required aria-label="Reason">
                            <option value="">Reason…</option>
                            @foreach ($delayReasons as $r)<option value="{{ $r }}" @selected(old('reason') === $r)>{{ $r }}</option>@endforeach
                        </select>
                        <input type="datetime-local" name="eta" class="cx-input" required aria-label="New ETA" value="{{ old('eta') }}" min="{{ now()->timezone(config('app.business_timezone'))->format('Y-m-d\TH:i') }}">
                        <button class="cx-btn cx-btn-outline">Send</button>
                    </form>
                    <p class="tr-hint">For a Vehicle breakdown, your company can give the trip to another Online courier.</p>
                </details>
            @endif
        @endif
    </div>
@else
    <div class="card cx-empty" style="margin-bottom:24px;">
        <p class="cx-empty-title">No trip right now</p>
        <p class="cx-empty-sub">{{ $courier->on_duty ? 'Stay Online: when your company assigns you a trip it shows here.' : 'Go Online so your company can assign you a trip.' }}</p>
    </div>
@endif

{{-- The receiving hub found a missing or damaged parcel and asks for the courier's side. --}}
@foreach ($needsNote as $t)
    <div class="card tr-notecard" data-needs-note="{{ $t->id }}">
        <p><b>{{ $t->code }}</b> ({{ $t->route() }}) was received with a problem. Tell {{ $t->origin?->code }} what happened.</p>
        <form method="POST" action="{{ route('courier.trips.note', $t->id) }}">@csrf
            <textarea name="note" class="tr-note" required minlength="10" maxlength="300" placeholder="What happened to the missing or damaged parcel? (10 to 300 characters)"></textarea>
            <button class="cx-btn cx-btn-orange" style="margin-top:8px;">Send note</button>
        </form>
    </div>
@endforeach

@if ($recent->isNotEmpty())
    <h2 class="cx-section-title">Recent trips</h2>
    <div class="card" style="overflow:hidden;">
        <table class="cx-table">
            <thead><tr><th>Trip</th><th>Route</th><th>Finished</th><th>Pay</th></tr></thead>
            <tbody>
            @foreach ($recent as $t)
                <tr><td style="font-family:monospace;font-weight:700;">{{ $t->code }}</td><td>{{ $t->route() }}</td><td>{{ $t->completed_at?->format('M j, g:i A') }}</td><td>₱{{ number_format((float) $t->pay, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Pre-trip checklist, then the location, then On Duty (same rules as the app). --}}
@if (! $suspended && ! $courier->on_duty)
<dialog class="tr-modal" id="tr-checklist" aria-labelledby="tr-checklist-title">
    <form method="POST" action="{{ route('courier.trips.duty') }}" data-checklist-form>
        @csrf
        <input type="hidden" name="on" value="1">
        <input type="hidden" name="lat"><input type="hidden" name="lng">
        <h2 id="tr-checklist-title" style="font-size:18px;font-weight:800;">Pre-trip checklist</h2>
        <p style="margin:4px 0 8px;font-size:13px;color:#8a7f78;">Check your truck ({{ $courier->plate_number }}) before going Online.</p>
        @foreach ($items as $i => $item)
            <div class="tr-check">
                <span>{{ $item }}</span>
                <span class="tr-seg" role="radiogroup" aria-label="{{ $item }}">
                    <label><input type="radio" name="items[{{ $item }}]" value="ok" required @checked(old("items.$item") === 'ok')><span class="ok">OK</span></label>
                    <label><input type="radio" name="items[{{ $item }}]" value="not_ok" @checked(old("items.$item") === 'not_ok')><span class="bad">Not OK</span></label>
                </span>
            </div>
        @endforeach
        <textarea name="note" class="tr-note" maxlength="300" placeholder="What's wrong? (needed for anything Not OK, 10 to 300 characters)" data-note hidden>{{ old('note') }}</textarea>
        <p class="tr-loc" data-loc-status>We'll use your location to put you Online. Allow it when your browser asks.</p>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <button type="button" class="cx-btn cx-btn-outline" data-close-checklist>Cancel</button>
            <button class="cx-btn cx-btn-green" data-submit-checklist>Go Online</button>
        </div>
    </form>
</dialog>
@endif

@push('scripts')
<script>
(function () {
    var dlg = document.getElementById('tr-checklist');
    if (!dlg) return;
    var form = dlg.querySelector('[data-checklist-form]'), note = form.querySelector('[data-note]'), status = form.querySelector('[data-loc-status]');
    var hub = @json($hub ? ['lat' => (float) $hub->latitude, 'lng' => (float) $hub->longitude, 'code' => $hub->code] : null);
    document.querySelector('[data-open-checklist]').addEventListener('click', function () { dlg.showModal(); });
    dlg.querySelector('[data-close-checklist]').addEventListener('click', function () { dlg.close(); });
    function syncNote() { note.hidden = !form.querySelector('input[value="not_ok"]:checked'); note.required = !note.hidden; }
    form.addEventListener('change', syncNote); syncNote();
    // Fill in the location, then send. A computer without location falls back to the home hub's spot.
    var located = false;
    form.addEventListener('submit', function (e) {
        if (located) return;
        e.preventDefault();
        var btn = form.querySelector('[data-submit-checklist]'); btn.disabled = true; btn.textContent = 'Getting your location…';
        function go(lat, lng, msg) { form.lat.value = lat; form.lng.value = lng; if (msg) status.textContent = msg; located = true; form.submit(); }
        function fallback() {
            if (hub) go(hub.lat, hub.lng, 'Location not available on this device, so your home hub (' + hub.code + ') is used.');
            else { btn.disabled = false; btn.textContent = 'Go Online'; status.textContent = 'ALVY needs your location. Allow location for this site, then try again.'; }
        }
        if (!navigator.geolocation) return fallback();
        navigator.geolocation.getCurrentPosition(function (p) { go(p.coords.latitude, p.coords.longitude); }, fallback, { timeout: 10000, maximumAge: 60000 });
    });
    @if (old('on') === '1') dlg.showModal(); @endif
})();

// Depart (at the origin hub) and Arrived (at the destination hub) send the location; ALVY checks it is at that hub.
// A computer without location uses that hub's spot.
(function () {
    var hubs = @json($tripSpots);
    document.querySelectorAll('form[data-located]').forEach(function (form) {
        var done = false;
        form.addEventListener('submit', function (e) {
            if (done) return;
            e.preventDefault();
            var btn = form.querySelector('button'), label = btn.textContent, hub = hubs[form.dataset.located];
            btn.disabled = true; btn.textContent = 'Getting your location…';
            function go(lat, lng) { form.lat.value = lat; form.lng.value = lng; done = true; form.submit(); }
            function fallback() {
                if (hub) return go(hub.lat, hub.lng);
                btn.disabled = false; btn.textContent = label; alert('ALVY needs your location. Allow location for this site, then try again.');
            }
            if (!navigator.geolocation) return fallback();
            navigator.geolocation.getCurrentPosition(function (p) { go(p.coords.latitude, p.coords.longitude); }, fallback, { timeout: 10000, maximumAge: 30000 });
        });
    });
})();
</script>
@endpush
</x-courier-layout>
