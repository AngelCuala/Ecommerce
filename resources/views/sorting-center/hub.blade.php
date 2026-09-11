@extends('admin.layouts.app')
@section('title', 'Sorting Center Hub')
@section('content')

{{-- Welcome bar --}}
<div style="background:var(--paper-raised);border:1px solid var(--line);border-left:4px solid var(--kraft);border-radius:var(--radius);padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <p style="font-family:'Space Grotesk',sans-serif;font-size:18px;font-weight:600;color:var(--ink);margin:0;">
            Welcome, {{ auth()->user()->name }}
        </p>
        <p style="font-size:13px;color:var(--slate);margin:4px 0 0;">
            {{ now()->format('l, F j, Y') }} · Sorting Center Management Portal
        </p>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn--ghost btn--sm">Sign out</button>
    </form>
</div>

{{-- Live stat strip --}}
<div class="stat-grid" style="margin-bottom:32px;">
    <div class="stat"><div class="stat__value">{{ $stats['pending_rider_applications'] }}</div><div class="stat__label">Pending Rider Apps</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['active_riders'] }}</div><div class="stat__label">Active Riders</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['pending_pickup_requests'] }}</div><div class="stat__label">Pickup Requests</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['incoming_parcels'] }}</div><div class="stat__label">Parcels at Hub</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['in_transit'] }}</div><div class="stat__label">Out for Delivery</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['delivered_today'] }}</div><div class="stat__label">Delivered Today</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['failed_deliveries'] }}</div><div class="stat__label">Failed</div></div>
</div>

{{-- Feature cards grid --}}
@php
$features = [
    [
        'icon'  => '🛵',
        'title' => 'Rider Management',
        'desc'  => 'Approve or disapprove rider/courier applications. Activate or deactivate riders.',
        'links' => [
            ['label'=>'View All Riders',   'route'=>'admin.riders.index'],
        ],
        'badge' => $stats['pending_rider_applications'] > 0
            ? $stats['pending_rider_applications'].' pending'
            : null,
        'badge_color' => 'var(--amber)',
    ],
    [
        'icon'  => '📥',
        'title' => 'Pickup Requests',
        'desc'  => 'Confirm, approve, or reject parcel pickup requests submitted by sellers.',
        'links' => [
            ['label'=>'View Requests', 'route'=>'admin.pickup-requests.index'],
        ],
        'badge' => $stats['pending_pickup_requests'] > 0
            ? $stats['pending_pickup_requests'].' pending'
            : null,
        'badge_color' => 'var(--amber)',
    ],
    [
        'icon'  => '📦',
        'title' => 'Incoming Parcels',
        'desc'  => 'Manage parcels received at the hub. Mark as picked up and track their progress.',
        'links' => [
            ['label'=>'View Parcels', 'route'=>'admin.parcels.index'],
        ],
        'badge' => $stats['incoming_parcels'] > 0
            ? $stats['incoming_parcels'].' at hub'
            : null,
        'badge_color' => 'var(--kraft)',
    ],
    [
        'icon'  => '🗂',
        'title' => 'Sorting',
        'desc'  => 'Sort incoming parcels into delivery areas so they can be assigned to riders.',
        'links' => [
            ['label'=>'Sort Parcels', 'route'=>'admin.parcels.index', 'query'=>['status'=>'picked_up']],
        ],
        'badge' => null,
    ],
    [
        'icon'  => '🗺',
        'title' => 'Delivery Assignment',
        'desc'  => 'Assign sorted parcels to riders by area. Match each parcel to the right courier.',
        'links' => [
            ['label'=>'Assign Deliveries', 'route'=>'admin.deliveries.assign-index'],
        ],
        'badge' => null,
    ],
    [
        'icon'  => '🔍',
        'title' => 'Delivery Monitoring',
        'desc'  => 'Live status tracking of all active deliveries. Update statuses manually if needed.',
        'links' => [
            ['label'=>'Monitor Deliveries', 'route'=>'admin.deliveries.monitor'],
        ],
        'badge' => $stats['in_transit'] > 0
            ? $stats['in_transit'].' in transit'
            : null,
        'badge_color' => 'var(--green)',
    ],
    [
        'icon'  => '📈',
        'title' => 'Reports',
        'desc'  => 'Generate delivery reports by date range. Export to CSV for record-keeping.',
        'links' => [
            ['label'=>'View Reports', 'route'=>'admin.parcels-reports.index'],
        ],
        'badge' => null,
    ],
    [
        'icon'  => '💬',
        'title' => 'Chat / Messaging',
        'desc'  => 'Send and receive messages with riders and sellers directly from the portal.',
        'links' => [
            ['label'=>'Open Chat', 'route'=>'admin.chat.index'],
        ],
        'badge' => null,
    ],
    [
        'icon'  => '👤',
        'title' => 'Account Management',
        'desc'  => 'Update your profile details and change your password.',
        'links' => [
            ['label'=>'My Account', 'route'=>'admin.account.edit'],
        ],
        'badge' => null,
    ],
];
@endphp

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    @foreach($features as $f)
        <div style="background:var(--paper-raised);border:1px solid var(--line);border-radius:var(--radius);display:flex;flex-direction:column;">

            {{-- Card header --}}
            <div style="padding:18px 20px 14px;border-bottom:1px solid var(--line);display:flex;align-items:flex-start;gap:12px;">
                <span style="font-size:26px;line-height:1;flex-shrink:0;">{{ $f['icon'] }}</span>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <p style="font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:600;color:var(--ink);margin:0;">
                            {{ $f['title'] }}
                        </p>
                        @if($f['badge'])
                            <span style="font-size:11px;font-weight:600;padding:2px 7px;border-radius:3px;background:{{ $f['badge_color'] }}22;color:{{ $f['badge_color'] }};border:1px solid {{ $f['badge_color'] }}44;">
                                {{ $f['badge'] }}
                            </span>
                        @endif
                    </div>
                    <p style="font-size:13px;color:var(--slate);margin:5px 0 0;line-height:1.45;">{{ $f['desc'] }}</p>
                </div>
            </div>

            {{-- Card links --}}
            <div style="padding:12px 20px;display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;">
                @foreach($f['links'] as $link)
                    <a href="{{ route($link['route'], $link['query'] ?? []) }}"
                       class="btn btn--primary btn--sm">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

        </div>
    @endforeach
</div>

{{-- Recent activity --}}
<div class="panel" style="margin-top:28px;">
    <div class="panel__header">
        <h2>Recent delivery activity</h2>
        <a href="{{ route('admin.deliveries.monitor') }}" class="btn btn--ghost btn--sm">View all</a>
    </div>
    @if($recentActivity->isEmpty())
        <div class="empty-state">No delivery activity yet.</div>
    @else
        <table>
            <thead>
                <tr><th>Tracking #</th><th>Rider</th><th>Status</th><th>Updated</th></tr>
            </thead>
            <tbody>
                @foreach($recentActivity as $d)
                    <tr>
                        <td class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</td>
                        <td>{{ $d->rider->full_name ?? '—' }}</td>
                        <td>
                            <span class="badge badge--{{
                                $d->status === 'delivered'  ? 'approved' :
                                ($d->status === 'failed'    ? 'rejected' :
                                ($d->status === 'out_for_delivery' ? 'active' : 'pending'))
                            }}">
                                {{ str_replace('_', ' ', $d->status) }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $d->updated_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
