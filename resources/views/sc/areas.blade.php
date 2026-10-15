@extends('sc.layout')
@section('title', 'Coverage Areas')
@section('icon', 'location')

@section('content')
<div class="page-header"><h1>Coverage Areas</h1></div>
<div class="page-body">

    @if(! $sc->assigned_municipality)
        <div class="card" style="padding:20px;text-align:center;">
            <div style="color:var(--accent-red);font-weight:600;margin-bottom:4px;">No municipality assigned</div>
            <div style="color:var(--text-muted);font-size:13px;">
                An administrator must assign your sorting center to a municipality before you can define barangay coverage.
            </div>
        </div>
    @else
        <div class="card" style="padding:16px 18px;margin-bottom:18px;">
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;">Assigned Municipality</div>
            <div style="font-size:18px;font-weight:700;color:var(--text);margin-top:2px;">{{ $sc->assigned_municipality }}</div>
            <div style="font-size:12.5px;color:var(--text-muted);">
                {{ $sc->assigned_province }} · {{ $sc->name }} ·
                {{ $areas->count() }} of {{ $barangayTotal }} PSA barangays covered
            </div>
        </div>

        <div style="display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start;">

            {{-- Add a barangay (PSA PSGC barangays of the assigned municipality only) --}}
            <div class="card" style="padding:18px;">
                <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px;">Add Barangay</div>
                <form method="POST" action="{{ route('sc.areas.store') }}">
                    @csrf
                    <label class="form-label" for="brgy_select">Barangay</label>
                    <select id="brgy_select" name="barangay_psgc" class="form-select" style="margin-bottom:14px;" required
                            @disabled(empty($barangays))>
                        @if($barangayTotal === 0)
                            <option value="">Municipality not found in the PSA dataset</option>
                        @elseif(empty($barangays))
                            <option value="">All barangays already added</option>
                        @else
                            <option value="">Select barangay…</option>
                            @foreach($barangays as $b)
                                <option value="{{ $b['psgc'] }}" @selected(old('barangay_psgc') === $b['psgc'])>{{ $b['name'] }}</option>
                            @endforeach
                        @endif
                    </select>
                    <button type="submit" class="btn btn-blue" style="width:100%;justify-content:center;gap:6px;" @disabled(empty($barangays))>
                        @include('sc.partials.icon', ['name' => 'location', 'size' => 15, 'sw' => 2]) Add to Coverage
                    </button>
                </form>
                <p style="font-size:11.5px;color:var(--text-muted);margin-top:10px;">
                    Official PSA PSGC barangays of {{ $sc->assigned_municipality }} only.
                </p>
            </div>

            {{-- Current coverage --}}
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr><th>Barangay</th><th>Riders</th><th>Code</th><th style="text-align:right;">Action</th></tr>
                    </thead>
                    <tbody>
                    @forelse($areas as $area)
                        <tr>
                            <td style="font-weight:600;">{{ $area->name }}</td>
                            <td>{{ $area->riders_count }}</td>
                            <td><span class="text-muted text-sm">{{ $area->barangay_code ?? '—' }}</span></td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('sc.areas.destroy', $area) }}"
                                      onsubmit="return confirm('Remove {{ addslashes($area->name) }} from coverage?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#f3c9c9;gap:4px;">
                                        @include('sc.partials.icon', ['name' => 'cross', 'size' => 13, 'sw' => 2.2]) Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted);">No barangays added yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @endif
</div>
@endsection
