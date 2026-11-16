@extends('sc.layout')
@section('title', 'Delivery Monitoring')
@section('icon', 'truck')

@php
    $cur = (string) request('status', '');
    $filters = [
        ''                 => ['All', null],
        'assigned'         => ['Assigned', $monitorStats['dispatched']],
        'out_for_delivery' => ['Out for Delivery', $monitorStats['out_for_delivery']],
        'delivered'        => ['Delivered', null],
        'failed'           => ['Failed', $monitorStats['failed']],
        'returned'         => ['Returned', $monitorStats['returned']],
    ];
    $stats = [
        ['assigned',         'Assigned',         $monitorStats['dispatched'],       'parcel', '#EAF2FB', 'var(--accent-blue)'],
        ['out_for_delivery', 'Out for delivery', $monitorStats['out_for_delivery'], 'rider',  '#FFF4E5', 'var(--accent-orange)'],
        ['delivered',        'Delivered today',  $monitorStats['delivered'],        'check',  '#E9F8EF', 'var(--accent-green)'],
        ['failed',           'Failed',           $monitorStats['failed'],           'cross',  '#FEF2F2', 'var(--accent-red)'],
        ['returned',         'Returned',         $monitorStats['returned'],         'return', '#F3F0FB', '#6D4AA5'],
    ];
@endphp

@section('content')

@include('partials.page-tabs', ['tabs' => [['Delivery assignment', 'sc.delivery-assignment', 'sc.delivery-assignment'], ['Rider dispatch', 'sc.dispatch', 'sc.dispatch*'], ['Delivery monitoring', 'sc.delivery-monitoring', 'sc.delivery-monitoring']]])
<style>
    .dm-stats { display: grid; gap: 12px; grid-template-columns: repeat(5, minmax(0, 1fr)); margin-bottom: 18px; }
    @media (max-width: 1000px) { .dm-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 600px) { .dm-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .dm-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); background: #fff; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
    .dm-stat:hover { border-color: var(--accent-blue); }
    .dm-stat.is-on { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(0,0,0,.04); }
    .dm-stat-ic { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 11px; }
    .dm-stat-n { font-size: 24px; font-weight: 800; line-height: 1; }
    .dm-stat-l { margin-top: 3px; font-size: 12px; color: var(--text-muted); }

    .dm-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 14px; }
    .dm-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; border: 1px solid var(--border); background: #fff; padding: 6px 12px; font-size: 12.5px; font-weight: 600; color: var(--text-sub); text-decoration: none; }
    .dm-chip:hover { border-color: var(--accent-blue); color: var(--text); }
    .dm-chip b { border-radius: 999px; background: #F1F5F9; padding: 0 7px; font-size: 11px; color: var(--text-muted); }
    .dm-chip.is-on { border-color: var(--accent-blue); background: var(--accent-blue); color: #fff; }
    .dm-chip.is-on b { background: rgba(255,255,255,.22); color: #fff; }

    .dm-table td { vertical-align: top; }
    .dm-addr { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 240px; margin-top: 2px; font-size: 12px; line-height: 1.35; color: var(--text-muted); }
    .dm-area { display: inline-block; border-radius: 6px; background: #FFF4E5; padding: 2px 8px; font-size: 12px; font-weight: 600; color: var(--accent-orange); white-space: nowrap; }
    .dm-small { margin-top: 2px; font-size: 12px; color: var(--text-muted); white-space: nowrap; }
    .dm-outcome { min-width: 220px; }
</style>

<div class="page-header"><h1>Delivery Monitoring</h1></div>
<div class="page-body">

    {{-- Stats: each one is also a filter --}}
    <div class="dm-stats" data-monitor-stats>
        @foreach ($stats as [$key, $label, $n, $ic, $bg, $fg])
            <a href="{{ route('sc.delivery-monitoring', ['status' => $key]) }}" class="dm-stat {{ $cur === $key ? 'is-on' : '' }}">
                <span class="dm-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 19, 'sw' => 2])</span>
                <div><div class="dm-stat-n" style="color:{{ $n ? $fg : 'var(--text)' }};">{{ $n }}</div><div class="dm-stat-l">{{ $label }}</div></div>
            </a>
        @endforeach
    </div>

    {{-- Status filter --}}
    <div class="dm-bar">
        @foreach ($filters as $val => [$lbl, $count])
            <a href="{{ route('sc.delivery-monitoring', $val === '' ? [] : ['status' => $val]) }}" class="dm-chip {{ $cur === $val ? 'is-on' : '' }}">
                {{ $lbl }}@if ($count !== null)<b>{{ $count }}</b>@endif
            </a>
        @endforeach
        @if ($deliveries->isNotEmpty())
            <a href="{{ route('sc.waybills', ['ids' => $deliveries->pluck('parcel_id')->filter()->unique()->values()->all()]) }}" target="_blank" class="btn btn-ghost btn-sm" style="gap:6px;margin-left:auto;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                Print waybills
            </a>
        @endif
    </div>

    <div class="card" data-monitor-table>
        <div class="card-header">
            <h2>{{ $filters[$cur][0] ?? 'All' }} deliveries</h2>
            <span class="card-meta">{{ $deliveries->total() }} {{ \Illuminate\Support\Str::plural('delivery', $deliveries->total()) }} · newest first</span>
        </div>
        <div style="overflow-x:auto;">
        <table class="data-table dm-table">
            <thead>
                <tr><th>Parcel</th><th>Recipient</th><th>Barangay</th><th>Rider</th><th>Assigned</th><th>Status</th><th>Outcome</th></tr>
            </thead>
            <tbody>
            @forelse ($deliveries as $d)
                @php
                    $badgeClass = match ($d->status) {
                        'delivered'        => 'badge-green',
                        'out_for_delivery' => 'badge-orange',
                        'assigned'         => 'badge-blue',
                        'failed'           => 'badge-red',
                        default            => 'badge-gray',
                    };
                    $badgeLabel = match ($d->status) {
                        'out_for_delivery' => 'Out for Delivery',
                        'assigned'         => 'Assigned to Rider',
                        default            => ucwords(str_replace('_', ' ', $d->status)),
                    };
                    $parcelStatus = $d->parcel->status ?? null;
                    $isCurrent    = in_array($parcelStatus, ['assigned', 'in_transit', 'failed'], true)
                                    && optional($d->parcel->parcelDelivery)->id === $d->id;
                    $attempt      = $attempts[$d->parcel_id] ?? 1;
                @endphp
                <tr data-delivery="{{ $d->id }}">
                    <td>
                        @if ($d->parcel)
                            <a href="{{ route('sc.tracking.show', $d->parcel) }}" class="id-link">{{ $d->parcel->waybill_number ?? $d->parcel->tracking_number }}</a>
                            @if ($d->parcel->waybill_number)<div class="dm-small">{{ $d->parcel->tracking_number }}</div>@endif
                            <div class="dm-small">
                                @if ($d->parcel->order_id)Order #{{ str_pad($d->parcel->order_id, 6, '0', STR_PAD_LEFT) }} · @endif
                                <a href="{{ route('sc.parcels.waybill', $d->parcel) }}" target="_blank" style="color:var(--accent-blue);font-weight:600;">Waybill</a>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight:600;">{{ $d->parcel->receiver_name ?? '—' }}</div>
                        <div class="dm-addr" title="{{ $d->parcel->dropoff_address ?? '' }}">{{ $d->parcel->dropoff_address ?? '' }}</div>
                    </td>
                    <td><span class="dm-area">{{ $d->area->name ?? '—' }}</span></td>
                    <td style="white-space:nowrap;">{{ $d->rider->full_name ?? '—' }}</td>
                    <td>
                        <div style="white-space:nowrap;">{{ $d->created_at->format('M j, g:i A') }}</div>
                        <div class="dm-small">Attempt {{ $attempt }} of {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass }}" style="white-space:nowrap;">{{ $badgeLabel }}</span>
                        @if ($d->remarks)<div class="dm-small" style="white-space:normal;max-width:180px;margin-top:4px;">{{ $d->remarks }}</div>@endif
                    </td>
                    <td class="dm-outcome">
                        @if ($isCurrent && in_array($d->status, ['assigned', 'out_for_delivery'], true))
                            <form method="POST" action="{{ route('sc.deliveries.update-status', $d) }}" style="display:flex;flex-direction:column;gap:5px;">
                                @csrf
                                <div style="display:flex;gap:5px;">
                                    <select name="status" class="form-select status-update-select" required>
                                        @if ($d->status === 'assigned')
                                            <option value="out_for_delivery">Out for Delivery</option>
                                        @else
                                            <option value="delivered">Delivered</option>
                                        @endif
                                        <option value="failed">Delivery Failed</option>
                                    </select>
                                    <button class="btn btn-blue btn-sm">Save</button>
                                </div>
                                <input type="text" name="remarks" class="form-input" style="padding:5px 8px;font-size:12px;"
                                       placeholder="Override reason (10–300 characters, logged)" minlength="10" maxlength="300" required>
                                @error('remarks')<span style="color:#DC2626;font-size:11px;">{{ $message }}</span>@enderror
                            </form>
                        @elseif ($isCurrent && $d->status === 'failed')
                            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('sc.deliveries.reschedule', $d) }}">@csrf
                                    <button class="btn btn-blue btn-sm">Reschedule</button>
                                </form>
                                <form method="POST" action="{{ route('sc.deliveries.return', $d) }}"
                                      data-confirm="The order is cancelled and the parcel stays at your hub for the seller to collect." data-confirm-title="Cancel this order?" data-confirm-ok="Cancel order">@csrf
                                    <button class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#f3c9c9;">Cancel order</button>
                                </form>
                            </div>
                        @elseif ($d->status === 'failed')
                            <span class="dm-small">Rescheduled</span>
                        @elseif ($d->status === 'delivered')
                            <span class="dm-small" style="color:var(--accent-green);font-weight:600;">Delivered {{ $d->delivered_at?->format('M j, g:i A') }}</span>
                        @else
                            <span class="dm-small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:34px;color:var(--text-muted);font-size:13px;">
                    No {{ $cur ? strtolower($filters[$cur][0] ?? '') . ' ' : '' }}deliveries.
                    @if ($cur)<a href="{{ route('sc.delivery-monitoring') }}" style="color:var(--accent-blue);font-weight:600;margin-left:4px;">Show all</a>@endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div style="margin-top:12px;">{{ $deliveries->links() }}</div>
</div>
@endsection
