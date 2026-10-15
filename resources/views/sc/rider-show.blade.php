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
                <tr><th>Email / phone</th><td>{{ $rider->user->email ?? '—' }} · {{ $rider->phone ?? '—' }}</td></tr>
                <tr><th>Vehicle</th><td>{{ $rider->vehicle_type ?? '—' }}</td></tr>
                <tr><th>Plate number</th><td data-plate>{{ $rider->plate_number ?? '—' }}</td></tr>
                <tr><th>License</th><td>{{ $rider->license_number ?? '—' }} · expires {{ $rider->license_expiry?->format('m/d/Y') ?? '—' }}</td></tr>
                <tr><th>Area</th><td>{{ $rider->area->name ?? '—' }}</td></tr>
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
