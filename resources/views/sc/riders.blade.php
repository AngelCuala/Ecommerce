@extends('sc.layout')
@section('title', 'Rider Management')
@section('icon', 'rider')
@section('topbar-right')
    <input type="text" class="search-input" placeholder="Search name or ID..."
           onkeyup="filterTable(this.value,'riders-tbody')" />
@endsection

@section('content')
<div class="page-header"><h1>Rider Management</h1></div>
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
                    <input type="text" id="rider_phone" name="phone" class="form-input" placeholder="09xx…" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="form-label" for="rider_vehicle">Vehicle</label>
                    <input type="text" id="rider_vehicle" name="vehicle_type" class="form-input" placeholder="Motorcycle" value="{{ old('vehicle_type') }}">
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

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Rider ID</th><th>Name</th><th>Contact</th><th>Area</th>
                    <th>Applied</th><th>Deliveries</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="riders-tbody">
            @forelse($riders as $rider)
                @php
                    $isActive   = $rider->application_status === 'approved' && $rider->is_active;
                    $isPending  = $rider->application_status === 'pending';
                    $isRejected = $rider->application_status === 'rejected';
                    $isInactive = $rider->application_status === 'approved' && !$rider->is_active;
                    $badgeClass = $isActive ? 'badge-green' : ($isPending ? 'badge-yellow' : ($isRejected ? 'badge-red' : 'badge-gray'));
                    $badgeLabel = $isActive ? 'Active' : ($isPending ? 'Pending' : ($isRejected ? 'Rejected' : 'Inactive'));
                    $deliveries = $rider->parcelDeliveries()->where('status','delivered')->count();
                @endphp
                <tr>
                    <td><span class="id-link">RDR-{{ str_pad($rider->id,3,'0',STR_PAD_LEFT) }}</span></td>
                    <td>
                        <div style="font-weight:600;">{{ $rider->full_name }}</div>
                        <div class="text-muted text-sm">{{ $rider->user->email ?? '' }}</div>
                    </td>
                    <td>{{ $rider->phone ?? '—' }}</td>
                    <td><span class="text-orange">{{ $rider->area->name ?? '—' }}</span></td>
                    <td>{{ $rider->created_at->format('Y-m-d') }}</td>
                    <td>{{ $deliveries }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            @if($isPending)
                                <form method="POST" action="{{ route('sc.riders.approve',$rider) }}">@csrf
                                    <button class="btn btn-green btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('sc.riders.reject',$rider) }}">@csrf
                                    <button class="btn btn-red btn-sm">Reject</button>
                                </form>
                            @elseif($isActive)
                                <form method="POST" action="{{ route('sc.riders.toggle',$rider) }}">@csrf
                                    <button class="btn btn-red btn-sm">Deactivate</button>
                                </form>
                            @elseif($isInactive)
                                <form method="POST" action="{{ route('sc.riders.toggle',$rider) }}">@csrf
                                    <button class="btn btn-green btn-sm">Activate</button>
                                </form>
                            @elseif($isRejected)
                                <form method="POST" action="{{ route('sc.riders.approve',$rider) }}">@csrf
                                    <button class="btn btn-yellow btn-sm">Re-review</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No riders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $riders->links() }}</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/table-filter.js') }}"></script>
@endpush
