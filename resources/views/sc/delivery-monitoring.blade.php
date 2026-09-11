@extends('sc.layout')
@section('title', 'Delivery Monitoring')
@section('icon', '🚚')

@section('content')
<div class="page-header"><h1>Delivery Monitoring</h1></div>
<div class="page-body">

    {{-- Stat strip --}}
    <div class="monitor-stat-grid">
        <div class="monitor-stat">
            <div class="ms-icon">📦</div>
            <div class="ms-value">{{ $monitorStats['dispatched'] }}</div>
            <div class="ms-label">Dispatched</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon">🏍️</div>
            <div class="ms-value">{{ $monitorStats['out_for_delivery'] }}</div>
            <div class="ms-label">Out for Delivery</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon">✅</div>
            <div class="ms-value" style="color:var(--accent-green);">{{ $monitorStats['delivered'] }}</div>
            <div class="ms-label">Delivered</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon" style="color:var(--accent-red);">✗</div>
            <div class="ms-value" style="color:var(--accent-red);">{{ $monitorStats['failed'] }}</div>
            <div class="ms-label">Failed</div>
        </div>
        <div class="monitor-stat">
            <div class="ms-icon">↩️</div>
            <div class="ms-value">{{ $monitorStats['returned'] }}</div>
            <div class="ms-label">Returned</div>
        </div>
    </div>

    {{-- Tab filter --}}
    @php $cur = request('status',''); @endphp
    <div class="tab-bar mb-16">
        @foreach([''=> 'All','assigned'=>'Dispatched','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','failed'=>'Failed','returned'=>'Returned'] as $val=>$lbl)
            <a href="{{ route('sc.delivery-monitoring',['status'=>$val]) }}"
               class="tab {{ $cur===$val ? 'active' : '' }}">{{ $lbl }}</a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th><th>Rider</th><th>Recipient</th><th>Area</th>
                    <th>Address</th><th>Dispatched</th><th>ETA</th><th>Attempts</th><th>Status</th><th>Update</th>
                </tr>
            </thead>
            <tbody>
            @forelse($deliveries as $d)
                @php
                    $badgeClass = match($d->status) {
                        'delivered'        => 'badge-green',
                        'out_for_delivery' => 'badge-orange',
                        'assigned'         => 'badge-blue',
                        'failed'           => 'badge-red',
                        'returned'         => 'badge-gray',
                        default            => 'badge-gray',
                    };
                    $badgeLabel = match($d->status) {
                        'out_for_delivery' => 'Out for Delivery',
                        'assigned'         => 'Dispatched',
                        default            => ucwords(str_replace('_',' ',$d->status)),
                    };
                    $eta = $d->picked_up_at ? $d->picked_up_at->addHours(2)->format('H:i') : '—';
                @endphp
                <tr>
                    <td><span class="id-link">{{ $d->parcel->tracking_number ?? '—' }}</span></td>
                    <td>{{ $d->rider->full_name ?? '—' }}</td>
                    <td>{{ $d->parcel->receiver_name ?? '—' }}</td>
                    <td><span class="text-orange">{{ $d->area->name ?? '—' }}</span></td>
                    <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-sub);">
                        {{ $d->parcel->dropoff_address ?? '—' }}
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $d->created_at->format('H:i') }}</td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $eta }}</td>
                    <td>1</td>
                    <td class="status-badge-cell"><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('sc.deliveries.update-status',$d) }}" style="display:flex;gap:5px;">
                            @csrf
                            <select name="status" class="form-select status-update-select">
                                @foreach(['assigned'=>'Dispatched','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','failed'=>'Failed','returned'=>'Returned'] as $v=>$l)
                                    <option value="{{ $v }}" @selected($d->status===$v)>{{ $l }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-ghost btn-sm">▾</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-muted);">No deliveries found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $deliveries->links() }}</div>
</div>
@endsection
