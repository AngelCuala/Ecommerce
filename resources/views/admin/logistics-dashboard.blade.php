@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
@endsection

@section('topbar-actions')
    <span style="font-size:12px;color:var(--text-muted);">Today, {{ now()->format('M j, Y') }}</span>
@endsection

@section('content')

{{-- Stat strip --}}
<div class="stat-grid">
    @php
    $statItems = [
        ['icon'=>'📦','label'=>'Total Parcels Today', 'value'=> $stats['incoming_parcels'],           'delta'=>'+17%','up'=>true],
        ['icon'=>'🚚','label'=>'In Transit',           'value'=> $stats['in_transit'],                 'delta'=>'+5%', 'up'=>true],
        ['icon'=>'✅','label'=>'Delivered',             'value'=> $stats['delivered_today'],            'delta'=>'+1.6%','up'=>true],
        ['icon'=>'🛵','label'=>'Active Riders',        'value'=> $stats['active_riders'],              'delta'=>'+2',  'up'=>true],
        ['icon'=>'⏳','label'=>'Pending Pickup',       'value'=> $stats['pending_pickup_requests'],    'delta'=>'-3%', 'up'=>false],
        ['icon'=>'🗂','label'=>'Sorting Queue',        'value'=> $stats['incoming_parcels'],           'delta'=>'+6%', 'up'=>true],
    ];
    @endphp
    @foreach($statItems as $s)
    <div class="stat">
        <span class="stat__delta {{ $s['up'] ? 'stat__delta--up' : 'stat__delta--down' }}">{{ $s['delta'] }}</span>
        <div class="stat__icon">{{ $s['icon'] }}</div>
        <div class="stat__value">{{ $s['value'] }}</div>
        <div class="stat__label">{{ $s['label'] }}</div>
    </div>
    @endforeach
</div>

<div class="two-col">

    {{-- Recent parcels table --}}
    <div class="panel">
        <div class="panel__header">
            <h2>Recent Parcels</h2>
            <span class="panel__meta">Today · {{ now()->format('M j, Y') }}</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Parcel ID</th>
                    <th>Sender</th>
                    <th>Area</th>
                    <th>Rider</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recentActivity as $d)
                @php
                    $badgeClass = match($d->status) {
                        'delivered'        => 'badge--delivered',
                        'out_for_delivery' => 'badge--out-delivery',
                        'assigned'         => 'badge--assigned',
                        'failed'           => 'badge--failed',
                        'returned'         => 'badge--transit',
                        default            => 'badge--neutral',
                    };
                    $label = match($d->status) {
                        'out_for_delivery' => 'In Transit',
                        'assigned'         => 'Dispatched',
                        default            => ucwords(str_replace('_',' ',$d->status)),
                    };
                @endphp
                <tr>
                    <td><span class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</span></td>
                    <td>{{ $d->parcel->seller->name ?? '—' }}</td>
                    <td><span style="color:var(--amber);">{{ $d->area->name ?? '—' }}</span></td>
                    <td>{{ $d->rider->full_name ?? '—' }}</td>
                    <td><span class="badge {{ $badgeClass }}"><span class="badge__dot"></span>{{ $label }}</span></td>
                    <td class="text-muted font-mono">{{ $d->updated_at->format('H:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">No delivery activity yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Delivery rate by area --}}
    <div class="panel">
        <div class="panel__header"><h2>Delivery Rate by Area</h2></div>
        <div style="padding:16px 18px;">
            @forelse(\App\Models\DeliveryArea::withCount(['parcelDeliveries as total_count', 'parcelDeliveries as delivered_count' => fn($q) => $q->where('status','delivered')])->get() as $area)
                @php
                    $pct = $area->total_count > 0 ? round(($area->delivered_count / $area->total_count) * 100) : 0;
                @endphp
                <div class="rate-row">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="rate-row__name">{{ $area->name }}</span>
                        <span class="rate-row__pct">{{ $pct }}%</span>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-bar">
                            <div class="progress-bar__fill" style="width:{{ $pct }}%;"></div>
                        </div>
                    </div>
                    <div class="rate-row__sub">{{ $area->delivered_count }} delivered / {{ $area->total_count }}</div>
                </div>
            @empty
                <p class="text-muted" style="font-size:12px;">No area data yet.</p>
            @endforelse
        </div>
    </div>

</div>
@endsection
