@extends('admin.layouts.app')

@section('title', 'Delivery Monitoring')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
@endsection

@section('content')

@php
    $dispatched   = \App\Models\ParcelDelivery::where('status','assigned')->count();
    $outCount     = \App\Models\ParcelDelivery::where('status','out_for_delivery')->count();
    $deliveredCnt = \App\Models\ParcelDelivery::where('status','delivered')->whereDate('delivered_at',today())->count();
    $failedCnt    = \App\Models\ParcelDelivery::where('status','failed')->count();
    $returnedCnt  = \App\Models\ParcelDelivery::where('status','returned')->count();
    $tab          = request('status','');
@endphp

{{-- Stat strip --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
    <div class="stat" style="padding:14px;"><div class="stat__icon">📦</div><div class="stat__value">{{ $dispatched }}</div><div class="stat__label">Dispatched</div></div>
    <div class="stat" style="padding:14px;"><div class="stat__icon">🚚</div><div class="stat__value">{{ $outCount }}</div><div class="stat__label">Out of Delivery</div></div>
    <div class="stat" style="padding:14px;"><div class="stat__icon" style="color:var(--teal);">✅</div><div class="stat__value" style="color:var(--teal);">{{ $deliveredCnt }}</div><div class="stat__label">Delivered</div></div>
    <div class="stat" style="padding:14px;"><div class="stat__icon" style="color:var(--red);">✗</div><div class="stat__value" style="color:var(--red);">{{ $failedCnt }}</div><div class="stat__label">Failed</div></div>
    <div class="stat" style="padding:14px;"><div class="stat__icon">↩</div><div class="stat__value">{{ $returnedCnt }}</div><div class="stat__label">Returned</div></div>
</div>

{{-- Tab filter --}}
<div class="tab-bar" style="margin-bottom:16px;">
    @foreach([''=> 'All', 'assigned'=>'Dispatched','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','failed'=>'Failed','returned'=>'Returned'] as $val=>$lbl)
        <a href="{{ route('admin.deliveries.monitor',['status'=>$val]) }}"
           class="tab {{ $tab===$val ? 'active' : '' }}">{{ $lbl }}</a>
    @endforeach
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Tracking</th>
                <th>Rider</th>
                <th>Recipient</th>
                <th>Area</th>
                <th>Address</th>
                <th>Dispatched</th>
                <th>ETA</th>
                <th>Attempts</th>
                <th>Status</th>
                <th>Update</th>
            </tr>
        </thead>
        <tbody>
        @forelse($deliveries as $d)
            @php
                $badgeClass = match($d->status) {
                    'delivered'        => 'badge--delivered',
                    'out_for_delivery' => 'badge--out-delivery',
                    'assigned'         => 'badge--transit',
                    'failed'           => 'badge--failed',
                    'returned'         => 'badge--transit',
                    default            => 'badge--neutral',
                };
                $badgeLabel = match($d->status) {
                    'out_for_delivery' => 'Out for Delivery',
                    'assigned'         => 'Dispatched',
                    default            => ucwords(str_replace('_',' ',$d->status)),
                };
                $eta = $d->picked_up_at ? $d->picked_up_at->addHours(2)->format('H:i') : '—';
            @endphp
            <tr>
                <td><span class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</span></td>
                <td style="font-weight:500;">{{ $d->rider->full_name ?? '—' }}</td>
                <td>{{ $d->parcel->receiver_name ?? '—' }}</td>
                <td><span style="color:var(--amber);">{{ $d->area->name ?? '—' }}</span></td>
                <td class="text-secondary" style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $d->parcel->dropoff_address ?? '—' }}
                </td>
                <td class="text-muted font-mono">{{ $d->created_at->format('H:i') }}</td>
                <td class="text-muted font-mono">{{ $eta }}</td>
                <td class="text-secondary">{{ $d->parcel->parcelDelivery ? 1 : 0 }}</td>
                <td>
                    <span class="badge {{ $badgeClass }}">
                        @if($d->status==='delivered') ✅ @elseif($d->status==='out_for_delivery') 🚚 @elseif($d->status==='failed') ✗ @elseif($d->status==='returned') ↩ @endif
                        {{ $badgeLabel }}
                    </span>
                </td>
                <td>
                    <form method="POST" action="{{ route('admin.deliveries.update-status',$d) }}" style="display:flex;gap:6px;align-items:center;">
                        @csrf
                        <select name="status" style="width:130px;background:var(--bg-input);font-size:12px;">
                            @foreach(['assigned'=>'Dispatched','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','failed'=>'Failed','returned'=>'Returned'] as $v=>$l)
                                <option value="{{ $v }}" @selected($d->status===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn--ghost btn--sm">▾</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="10"><div class="empty-state"><div class="empty-state__icon">🔍</div>No deliveries found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination">{{ $deliveries->links() }}</div>

@endsection
