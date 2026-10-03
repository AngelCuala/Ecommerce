@extends('sc.layout')
@section('title', 'Pickup Requests')
@section('icon', 'parcel')

@section('content')
<div class="page-header"><h1>Pickup Requests</h1></div>
<div class="page-body">

    <p class="text-muted" style="font-size:12.5px;margin-bottom:14px;">
        Sellers send a pickup request when an order is packed and ready. Confirm the request to schedule the rider pickup,
        or reject it with a reason so the seller can fix and resend it.
    </p>

    @php
        $tabs = ['pending'=>'Ready for Pickup','confirmed'=>'Confirmed','received'=>'Received at Center','rejected'=>'Rejected'];
    @endphp
    <div class="tab-bar">
        @foreach($tabs as $key => $label)
            <a href="{{ route('sc.pickup-requests',['tab'=>$key]) }}" class="tab {{ $tab===$key ? 'active' : '' }}">
                {{ $label }}
                @if(($counts[$key] ?? 0) > 0)
                    <span class="badge {{ $key==='pending' ? 'badge-yellow' : 'badge-gray' }}" style="margin-left:4px;font-size:10px;padding:1px 6px;">{{ $counts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking No.</th><th>Order</th><th>Seller / Pickup Address</th>
                    <th>Deliver To</th><th>Pickup Schedule</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($requests as $parcel)
                @php
                    $badgeClass = match($parcel->status) {
                        'pending_pickup'  => 'badge-yellow',
                        'pickup_approved' => 'badge-blue',
                        'pickup_rejected', 'failed' => 'badge-red',
                        'returned'        => 'badge-gray',
                        default           => 'badge-green',
                    };
                @endphp
                <tr>
                    <td>
                        <span class="id-link">{{ $parcel->tracking_number }}</span>
                        @if(! $parcel->current_sorting_center_id)
                            <div><span class="badge badge-orange" style="font-size:10px;margin-top:4px;">Unrouted</span></div>
                        @endif
                    </td>
                    <td>
                        {{ $parcel->order_id ? '#'.str_pad($parcel->order_id,6,'0',STR_PAD_LEFT) : '—' }}
                        @if($parcel->order && $parcel->order->status === 'Cancelled')
                            <div><span class="badge badge-red" style="font-size:10px;margin-top:4px;">Order cancelled</span></div>
                        @endif
                    </td>
                    <td style="max-width:200px;">
                        <div style="font-weight:600;">{{ $parcel->seller->sellerApplication->shop_name ?? $parcel->seller->name ?? '—' }}</div>
                        <div class="text-muted text-sm">{{ $parcel->pickup_address }}</div>
                    </td>
                    <td style="max-width:200px;">
                        <div style="font-weight:600;">{{ $parcel->receiver_name }}</div>
                        <div class="text-muted text-sm">{{ $parcel->destination_municipality ?? $parcel->dropoff_address }}</div>
                    </td>
                    <td style="font-size:12px;">{{ $parcel->pickup_scheduled_at?->format('M d, Y h:i A') ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $parcel->statusLabel() }}</span>
                        @if($parcel->status === 'pickup_rejected' && $parcel->failure_reason)
                            <div class="text-muted text-sm" style="margin-top:4px;max-width:160px;">{{ $parcel->failure_reason }}</div>
                        @endif
                    </td>
                    <td>
                        @if($parcel->status === 'pending_pickup')
                            <div style="display:flex;flex-direction:column;gap:6px;min-width:190px;">
                                <form method="POST" action="{{ route('sc.pickup-requests.approve',$parcel) }}">@csrf
                                    <button class="btn btn-green btn-sm" style="width:100%;justify-content:center;">Confirm Pickup</button>
                                </form>
                                <form method="POST" action="{{ route('sc.pickup-requests.reject',$parcel) }}" style="display:flex;gap:4px;">@csrf
                                    <input type="text" name="reason" class="form-input" style="padding:5px 8px;font-size:12px;" placeholder="Reason" required maxlength="500">
                                    <button class="btn btn-red btn-sm">Reject</button>
                                </form>
                            </div>
                        @elseif($parcel->status === 'pickup_approved')
                            <a href="{{ route('sc.incoming-parcels',['tab'=>'pickup_approved']) }}" class="btn btn-ghost btn-sm">Receive</a>
                        @else
                            <span class="text-muted text-sm">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">No pickup requests here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $requests->links() }}</div>
</div>
@endsection
