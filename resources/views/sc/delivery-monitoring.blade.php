@extends('sc.layout')
@section('title', 'Delivery Monitoring')
@section('icon', 'truck')

@section('content')
<div class="page-header"><h1>Delivery Monitoring</h1></div>
<div class="page-body">

    {{-- Stat strip --}}
    <div class="monitor-stat-grid">
        <div class="monitor-stat">
            <div class="ms-icon">@include('sc.partials.icon', ['name' => 'parcel', 'size' => 19])</div>
            <div class="ms-value">{{ $monitorStats['dispatched'] }}</div>
            <div class="ms-label">Assigned</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon">@include('sc.partials.icon', ['name' => 'rider', 'size' => 19])</div>
            <div class="ms-value">{{ $monitorStats['out_for_delivery'] }}</div>
            <div class="ms-label">Out for Delivery</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon" style="background:#ecfdf3;color:var(--accent-green);">@include('sc.partials.icon', ['name' => 'check', 'size' => 19])</div>
            <div class="ms-value" style="color:var(--accent-green);">{{ $monitorStats['delivered'] }}</div>
            <div class="ms-label">Delivered Today</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon" style="background:#fef3f2;color:var(--accent-red);">@include('sc.partials.icon', ['name' => 'cross', 'size' => 19])</div>
            <div class="ms-value" style="color:var(--accent-red);">{{ $monitorStats['failed'] }}</div>
            <div class="ms-label">Failed</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon">@include('sc.partials.icon', ['name' => 'return', 'size' => 19])</div>
            <div class="ms-value">{{ $monitorStats['returned'] }}</div>
            <div class="ms-label">Returned</div>
        </div>
    </div>

    @php $cur = request('status',''); @endphp
    <div class="tab-bar mb-16">
        @foreach([''=> 'All','assigned'=>'Assigned','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','failed'=>'Failed','returned'=>'Returned'] as $val=>$lbl)
            <a href="{{ route('sc.delivery-monitoring',['status'=>$val]) }}"
               class="tab {{ $cur===$val ? 'active' : '' }}">{{ $lbl }}</a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th><th>Rider</th><th>Recipient</th><th>Area</th>
                    <th>Tracking</th><th>Rider</th><th>Recipient</th><th>Area</th>
                    <th>Assigned</th><th>Attempt</th><th>Status</th><th>Record Outcome</th>
            </thead>
            <tbody>
            @forelse($deliveries as $d)
                @php
                    $badgeClass = match($d->status) {
                        'delivered'        => 'badge-green',
                        'out_for_delivery' => 'badge-orange',
                        'assigned'         => 'badge-blue',
                        'failed'           => 'badge-red',
                        default            => 'badge-gray',
                    };
                    $badgeLabel = match($d->status) {
                        'out_for_delivery' => 'Out for Delivery',
                        'assigned'         => 'Assigned to Rider',
                        default            => ucwords(str_replace('_',' ',$d->status)),
                    };
                    $parcelStatus = $d->parcel->status ?? null;
                    $isCurrent    = in_array($parcelStatus, ['assigned','in_transit','failed'], true)
                                    && optional($d->parcel->parcelDelivery)->id === $d->id;
                @endphp
                <tr>
                    <td>
                        <span class="id-link">{{ $d->parcel->tracking_number ?? '—' }}</span>
                        @if($d->parcel?->order_id)
                            <div class="text-muted text-sm">Order #{{ str_pad($d->parcel->order_id,6,'0',STR_PAD_LEFT) }}</div>
                        @endif
                    </td>
                    <td>{{ $d->rider->full_name ?? '—' }}</td>
                    <td style="max-width:180px;">
                        {{ $d->parcel->receiver_name ?? '—' }}
                        <div class="text-muted text-sm">{{ $d->parcel->dropoff_address ?? '' }}</div>
                    </td>
                    <td><span class="text-orange">{{ $d->area->name ?? '—' }}</span></td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $d->created_at->format('M d, H:i') }}</td>
                    <td>{{ $attempts[$d->parcel_id] ?? 1 }}</td>
                    <td class="status-badge-cell">
                        <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        @if($d->remarks)
                            <div class="text-muted text-sm" style="margin-top:4px;max-width:160px;">{{ $d->remarks }}</div>
                        @endif
                    </td>
                    <td>
                        @if($isCurrent && in_array($d->status, ['assigned','out_for_delivery']))
                            <form method="POST" action="{{ route('sc.deliveries.update-status',$d) }}" style="display:flex;flex-direction:column;gap:5px;min-width:200px;">
                                @csrf
                                <div style="display:flex;gap:5px;">
                                    <select name="status" class="form-select status-update-select" required>
                                        @if($d->status === 'assigned')
                                            <option value="out_for_delivery">Out for Delivery</option>
                                        @else
                                            <option value="delivered">Delivered</option>
                                        @endif
                                        <option value="failed">Delivery Failed</option>
                                    </select>
                                    <button class="btn btn-blue btn-sm">Save</button>
                                </div>
                                <input type="text" name="remarks" class="form-input" style="padding:5px 8px;font-size:12px;"
                                       placeholder="Remarks (required if failed)" maxlength="500">
                            </form>
                        @elseif($isCurrent && $d->status === 'failed')
                            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('sc.deliveries.reschedule',$d) }}">@csrf
                                    <button class="btn btn-blue btn-sm">Reschedule</button>
                                </form>
                                <form method="POST" action="{{ route('sc.deliveries.return',$d) }}"
                                      onsubmit="return confirm('Return this parcel to the seller?')">@csrf
                                    <button class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#f3c9c9;">Return to Seller</button>
                                </form>
                            </div>
                        @elseif($d->status === 'failed')
                            <span class="text-muted text-sm">Rescheduled</span>
                        @else
                            <span class="text-muted text-sm">{{ $d->delivered_at?->format('M d, H:i') ?? '—' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No deliveries found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $deliveries->links() }}</div>
</div>
@endsection
