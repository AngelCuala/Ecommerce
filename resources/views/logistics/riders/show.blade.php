<x-logistics-layout title="Rider: {{ $rider->full_name }}">

<a href="{{ route('logistics.riders.index') }}" class="btn btn--sm btn--ghost" style="margin-bottom:18px;display:inline-block;">← Back</a>

<div class="panel" style="max-width:640px">
    <div class="panel__header">
        <h2>{{ $rider->full_name }}</h2>
        <span class="badge badge--{{ $rider->application_status === 'approved' ? 'approved' : ($rider->application_status === 'rejected' ? 'rejected' : 'pending') }}">
            {{ ucfirst($rider->application_status) }}
        </span>
    </div>
    <div style="padding:18px;display:grid;gap:10px;grid-template-columns:1fr 1fr;">
        <div><div class="text-muted">Phone</div>{{ $rider->phone ?? '—' }}</div>
        <div><div class="text-muted">Vehicle</div>{{ $rider->vehicle_type ?? '—' }}</div>
        <div><div class="text-muted">License</div>{{ $rider->license_number ?? '—' }}</div>
        <div><div class="text-muted">Area</div>{{ $rider->area->name ?? '—' }}</div>
        <div><div class="text-muted">Active</div>{{ $rider->is_active ? 'Yes' : 'No' }}</div>
        <div><div class="text-muted">Applied</div>{{ $rider->created_at->format('M d, Y') }}</div>
    </div>

    @if($rider->id_document_path)
    <div style="padding:0 18px 18px;">
        <div class="text-muted" style="margin-bottom:6px;">ID Document</div>
        @if(Str::endsWith($rider->id_document_path, ['.jpg','.jpeg','.png','.webp']))
            <img src="{{ asset('storage/'.$rider->id_document_path) }}" style="max-height:180px;border:1px solid var(--line);border-radius:var(--radius);">
        @else
            <a href="{{ asset('storage/'.$rider->id_document_path) }}" target="_blank" class="btn btn--sm">Download Document</a>
        @endif
    </div>
    @endif

    @if($rider->rejection_reason)
    <div style="padding:0 18px 18px;"><div class="text-muted">Rejection Reason</div>{{ $rider->rejection_reason }}</div>
    @endif

    <div style="padding:0 18px 18px;display:flex;gap:10px;flex-wrap:wrap;">
        @if($rider->application_status === 'pending')
            <form method="POST" action="{{ route('logistics.riders.approve', $rider) }}">@csrf
                <button class="btn btn--primary">Approve</button>
            </form>
            <form method="POST" action="{{ route('logistics.riders.disapprove', $rider) }}">@csrf
                <div class="field"><input type="text" name="rejection_reason" placeholder="Rejection reason (optional)"></div>
                <button class="btn btn--danger">Disapprove</button>
            </form>
        @elseif($rider->application_status === 'approved')
            <form method="POST" action="{{ route('logistics.riders.toggle-active', $rider) }}">@csrf
                <button class="btn {{ $rider->is_active ? 'btn--danger' : 'btn--primary' }}">
                    {{ $rider->is_active ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        @endif
    </div>
</div>

</x-logistics-layout>
