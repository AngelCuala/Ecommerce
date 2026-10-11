@extends('sc.layout')
@section('title', 'Courier Applications')
@section('icon', 'truck')
@section('topbar-right')
    <input type="text" class="search-input" placeholder="Search name, plate or email..."
           onkeyup="filterTable(this.value,'couriers-tbody')" />
@endsection

@section('content')
<div class="page-header"><h1>Courier Applications</h1></div>
<div class="page-body">

    <div class="card" style="padding:12px 18px;margin-bottom:16px;font-size:12.5px;color:var(--text-muted);">
        Couriers who chose <strong style="color:var(--text);">your sorting center</strong> when they applied.
        Open an application to check their details and documents, then approve or reject it.
    </div>

    <div class="flex-between mb-16">
        <div class="tab-bar" style="margin-bottom:0;">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended'] as $key => $label)
                <a href="{{ route('sc.courier-applications.index', ['status' => $key]) }}" class="tab {{ $status === $key ? 'active' : '' }}">
                    {{ $label }}
                    @if (($counts[$key] ?? 0) > 0)
                        <span class="badge {{ ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'rejected' => 'badge-red'][$key] ?? 'badge-gray' }}"
                              style="margin-left:4px;font-size:10px;padding:1px 6px;">{{ $counts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr><th>Applicant</th><th>Contact</th><th>Vehicle</th><th>Address</th><th>Applied</th><th></th></tr>
            </thead>
            <tbody id="couriers-tbody">
            @forelse ($couriers as $courier)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $courier->fullName() }}</div>
                        <div class="text-muted text-sm">{{ $courier->user->email ?? '' }}</div>
                    </td>
                    <td>{{ $courier->contact_no ?: '—' }}</td>
                    <td>
                        <div>{{ ucfirst($courier->vehicle_type) }}</div>
                        <div class="text-muted text-sm">{{ $courier->plate_number }}</div>
                    </td>
                    <td class="text-sm">{{ $courier->fullAddress() }}</td>
                    <td>{{ ($courier->submitted_at ?? $courier->created_at)->format('Y-m-d') }}</td>
                    <td>
                        <a href="{{ route('sc.courier-applications.show', $courier) }}" class="btn btn-blue btn-sm">
                            {{ $courier->isPending() ? 'Review' : 'View' }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">No {{ $status }} courier applications.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $couriers->links() }}</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/table-filter.js') }}"></script>
@endpush
