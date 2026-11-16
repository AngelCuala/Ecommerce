@extends('sc.layout')
@section('title', 'Rider ' . $rider->full_name)
@section('icon', 'rider')

@section('content')
<div class="page-header"><h1>{{ $rider->full_name }}</h1></div>
<div class="page-body">
    <a href="{{ route('sc.riders') }}" class="text-muted" style="font-size:13px;">&larr; Riders</a>

    @if($rider->needs_reverification)
        <div class="card" style="padding:14px 18px;margin:12px 0;border-left:3px solid var(--accent-orange);" data-reverify>
            <strong>Vehicle changed — re-verify the OR/CR.</strong>
            <form method="POST" action="{{ route('sc.riders.verify', $rider) }}" style="display:inline;margin-left:8px;">@csrf<button class="btn btn-green btn-sm">Mark verified</button></form>
        </div>
    @endif

    <div class="card" style="padding:16px 18px;margin-top:12px;">
        <table class="data-table">
            <tbody>
                <tr><th>Status</th><td>{{ $rider->statusLabel() }} · {{ $rider->isOnlineNow() ? 'Online' : 'Offline' }} · {{ $rider->activeJobsCount() }} active jobs</td></tr>
                <tr><th>Hub transfers</th><td>
                    {{ $rider->can_transport_transfers ? 'Allowed to carry parcels between sorting centers' : 'Last-mile only (not allowed to carry hub transfers)' }}
                    <form method="POST" action="{{ route('sc.riders.transport', $rider) }}" style="display:inline;margin-left:8px;" onsubmit="return confirm('Change this rider\'s transfer permission?')">@csrf
                        <button class="btn btn-ghost btn-sm" data-transport-toggle>{{ $rider->can_transport_transfers ? 'Stop transfers' : 'Allow transfers' }}</button>
                    </form>
                </td></tr>
                <tr><th>Email / phone</th><td>{{ $rider->user->email ?? '—' }} · {{ $rider->phone ?? '—' }}</td></tr>
                <tr><th>Vehicle</th><td>{{ $rider->vehicle_type ?? '—' }}</td></tr>
                <tr><th>Plate number</th><td data-plate>{{ $rider->plate_number ?? '—' }}</td></tr>
                <tr><th>License</th><td>{{ $rider->license_number ?? '—' }} · expires {{ $rider->license_expiry?->format('m/d/Y') ?? '—' }}</td></tr>
                <tr><th>Barangays</th><td>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;" data-rider-areas>
                        @forelse ($rider->areas as $a)
                            <span class="badge {{ (int) $a->id === (int) $rider->area_id ? 'badge-blue' : 'badge-gray' }}" style="gap:6px;">
                                {{ $a->name }}@if ((int) $a->id === (int) $rider->area_id) · main @endif
                                @if ($rider->areas->count() > 1)
                                    <form method="POST" action="{{ route('sc.riders.areas.remove', [$rider, $a]) }}" style="display:inline;" onsubmit="return confirm('Remove {{ $a->name }} from {{ $rider->full_name }}?')">@csrf
                                        <button type="submit" title="Remove {{ $a->name }}" style="border:0;background:none;cursor:pointer;color:inherit;padding:0 0 0 2px;">&times;</button>
                                    </form>
                                @endif
                            </span>
                        @empty
                            <span class="text-muted">{{ $rider->area->name ?? 'No barangay yet' }}</span>
                        @endforelse
                    </div>
                    @if ($barangays->isNotEmpty())
                        <form method="POST" action="{{ route('sc.riders.areas.add', $rider) }}" style="display:flex;gap:6px;max-width:420px;">@csrf
                            <select name="barangay_psgc" class="form-select" required aria-label="Add a barangay for {{ $rider->full_name }}">
                                <option value="">Add a barangay…</option>
                                @foreach ($barangays as $b)<option value="{{ $b['psgc'] }}">{{ $b['name'] }}</option>@endforeach
                            </select>
                            <button class="btn btn-blue btn-sm">Add</button>
                        </form>
                        @error('barangay_psgc')<p style="color:#B91C1C;font-size:12px;margin-top:4px;">{{ $message }}</p>@enderror
                    @endif
                </td></tr>
                <tr><th>Documents</th><td>
                    @if($rider->license_path)<a href="{{ route('sc.riders.document', [$rider, 'license']) }}" target="_blank" style="color:var(--accent-blue);">Driver's license</a>@else No license file @endif
                    ·
                    @if($rider->or_cr_path)<a href="{{ route('sc.riders.document', [$rider, 'or-cr']) }}" target="_blank" style="color:var(--accent-blue);">OR/CR</a>@else No OR/CR file @endif
                </td></tr>
                <tr><th>COD with rider</th><td>₱{{ number_format($wallet->codBalance($rider), 2) }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
