@extends('sc.layout')
@section('title', 'Courier Application')
@section('icon', 'truck')

@section('content')
@php
    $badge = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'rejected' => 'badge-red'][$courier->status] ?? 'badge-gray';
    $docs  = [
        'license' => ["Driver's License / Valid ID", $courier->id_license_path],
        'or-cr'   => ['Vehicle Registration (OR / CR)', $courier->or_cr_path],
    ];
@endphp
<div class="page-header">
    <h1>{{ $courier->fullName() }}</h1>
</div>
<div class="page-body">
    <a href="{{ route('sc.courier-applications.index', ['status' => $courier->status]) }}" class="btn btn-ghost btn-sm" style="margin-bottom:14px;">← Back to applications</a>

    @if ($errors->any())
        <div class="flash flash-error" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start;">

        {{-- Applicant details --}}
        <div class="card" style="padding:18px 20px;">
            <div class="flex-between" style="margin-bottom:12px;">
                <div style="font-size:14px;font-weight:700;color:var(--text);">Applicant</div>
                <span class="badge {{ $badge }}">{{ ucfirst($courier->status) }}</span>
            </div>
            <table class="data-table" style="font-size:13px;">
                <tbody>
                    <tr><td class="text-muted" style="width:38%;">Full name</td><td>{{ $courier->fullName() }}</td></tr>
                    <tr><td class="text-muted">Email</td><td>{{ $courier->user->email ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Mobile number</td><td>{{ $courier->contact_no ?: '—' }}</td></tr>
                    <tr><td class="text-muted">Sex</td><td>{{ $courier->sex ?: '—' }}</td></tr>
                    <tr><td class="text-muted">Birthday / Age</td>
                        <td>{{ $courier->birthday?->format('M d, Y') ?? '—' }}{{ $courier->age ? ' (' . $courier->age . ')' : '' }}</td></tr>
                    <tr><td class="text-muted">Address</td><td>{{ $courier->fullAddress() }}</td></tr>
                    <tr><td class="text-muted">Vehicle</td><td>{{ ucfirst($courier->vehicle_type) }}</td></tr>
                    <tr><td class="text-muted">Plate number</td><td>{{ $courier->plate_number }}</td></tr>
                    <tr><td class="text-muted">Applied</td><td>{{ ($courier->submitted_at ?? $courier->created_at)->format('M d, Y g:i A') }}</td></tr>
                    @if ($courier->reviewed_at)
                        <tr><td class="text-muted">Reviewed</td>
                            <td>{{ $courier->reviewed_at->format('M d, Y g:i A') }}{{ $courier->reviewedBy ? ' by ' . $courier->reviewedBy->name : '' }}</td></tr>
                    @endif
                </tbody>
            </table>

            @if ($courier->isRejected() && $courier->rejection_reason)
                <div class="flash flash-error" style="margin-top:12px;"><strong>Rejection reason:</strong> {{ $courier->rejection_reason }}</div>
            @endif
        </div>

        {{-- Documents --}}
        <div class="card" style="padding:18px 20px;">
            <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px;">Documents</div>
            <div style="display:grid;gap:14px;">
                @foreach ($docs as $key => [$label, $path])
                    <div>
                        <div class="text-muted" style="font-size:12px;font-weight:600;margin-bottom:6px;">{{ $label }}</div>
                        @if ($path)
                            @php $url = route('sc.courier-applications.document', [$courier, $key]); @endphp
                            @if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true))
                                <a href="{{ $url }}" target="_blank" rel="noopener">
                                    <img src="{{ $url }}" alt="{{ $label }}"
                                         style="width:100%;max-height:260px;object-fit:contain;border:1px solid var(--border);border-radius:10px;background:#fff;">
                                </a>
                            @else
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Open PDF document</a>
                            @endif
                        @else
                            <div class="text-muted text-sm">Not uploaded.</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Decision --}}
    @if ($courier->isPending())
        <div class="card" style="padding:18px 20px;margin-top:16px;">
            <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;">Decision</div>
            <div class="text-muted" style="font-size:12.5px;margin-bottom:14px;">
                Approving lets this courier sign in and deliver for your sorting center. They are notified by email either way.
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;align-items:end;">
                <form method="POST" action="{{ route('sc.courier-applications.approve', $courier) }}"
                      onsubmit="return confirm('Approve {{ addslashes($courier->fullName()) }} as a courier for your sorting center?');">
                    @csrf
                    <button class="btn btn-green" style="width:100%;justify-content:center;">Approve courier</button>
                </form>
                <form method="POST" action="{{ route('sc.courier-applications.reject', $courier) }}">
                    @csrf
                    <label class="form-label">Reason for rejection *</label>
                    <textarea name="rejection_reason" class="form-input" rows="2" maxlength="500" required
                              placeholder="e.g. The driver's license photo is blurry. Please upload a clear copy.">{{ old('rejection_reason') }}</textarea>
                    <button class="btn btn-red" style="width:100%;justify-content:center;margin-top:8px;">Reject application</button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
