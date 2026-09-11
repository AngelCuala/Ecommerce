@extends('sc.layout')
@section('title', 'Pickup Requests')
@section('icon', '📦')

@section('content')
<div class="page-header"><h1>Pickup Requests</h1></div>
<div class="page-body">

    @php
        $cur = request('tab','all');
        $tabs = ['all'=>'All','pending'=>'Pending','confirmed'=>'Confirmed','approved'=>'Approved','rejected'=>'Rejected'];
    @endphp
    <div class="tab-bar">
        @foreach($tabs as $key => $label)
            <a href="{{ route('sc.pickup-requests',['tab'=>$key]) }}"
               class="tab {{ $cur===$key ? 'active' : '' }}"
               style="min-width:100px;text-align:center;">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Request ID</th><th>Seller / Business</th><th>Address</th>
                    <th>Parcels</th><th>Submitted</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($requests as $parcel)
                @php
                    $badgeClass = match($parcel->status) {
                        'pending_pickup'  => 'badge-yellow',
                        'pickup_approved' => 'badge-blue',
                        'pickup_rejected' => 'badge-red',
                        default           => 'badge-green',
                    };
                    $badgeLabel = match($parcel->status) {
                        'pending_pickup'  => 'Pending',
                        'pickup_approved' => 'Confirmed',
                        'pickup_rejected' => 'Rejected',
                        default           => 'Approved',
                    };
                @endphp
                <tr>
                    <td><span class="id-link">PKP-{{ now()->format('Ymd') }}-{{ str_pad($parcel->id,3,'0',STR_PAD_LEFT) }}</span></td>
                    <td>
                        <div style="font-weight:600;">{{ $parcel->seller->name ?? '—' }}</div>
                        <div class="text-muted text-sm">{{ $parcel->seller->email ?? '' }}</div>
                    </td>
                    <td style="max-width:180px;">{{ $parcel->pickup_address }}</td>
                    <td>1</td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $parcel->created_at->format('Y-m-d  H:i') }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            @if($parcel->status === 'pending_pickup')
                                <form method="POST" action="{{ route('sc.pickup-requests.approve',$parcel) }}">@csrf
                                    <button class="btn btn-green btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('sc.pickup-requests.reject',$parcel) }}" onsubmit="return confirm('Reject this request?')">@csrf
                                    <button class="btn btn-red btn-sm">Reject</button>
                                </form>
                            @endif
                            <button class="btn btn-ghost btn-sm">View</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">No pickup requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $requests->links() }}</div>
</div>
@endsection
