@extends('sc.layout')
@section('title', 'Rider Management')
@section('icon', 'rider')
@section('topbar-right')
    <input type="text" class="search-input" placeholder="Search name or ID..."
           onkeyup="filterTable(this.value,'riders-tbody')" />
@endsection

@section('content')

@include('partials.page-tabs', ['tabs' => [['Riders', 'sc.riders', 'sc.riders'], ['Courier applications', 'sc.courier-applications.index', 'sc.courier-applications.*']]])
<div class="page-header" style="display:block;"><h1>Rider Management</h1>
    <p style="margin:4px 0 0;max-width:860px;font:400 13px/1.55 Inter,system-ui,sans-serif;letter-spacing:0;color:var(--text-sub);">
        <b style="color:var(--text);">Riders</b> pick up from sellers and deliver to buyers; only cars and vans take bulky parcels (over {{ \App\Models\Parcel::BULKY_KG }} kg, or big items like a table).
        <b style="color:var(--text);">Linehaul drivers</b> drive trucks between hubs (Linehaul Trips) and are marked <span class="badge badge-blue" style="font-size:10px;">Linehaul driver</span>.
    </p>
</div>
<div class="page-body">

    {{-- Add rider: barangays come from the PSA PSGC dataset for this SC's municipality --}}
    @if(! $sc->hasAssignedMunicipality())
        <div class="card" style="padding:14px 18px;margin-bottom:16px;border-left:3px solid var(--accent-orange);">
            <div style="font-weight:700;color:var(--accent-orange);">No municipality assigned</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">
                An administrator must assign your sorting center to a municipality before you can add riders.
            </div>
        </div>
    @elseif(empty($barangays))
        <div class="card" style="padding:14px 18px;margin-bottom:16px;border-left:3px solid var(--accent-orange);">
            <div style="font-weight:700;color:var(--accent-orange);">Barangays unavailable</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">
                {{ $sc->assigned_municipality }} (code {{ $sc->assigned_municipality_code }}) was not found in the PSA PSGC dataset.
                Ask an administrator to re-assign your municipality.
            </div>
        </div>
    @else
        @php
            $inCoverage = array_values(array_filter($barangays, fn ($b) => in_array($b['code'], $coveredCodes, true)));
            $others     = array_values(array_filter($barangays, fn ($b) => ! in_array($b['code'], $coveredCodes, true)));
        @endphp
        <div class="card" style="padding:16px 18px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                <div style="font-size:14px;font-weight:700;color:var(--text);">Add Rider</div>
                <div style="font-size:12.5px;color:var(--text-muted);">
                    Sorting Center: <strong style="color:var(--text);">{{ $sc->name }}</strong>
                    &nbsp;·&nbsp; Municipality:
                    <strong style="color:var(--text);">{{ $sc->assigned_municipality }}</strong>{{ $sc->assigned_province ? ', ' . $sc->assigned_province : '' }}
                </div>
            </div>
            <form method="POST" action="{{ route('sc.riders.store') }}"
                  style="display:grid;grid-template-columns:1.4fr 1fr 1fr 1.4fr auto;gap:10px;align-items:end;">
                @csrf
                <div>
                    <label class="form-label" for="rider_full_name">Full Name</label>
                    <input type="text" id="rider_full_name" name="full_name" class="form-input" required
                           placeholder="Rider name" value="{{ old('full_name') }}">
                </div>
                <div>
                    <label class="form-label" for="rider_phone">Phone</label>
                    <input type="text" id="rider_phone" name="phone" class="form-input" placeholder="09xx…" value="{{ old('phone') }}" maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
                </div>
                <div>
                    <label class="form-label" for="rider_vehicle">Vehicle</label>
                    <select id="rider_vehicle" name="vehicle_type" class="form-select">
                        <optgroup label="Rider · pickups and deliveries">
                            @foreach(['Motorcycle', 'Tricycle', 'Bicycle'] as $v)<option @selected(old('vehicle_type', 'Motorcycle') === $v)>{{ $v }}</option>@endforeach
                        </optgroup>
                        <optgroup label="Rider · 4 wheels, also bulky parcels">
                            @foreach(['Car', 'Van'] as $v)<option @selected(old('vehicle_type') === $v)>{{ $v }}</option>@endforeach
                        </optgroup>
                        <optgroup label="Linehaul driver · truck, hub to hub">
                            @foreach(['Truck'] as $v)<option @selected(old('vehicle_type') === $v)>{{ $v }}</option>@endforeach
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="rider_barangay">Barangay ({{ count($barangays) }} in {{ $sc->assigned_municipality }})</label>
                    <select id="rider_barangay" name="barangay_psgc" class="form-select" required>
                        <option value="">Select barangay…</option>
                        @if($inCoverage)
                            <optgroup label="In your coverage">
                                @foreach($inCoverage as $b)
                                    <option value="{{ $b['psgc'] }}" @selected(old('barangay_psgc') === $b['psgc'])>{{ $b['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if($others)
                            <optgroup label="Other barangays of {{ $sc->assigned_municipality }} (added to coverage)">
                                @foreach($others as $b)
                                    <option value="{{ $b['psgc'] }}" @selected(old('barangay_psgc') === $b['psgc'])>{{ $b['name'] }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>
                <button type="submit" class="btn btn-blue" style="justify-content:center;">Add</button>
            </form>
            <p style="font-size:11.5px;color:var(--text-muted);margin:10px 0 0;">
                Barangays are from the official PSA PSGC list for {{ $sc->assigned_municipality }}.
                Choosing a barangay outside your coverage adds it to your Coverage Areas automatically.
            </p>
        </div>
    @endif

    <div class="flex-between mb-16">
        <div class="tab-bar" style="margin-bottom:0;">
            @php
                $tabs = ['all'=>'All','pending'=>'Pending','active'=>'Active','inactive'=>'Inactive','rejected'=>'Rejected'];
                $cur  = request('tab','all');
            @endphp
            @foreach($tabs as $key => $label)
                <a href="{{ route('sc.riders',['tab'=>$key]) }}"
                   class="tab {{ $cur===$key ? 'active' : '' }}">
                    {{ $label }}
                    @php
                        $cnt = match($key) {
                            'pending'  => $counts['pending'],
                            'active'   => $counts['active'],
                            'inactive' => $counts['inactive'],
                            'rejected' => $counts['rejected'],
                            default    => $counts['all'],
                        };
                    @endphp
                    @if($cnt > 0 && $key !== 'all')
                        <span class="badge {{ $key==='pending' ? 'badge-yellow' : ($key==='active' ? 'badge-green' : ($key==='rejected' ? 'badge-red' : 'badge-gray')) }}"
                              style="margin-left:4px;font-size:10px;padding:1px 6px;">{{ $cnt }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- Ten columns: let the headers wrap and keep cells a little tighter so Actions stays in view. --}}
    <style>
        .riders-table th { white-space: normal; vertical-align: bottom; }
        .riders-table th, .riders-table td { padding-left: 12px; padding-right: 12px; }
    </style>
    <div class="card" style="overflow-x:auto;">
        <style>
            .riders-table td { vertical-align: middle; }
            .rt-who { display: flex; align-items: center; gap: 10px; }
            .rt-avatar { display: flex; width: 34px; height: 34px; flex: none; align-items: center; justify-content: center; border-radius: 10px; background: #fff1ee; color: var(--accent-blue); font-weight: 800; font-size: 14px; }
            .rt-name { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-weight: 700; }
            .rt-name a { color: var(--accent-blue); }
            .rt-tag { font-size: 10px; white-space: nowrap; }
            .rt-main { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; }
            .rt-sub { margin-top: 2px; font-size: 12px; color: var(--text-muted); }
            .riders-table details[open] summary { margin-left: auto; }
        </style>
        <table class="data-table riders-table">
            <thead>
                <tr>
                    <th>Rider</th><th>Vehicle &amp; area</th><th>Availability</th><th>Delivered</th><th>Status</th><th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="riders-tbody">
            @foreach($riders as $rider)
                @php
                    $isActive   = $rider->application_status === 'approved' && $rider->is_active;
                    $isPending  = $rider->application_status === 'pending';
                    $isRejected = $rider->application_status === 'rejected';
                    $isInactive = $rider->application_status === 'approved' && !$rider->is_active;
                    $badgeClass = $isActive ? 'badge-green' : ($isPending ? 'badge-yellow' : ($isRejected ? 'badge-red' : 'badge-gray'));
                    $badgeLabel = $isActive ? 'Active' : ($isPending ? 'Pending' : ($isRejected ? 'Rejected' : 'Suspended'));
                    $deliveries = $rider->parcelDeliveries()->where('status','delivered')->count();
                    $todayDelivered = $rider->parcelDeliveries()->where('status','delivered')->where('updated_at', '>=', today())->count();
                    $activeJobs = $rider->activeJobsCount();
                @endphp
                @php
                    // A 4-wheel rider does hub-to-hub trips: show their truck's hub, duty and trips instead of buyer jobs.
                    $truck = $rider->isTruck() ? $rider->truck : null;
                    $trip = $truck?->activeTrip();
                    if ($rider->isTruck()) {
                        $activeJobs = $trip ? 1 : 0;
                        $todayDelivered = $truck ? \App\Models\LinehaulTrip::where('paid_courier_id', $truck->id)->whereDate('completed_at', today())->count() : 0;
                        $deliveries = $truck ? \App\Models\LinehaulTrip::where('paid_courier_id', $truck->id)->whereIn('status', \App\Models\LinehaulTrip::DONE)->count() : 0;
                    }
                @endphp
                <tr @if($rider->isTruck()) data-truck-rider="{{ $rider->id }}" @endif>
                    {{-- Rider: ID, name and type, contact --}}
                    <td>
                        <div class="rt-who">
                            <span class="rt-avatar">{{ mb_strtoupper(mb_substr($rider->full_name, 0, 1)) }}</span>
                            <div style="min-width:0;">
                                <div class="rt-name">
                                    <a href="{{ route('sc.riders.show', $rider) }}">{{ $rider->full_name }}</a>
                                    @if($rider->isTruck())<span class="badge badge-blue rt-tag">Linehaul driver</span>@elseif($rider->canCarryBulky())<span class="badge badge-yellow rt-tag">4 wheels · bulky OK</span>@endif
                                </div>
                                <div class="rt-sub"><span class="id-link">RDR-{{ str_pad($rider->id,3,'0',STR_PAD_LEFT) }}</span> · {{ $rider->user->email ?? '' }}{{ $rider->phone ? ' · ' . $rider->phone : '' }}</div>
                            </div>
                        </div>
                    </td>
                    {{-- Vehicle and where they work --}}
                    <td>
                        <div class="rt-main">{{ $rider->vehicle_type ?? '—' }}{{ $rider->plate_number ? ' · ' . $rider->plate_number : '' }}</div>
                        @if($rider->isTruck())
                            <div class="rt-sub"><span class="text-orange">{{ $truck?->homeHub?->code ?? 'No hub yet' }}</span> · hub to hub{{ $truck?->load_capacity_kg ? ' · ' . number_format($truck->load_capacity_kg) . ' kg' : '' }}</div>
                        @else
                            <div class="rt-sub"><span class="text-orange">{{ $rider->barangayNames() }}</span></div>
                        @endif
                        @if($rider->needs_reverification)<span class="badge badge-yellow rt-tag" style="margin:4px 0 0;">Re-verify vehicle</span>@endif
                    </td>
                    {{-- Online, and the job or trip they are on --}}
                    <td>
                        @if($rider->isTruck() ? $truck?->isOnDutyNow() : $rider->isOnlineNow())<span class="badge badge-green" data-online>Online</span>@else<span class="badge badge-gray">Offline</span>@endif
                        <div class="rt-sub" style="margin-top:4px;" data-active-jobs="{{ $activeJobs }}">
                            @if($rider->isTruck())
                                @if($trip)On <a href="{{ route('sc.trips.show', $trip) }}">{{ $trip->code }}</a>@else No trip now @endif
                            @else
                                {{ $activeJobs }} active {{ \Illuminate\Support\Str::plural('job', $activeJobs) }}
                            @endif
                        </div>
                    </td>
                    {{-- Delivered today, and in total --}}
                    <td data-today="{{ $todayDelivered }}">
                        <div class="rt-main">{{ $todayDelivered }} today</div>
                        <div class="rt-sub">{{ $deliveries }} {{ $rider->isTruck() ? \Illuminate\Support\Str::plural('trip', $deliveries) : \Illuminate\Support\Str::plural('delivery', $deliveries) }} in total</div>
                    </td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end;">
                            @if($isPending)
                                <form method="POST" action="{{ route('sc.riders.approve',$rider) }}">@csrf
                                    <button class="btn btn-green btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('sc.riders.reject',$rider) }}">@csrf
                                    <button class="btn btn-red btn-sm">Reject</button>
                                </form>
                            @elseif($isActive)
                                {{-- Suspend asks for a reason: the field opens under the button, so the column stays narrow. --}}
                                <details data-suspend="{{ $rider->id }}">
                                    <summary class="btn btn-red btn-sm" style="list-style:none;cursor:pointer;">Suspend</summary>
                                    <form method="POST" action="{{ route('sc.riders.toggle',$rider) }}" style="display:flex;flex-direction:column;gap:4px;margin-top:6px;width:170px;">@csrf
                                        <input name="reason" class="form-input" style="padding:4px 6px;font-size:11px;width:100%;" placeholder="Reason (10–300)" minlength="10" maxlength="300" required aria-label="Reason for suspending {{ $rider->full_name }}">
                                        <button class="btn btn-red btn-sm">Confirm suspend</button>
                                    </form>
                                </details>
                            @elseif($isInactive)
                                <form method="POST" action="{{ route('sc.riders.toggle',$rider) }}">@csrf
                                    <button class="btn btn-green btn-sm">Reactivate</button>
                                </form>
                            @elseif($isRejected)
                                <form method="POST" action="{{ route('sc.riders.approve',$rider) }}">@csrf
                                    <button class="btn btn-yellow btn-sm">Re-review</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach

            @if($riders->isEmpty())
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">No riders found.</td></tr>
            @endif
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $riders->links() }}</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/table-filter.js') }}"></script>
@endpush
