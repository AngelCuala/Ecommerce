@extends('sc.layout')
@section('title', 'Rider Dispatch')
@section('icon', 'rider')

@section('content')
<div class="page-header"><h1>Rider Dispatch</h1></div>
<div class="page-body">
    <p class="text-muted" style="margin:-4px 0 16px;font-size:13px;">
        Auto-assign offers a job to the nearest Online rider with fewer than {{ \App\Services\RiderDispatch::MAX_ACTIVE }} active jobs.
        An offer expires after {{ \App\Services\RiderDispatch::OFFER_SECONDS }} seconds and goes to the next rider; after {{ \App\Services\RiderDispatch::MAX_OFFERS }} riders it waits in the Unassigned queue.
    </p>

    {{-- Riders --}}
    <div class="card" style="padding:14px 18px;margin-bottom:16px;">
        <div style="font-weight:700;margin-bottom:8px;">Riders</div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @forelse($riders as $r)
                <span class="badge {{ $r->isSuspended() ? 'badge-red' : ($r->online_now ? 'badge-green' : 'badge-gray') }}" data-rider="{{ $r->full_name }}">
                    {{ $r->full_name }} · {{ $r->isSuspended() ? 'Suspended' : ($r->online_now ? 'Online' : 'Offline') }} · {{ $r->active_jobs }}/{{ \App\Services\RiderDispatch::MAX_ACTIVE }} jobs
                </span>
            @empty
                <span class="text-muted">No approved riders yet.</span>
            @endforelse
        </div>
    </div>

    @foreach (['Waiting for a rider' => $jobs, 'Unassigned queue — assign manually' => $unassigned] as $heading => $list)
        <div class="card" style="margin-bottom:16px;" @if($loop->last) id="unassigned" @endif>
            <div style="padding:14px 18px;font-weight:700;">{{ $heading }} <span class="badge badge-gray">{{ $list->count() }}</span></div>
            <table class="data-table">
                <thead><tr><th>Tracking</th><th>Job</th><th>From</th><th>To</th><th>COD</th><th>Offer</th><th>Assign</th></tr></thead>
                <tbody>
                @forelse($list as $p)
                    <tr data-job="{{ $p->tracking_number }}">
                        <td><span class="id-link">{{ $p->tracking_number }}</span></td>
                        <td>{{ ucfirst($p->job_kind) }}{{ $p->job_kind === 'delivery' ? ' · attempt ' . ($p->deliveries()->count() + 1) : '' }}</td>
                        <td style="font-size:12px;">{{ $p->seller->name ?? '—' }}<div class="text-muted">{{ \Illuminate\Support\Str::limit($p->pickup_address, 40) }}</div></td>
                        <td style="font-size:12px;">{{ $p->receiver_name }}<div class="text-muted">{{ $p->area->name ?? $p->destination_municipality }}</div></td>
                        <td>{{ $p->order && strtoupper($p->order->payment_method) === 'COD' && $p->order->payment_status !== 'Paid' ? '₱' . number_format($p->order->total_price, 2) : 'Paid' }}</td>
                        <td style="font-size:12px;">
                            @if($p->job_offer)
                                Offered to <strong>{{ $p->job_offer->rider->full_name }}</strong><div class="text-muted">{{ $p->job_offer->secondsLeft() }}s left</div>
                            @elseif($p->unassigned)
                                <span class="badge badge-red">Unassigned</span>
                            @else
                                <form method="POST" action="{{ route('sc.dispatch.auto', $p) }}">@csrf<button class="btn btn-blue btn-sm">Auto-assign</button></form>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('sc.dispatch.assign', $p) }}" style="display:flex;gap:6px;">@csrf
                                <select name="rider_id" class="form-select" style="min-width:150px;" aria-label="Rider for {{ $p->tracking_number }}">
                                    <option value="">Choose rider…</option>
                                    @foreach($eligible as $r)<option value="{{ $r->id }}">{{ $r->full_name }} ({{ $r->activeJobsCount() }} jobs)</option>@endforeach
                                </select>
                                <button class="btn btn-green btn-sm">Assign</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">Nothing here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="card">
        <div style="padding:14px 18px;font-weight:700;">Pickups with riders</div>
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Rider</th><th>Status</th><th>Picked up</th></tr></thead>
            <tbody>
            @forelse($withRiders as $pu)
                <tr><td>{{ $pu->parcel->tracking_number ?? '—' }}</td><td>{{ $pu->rider->full_name ?? '—' }}</td><td>{{ $pu->statusLabel() }}</td><td>{{ $pu->picked_up_at?->format('M j, g:i A') ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">No pickups in progress. Riders' parcels are received under Incoming Parcels.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
