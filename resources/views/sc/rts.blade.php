@extends('sc.layout')
@section('title', 'Return to Sender')
@section('icon', 'box')

@section('content')
<div class="page-header"><h1>Return to Sender</h1></div>
<div class="page-body">
    <p class="text-muted" style="margin:-4px 0 16px;font-size:13px;">
        Parcels that failed {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }} delivery attempts (or were refused) go back to the seller.
        Assign a rider to bring each one back; the seller then confirms receipt and the stock returns.
    </p>

    <div class="card" style="margin-bottom:16px;">
        <div style="padding:14px 18px;font-weight:700;">Waiting to go back <span class="badge badge-gray">{{ $parcels->count() }}</span></div>
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Seller</th><th>Failed attempts</th><th>Rider</th></tr></thead>
            <tbody>
            @forelse($parcels as $p)
                <tr data-rts="{{ $p->tracking_number }}">
                    <td><span class="id-link">{{ $p->tracking_number }}</span><div class="text-muted text-sm">{{ $p->statusLabel() }}</div></td>
                    <td style="font-size:12px;">{{ $p->seller->name ?? '—' }}<div class="text-muted">{{ $p->pickup_address }}</div></td>
                    <td style="font-size:12px;">
                        @foreach($p->deliveries as $d)
                            <div>Attempt {{ $d->attempt }}: {{ $d->failure_reason ?: $d->remarks ?: $d->statusLabel() }}</div>
                        @endforeach
                    </td>
                    <td>
                        @if($p->rtsRider)
                            <div style="margin-bottom:4px;"><span class="badge badge-blue">{{ $p->rtsRider->full_name }}</span>
                                <span class="text-muted text-sm">since {{ $p->rts_assigned_at?->format('M j, g:i A') }}</span></div>
                        @endif
                        <form method="POST" action="{{ route('sc.rts.assign', $p) }}" style="display:flex;gap:6px;">@csrf
                            <select name="rider_id" class="form-select" aria-label="Rider for {{ $p->tracking_number }}">
                                <option value="">{{ $p->rtsRider ? 'Change rider…' : 'Choose rider…' }}</option>
                                @foreach($riders as $r)@if($r->id !== $p->rts_rider_id)<option value="{{ $r->id }}">{{ $r->full_name }}</option>@endif @endforeach
                            </select>
                            <button class="btn btn-green btn-sm">{{ $p->rtsRider ? 'Reassign' : 'Assign' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">Nothing to return.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div style="padding:14px 18px;font-weight:700;">Returned — awaiting the seller's confirmation <span class="badge badge-gray">{{ $awaiting->count() }}</span></div>
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Seller</th><th>Handed back</th></tr></thead>
            <tbody>
            @forelse($awaiting as $p)
                <tr><td>{{ $p->tracking_number }}</td><td>{{ $p->seller->name ?? '—' }}</td><td>{{ $p->returned_at?->format('M j, g:i A') }}</td></tr>
            @empty
                <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text-muted);">None.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
