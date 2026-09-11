@extends('sc.layout')
@section('title', 'Dashboard')
@section('icon', '🏠')
@section('topbar-right')
    <span style="font-size:12px;color:var(--text-muted);">Today · {{ now()->format('M j, Y') }}</span>
@endsection

@section('content')
<div class="page-header"><h1>Dashboard</h1></div>
<div class="page-body">

    {{-- Stat Cards --}}
    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-badge up">+17%</span>
            <div class="stat-icon">📦</div>
            <div class="stat-value">{{ number_format($stats['total_parcels_today']) }}</div>
            <div class="stat-label">Total Parcels Today</div>
        </div>
        <div class="stat-card">
            <span class="stat-badge up">+5%</span>
            <div class="stat-icon">🚚</div>
            <div class="stat-value">{{ number_format($stats['in_transit']) }}</div>
            <div class="stat-label">In Transit</div>
        </div>
        <div class="stat-card">
            <span class="stat-badge up">+18%</span>
            <div class="stat-icon">✅</div>
            <div class="stat-value">{{ number_format($stats['delivered_today']) }}</div>
            <div class="stat-label">Delivered</div>
        </div>
        <div class="stat-card">
            <span class="stat-badge up">+2</span>
            <div class="stat-icon">🏍️</div>
            <div class="stat-value">{{ number_format($stats['active_riders']) }}</div>
            <div class="stat-label">Active Riders</div>
        </div>
        <div class="stat-card">
            <span class="stat-badge down">-3%</span>
            <div class="stat-icon">⏳</div>
            <div class="stat-value">{{ number_format($stats['pending_pickup']) }}</div>
            <div class="stat-label">Pending Pickup</div>
        </div>
        <div class="stat-card">
            <span class="stat-badge up">+8%</span>
            <div class="stat-icon">🗂️</div>
            <div class="stat-value">{{ number_format($stats['sorting_queue']) }}</div>
            <div class="stat-label">Sorting Queue</div>
        </div>
    </div>

    {{-- Bottom grid --}}
    <div class="dashboard-bottom">

        {{-- Recent Parcels --}}
        <div class="card">
            <div class="card-header">
                <h2>Recent Parcels</h2>
                <span class="card-meta">Today · {{ now()->format('M j, Y') }}</span>
            </div>
            <table class="data-table">
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
                @forelse($recentParcels as $d)
                    @php
                        $badgeClass = match($d->status ?? '') {
                            'delivered'        => 'badge-green',
                            'out_for_delivery' => 'badge-orange',
                            'assigned'         => 'badge-blue',
                            'pending_pickup'   => 'badge-yellow',
                            'sorted'           => 'badge-blue',
                            'failed'           => 'badge-red',
                            default            => 'badge-gray',
                        };
                        $badgeLabel = match($d->status ?? '') {
                            'delivered'        => 'Delivered',
                            'out_for_delivery' => 'In Transit',
                            'assigned'         => 'Dispatched',
                            'pending_pickup'   => 'Pending Pickup',
                            'sorted'           => 'Sorting...',
                            default            => ucwords(str_replace('_',' ', $d->status ?? '—')),
                        };
                    @endphp
                    <tr>
                        <td><span class="id-link">{{ $d->parcel->tracking_number ?? 'PRC-' . str_pad($d->id,10,'0',STR_PAD_LEFT) }}</span></td>
                        <td>{{ $d->parcel->seller->name ?? '—' }}</td>
                        <td>{{ $d->area->name ?? '—' }}</td>
                        <td>{{ $d->rider->full_name ?? '—' }}</td>
                        <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                        <td style="color:var(--text-muted);font-size:12px;">{{ $d->updated_at->format('h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">No activity yet today.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Delivery Rate by Area --}}
        <div class="card" style="padding:18px;">
            <h2 style="font-size:14px;font-weight:600;margin-bottom:18px;">Delivery Rate by Area</h2>
            <div class="delivery-rate-list">
                @forelse($areaRates as $area)
                    @php $pct = $area->total > 0 ? round(($area->delivered / $area->total) * 100) : 0; @endphp
                    <div class="dr-item">
                        <div class="dr-top">
                            <span class="dr-city">{{ $area->name }}</span>
                            <span class="dr-pct">{{ $pct }}%</span>
                        </div>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:{{ $pct }}%;{{ $pct < 80 ? 'background:#ffa726;' : '' }}"></div>
                        </div>
                        <div class="dr-count">{{ $area->delivered }} delivered / {{ $area->total }}</div>
                    </div>
                @empty
                    <p style="color:var(--text-muted);font-size:12px;">No area data yet.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection
