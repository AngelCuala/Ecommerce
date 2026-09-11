@extends('admin.layouts.app')
@section('title', 'Rider Details')
@section('content')

<div class="panel">
    <div class="panel__header">
        <h2>{{ $rider->full_name }}</h2>
        <a href="{{ route('admin.riders.index') }}" class="btn btn--ghost btn--sm">Back</a>
    </div>
    <table>
        <tbody>
            <tr><td style="width:200px" class="text-muted">Email</td><td>{{ $rider->user->email ?? '—' }}</td></tr>
            <tr><td class="text-muted">Phone</td><td>{{ $rider->phone ?? '—' }}</td></tr>
            <tr><td class="text-muted">Vehicle</td><td>{{ $rider->vehicle_type ?? '—' }}</td></tr>
            <tr><td class="text-muted">License #</td><td class="tracking-no">{{ $rider->license_number ?? '—' }}</td></tr>
            <tr><td class="text-muted">Area</td><td>{{ $rider->area->name ?? '—' }}</td></tr>
            <tr><td class="text-muted">Application</td><td><span class="badge badge--{{ $rider->application_status }}">{{ ucfirst($rider->application_status) }}</span></td></tr>
            <tr><td class="text-muted">Active</td><td><span class="badge badge--{{ $rider->is_active ? 'active' : 'inactive' }}">{{ $rider->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
            @if($rider->rejection_reason)
            <tr><td class="text-muted">Rejection reason</td><td>{{ $rider->rejection_reason }}</td></tr>
            @endif
            @if($rider->id_document_path)
            <tr>
                <td class="text-muted">ID Document</td>
                <td>
                    @if(Str::endsWith($rider->id_document_path, ['.jpg','.jpeg','.png','.webp']))
                        <img src="{{ asset('storage/'.$rider->id_document_path) }}" style="max-height:150px;border:1px solid var(--line);border-radius:var(--radius);">
                    @else
                        <a href="{{ asset('storage/'.$rider->id_document_path) }}" target="_blank" class="btn btn--sm">Download</a>
                    @endif
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    <div style="padding:14px 18px;display:flex;gap:10px;flex-wrap:wrap;">
        @if($rider->application_status === 'pending')
            <form method="POST" action="{{ route('admin.riders.approve', $rider) }}">@csrf
                <button class="btn btn--primary">Approve</button>
            </form>
            <form method="POST" action="{{ route('admin.riders.disapprove', $rider) }}" style="display:flex;gap:8px;">
                @csrf
                <input type="text" name="rejection_reason" placeholder="Rejection reason (optional)" style="width:240px;">
                <button class="btn btn--danger" onclick="return confirm('Disapprove this application?')">Disapprove</button>
            </form>
        @elseif($rider->application_status === 'approved')
            <form method="POST" action="{{ route('admin.riders.toggle-active', $rider) }}">@csrf
                <button class="btn {{ $rider->is_active ? 'btn--danger' : 'btn--primary' }}">
                    {{ $rider->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        @endif
    </div>
</div>

<div class="panel">
    <div class="panel__header"><h2>Delivery history</h2></div>
    <table>
        <thead><tr><th>Tracking #</th><th>Status</th><th>Assigned</th></tr></thead>
        <tbody>
        @forelse($rider->parcelDeliveries as $d)
            <tr>
                <td class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</td>
                <td><span class="badge badge--{{ $d->status === 'delivered' ? 'approved' : ($d->status === 'failed' ? 'rejected' : 'pending') }}">{{ str_replace('_',' ',$d->status) }}</span></td>
                <td class="text-muted">{{ $d->created_at->format('M d, Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty-state">No deliveries yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@endsection
