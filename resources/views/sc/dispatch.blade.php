@extends('sc.layout')
@section('title', 'Rider Dispatch')
@section('icon', 'rider')

@section('content')

@include('partials.page-tabs', ['tabs' => [['Delivery assignment', 'sc.delivery-assignment', 'sc.delivery-assignment'], ['Rider dispatch', 'sc.dispatch', 'sc.dispatch*'], ['Delivery monitoring', 'sc.delivery-monitoring', 'sc.delivery-monitoring']]])
<div class="page-header"><h1>Rider Dispatch</h1></div>
<div class="page-body">
    <p class="text-muted" style="margin:-4px 0 16px;font-size:13px;">
        Riders are assigned per barangay. Auto-assign gives each waiting job straight to the Online rider who covers its barangay:
        the seller's barangay for a pickup, the buyer's barangay for a delivery. Jobs whose barangay has no Online rider stay here for you to assign manually.
    </p>

    {{-- Auto-assign: pickups and deliveries separately. --}}
    <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));margin-bottom:16px;" data-auto-assign>
        @foreach ([
            ['pickup', 'Pickups', 'Seller → hub, by the seller\'s barangay', $waitingPickups, $readyPickups, '#FFF4E5', 'var(--accent-orange)'],
            ['delivery', 'Deliveries', 'Hub → buyer, by the buyer\'s barangay', $waitingDeliveries, $readyDeliveries, '#EAF2FB', 'var(--accent-blue)'],
        ] as [$kind, $label, $sub, $waiting, $ready, $bg, $fg])
            <div class="card" style="padding:16px 18px;display:flex;flex-direction:column;gap:10px;" data-auto-kind="{{ $kind }}">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="display:flex;width:40px;height:40px;align-items:center;justify-content:center;border-radius:11px;background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $kind === 'pickup' ? 'upload' : 'rider', 'size' => 19, 'sw' => 2])</span>
                    <div style="flex:1;">
                        <div style="font-weight:800;">{{ $label }} waiting <span class="badge badge-gray">{{ $waiting }}</span></div>
                        <div class="text-muted" style="font-size:12px;">{{ $sub }}</div>
                    </div>
                </div>
                <div style="font-size:12.5px;color:var(--text-muted);">
                    {{ $ready }} {{ $ready === 1 ? 'has' : 'have' }} an Online barangay rider{{ $waiting - $ready > 0 ? ' · ' . ($waiting - $ready) . ' need manual assignment' : '' }}.
                </div>
                <form method="POST" action="{{ route('sc.dispatch.auto-all', $kind) }}"
                      data-confirm="{{ $ready }} {{ \Illuminate\Support\Str::plural(strtolower(rtrim($label, 's')), $ready) }} will go to the rider of {{ $ready === 1 ? 'its' : 'their' }} barangay{{ $waiting - $ready > 0 ? '; ' . ($waiting - $ready) . ' without an Online barangay rider stay here' : '' }}."
                      data-confirm-title="Auto-assign {{ strtolower($label) }}?" data-confirm-ok="Auto-assign">
                    @csrf
                    <button class="btn btn-sm {{ $kind === 'pickup' ? 'btn-orange' : 'btn-blue' }}" style="width:100%;" @disabled($ready === 0)>Auto-assign {{ $label }}</button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- Riders --}}
    <div class="card" style="padding:14px 18px;margin-bottom:16px;">
        <div style="font-weight:700;margin-bottom:8px;">Riders</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @forelse($riders as $r)
                <span class="badge {{ $r->isSuspended() ? 'badge-red' : ($r->online_now ? 'badge-green' : 'badge-gray') }}" data-rider="{{ $r->full_name }}">
                    {{ $r->full_name }} · {{ $r->barangayNames() }} · {{ $r->isSuspended() ? 'Suspended' : ($r->online_now ? 'Online' : 'Offline') }} · {{ $r->active_jobs }} {{ \Illuminate\Support\Str::plural('job', $r->active_jobs) }}
                </span>
            @empty
                <span class="text-muted">No approved riders yet.</span>
            @endforelse
        </div>
    </div>

    {{-- L3: bulk assignment — tick the parcels below, then pick one rider. --}}
    <form id="bulk-assign" method="POST" action="{{ route('sc.dispatch.bulk') }}" class="card" style="padding:12px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">@csrf
        <strong style="font-size:13px;">Bulk assign</strong>
        <span class="text-muted" style="font-size:12px;">Tick parcels in the lists below, then choose a rider. Parcels the rider can't take stay in the queue.</span>
        <select name="rider_id" class="form-select" style="min-width:170px;margin-left:auto;" aria-label="Rider for the selected parcels">
            @include('sc.partials.rider-options')
        </select>
        <button class="btn btn-green btn-sm">Assign selected</button>
        @if ($riderChoices->where('available', true)->isEmpty())
            <p class="text-muted" style="flex-basis:100%;font-size:12px;margin:2px 0 0;" data-no-rider-online>
                {{ $riders->isEmpty() ? 'This center has no approved riders yet.' : 'No rider can take barangay jobs right now (suspended, or truck couriers).' }}
            </p>
        @endif
    </form>

    @foreach (['Waiting for a rider' => $jobs, 'Returned to the hub — assign manually' => $unassigned] as $heading => $list)
        <div class="card" style="margin-bottom:16px;" @if($loop->last) id="unassigned" @endif>
            <div style="padding:14px 18px;font-weight:700;">{{ $heading }} <span class="badge badge-gray">{{ $list->count() }}</span></div>
            <table class="data-table">
                <thead><tr><th></th><th>Tracking</th><th>Job</th><th>From</th><th>To</th><th>COD</th><th>Barangay rider</th><th>Assign</th></tr></thead>
                <tbody>
                @forelse($list as $p)
                    <tr data-job="{{ $p->tracking_number }}">
                        <td><input type="checkbox" form="bulk-assign" name="parcel_ids[]" value="{{ $p->id }}" aria-label="Select {{ $p->tracking_number }}"></td>
                        <td><span class="id-link">{{ $p->tracking_number }}</span></td>
                        <td>{{ ucfirst($p->job_kind) }}{{ $p->job_kind === 'delivery' ? ' · attempt ' . ($p->deliveries()->count() + 1) : '' }}</td>
                        <td style="font-size:12px;">{{ $p->seller->name ?? '—' }}<div class="text-muted">{{ \Illuminate\Support\Str::limit($p->pickup_address, 40) }}</div></td>
                        <td style="font-size:12px;">{{ $p->receiver_name }}<div class="text-muted">{{ $p->area->name ?? $p->destination_municipality }}</div></td>
                        <td>{{ $p->order && strtoupper($p->order->payment_method) === 'COD' && $p->order->payment_status !== 'Paid' ? '₱' . number_format($p->order->total_price, 2) : 'Paid' }}</td>
                        <td style="font-size:12px;">
                            @if($p->barangay_rider)
                                <form method="POST" action="{{ route('sc.dispatch.auto', $p) }}" style="display:flex;align-items:center;gap:8px;">@csrf
                                    <span><strong>{{ $p->barangay_rider->full_name }}</strong><div class="text-muted">{{ $p->job_kind === 'pickup' ? 'Seller\'s barangay' : ($p->area->name ?? 'Buyer\'s barangay') }}</div></span>
                                    <button class="btn btn-blue btn-sm" data-auto-one="{{ $p->tracking_number }}">Auto-assign</button>
                                </form>
                            @else
                                <span class="badge badge-red" title="No Online rider covers this barangay">No barangay rider</span>
                            @endif
                        </td>
                        <td>
                            @if ($p->isBulky())<span class="badge badge-yellow" style="margin-bottom:4px;" data-bulky>Bulky ({{ $p->bulkyReason() }}) · car or van rider</span>@endif
                            <form method="POST" action="{{ route('sc.dispatch.assign', $p) }}" style="display:flex;gap:6px;flex-wrap:wrap;">@csrf
                                <select name="rider_id" class="form-select" style="min-width:150px;" aria-label="Rider for {{ $p->tracking_number }}">
                                    @include('sc.partials.rider-options', ['bulky' => $p->isBulky()])
                                </select>
                                <button class="btn btn-green btn-sm">Assign</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">Nothing here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="card" style="margin-bottom:16px;" id="reassign">
        <div style="padding:14px 18px;font-weight:700;">Assigned, not started — can be reassigned <span class="badge badge-gray">{{ $notStarted->count() }}</span></div>
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Job</th><th>Rider</th><th>Assigned</th><th>Reassign to</th></tr></thead>
            <tbody>
            @forelse($notStarted as $j)
                <tr data-reassign="{{ $j->parcel->tracking_number }}">
                    <td>{{ $j->parcel->tracking_number }}</td>
                    <td>{{ ucfirst($j->kind) }}</td>
                    <td>{{ $j->rider->full_name ?? '—' }}</td>
                    <td>{{ $j->since?->format('M j, g:i A') }}</td>
                    <td>
                        <form method="POST" action="{{ route('sc.dispatch.reassign', $j->parcel) }}" style="display:flex;gap:6px;">@csrf
                            <select name="rider_id" class="form-select" style="min-width:150px;" aria-label="New rider for {{ $j->parcel->tracking_number }}">
                                @include('sc.partials.rider-options', ['except' => $j->rider?->id])
                            </select>
                            <button class="btn btn-blue btn-sm">Reassign</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">No jobs waiting on a rider to start.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div style="padding:14px 18px;font-weight:700;">Pickups with riders</div>
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Rider</th><th>Status</th><th>Picked up</th></tr></thead>
            <tbody>
            @forelse($withRiders as $pu)
                <tr><td>{{ $pu->parcel->tracking_number ?? '—' }}</td><td>{{ $pu->rider->full_name ?? '—' }}</td><td>{{ $pu->statusLabel() }}</td><td>{{ $pu->picked_up_at?->format('M j, g:i A') ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">No pickups in progress. Riders' parcels are received on the Shipment Board.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
