@extends('sc.layout')
@section('title', 'Return Items')
@section('icon', 'report')

@php
    $tabs = \App\Http\Controllers\SortingCenter\ReturnsController::TABS;
    $tz = config('app.business_timezone');
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Return Items</h1>
        <p class="text-muted" style="font-size:12.5px;margin-top:2px;max-width:900px;">A buyer is returning an order. A rider collects it (photo + waybill scan at the buyer).
            At the hub, like any parcel: <b>1 Sort</b> (where it goes next) → <b>2 Check</b> (its condition) → <b>3 Scan</b> the waybill for the record →
            <b>4 Send</b>: a rider to the seller, or a hub-to-hub trip when the seller is in another town (that hub does the same steps).</p>
    </div>
</div>
<div class="page-body">
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        @foreach ($tabs as $key => [$label])
            <a href="{{ route('sc.returns', ['tab' => $key]) }}" class="badge {{ $tab === $key ? 'badge-blue' : 'badge-gray' }}" style="padding:6px 12px;" data-tab="{{ $key }}">{{ $label }} · {{ $counts[$key] }}</a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead><tr><th>Order</th><th>Buyer → seller</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($items as $rs)
                <tr data-return="{{ $rs->id }}">
                    <td>
                        <a href="{{ route('sc.returns.show', $rs) }}" class="id-link">{{ $rs->order?->number() }}</a>
                        <div class="text-muted text-sm">Waybill {{ $rs->code() }}</div>
                        @if ($rs->parcel?->isBulky())<span class="badge badge-yellow" data-bulky>Bulky · car or van rider</span>@endif
                    </td>
                    <td style="font-size:12.5px;max-width:300px;">
                        <div><strong>{{ $rs->order?->full_name }}</strong> · {{ \Illuminate\Support\Str::limit($rs->buyerAddress(), 70) }}</div>
                        <div class="text-muted">→ {{ $rs->parcel?->seller?->storeName() ?? $rs->parcel?->seller?->name ?? 'Seller' }} · {{ \Illuminate\Support\Str::limit($rs->sellerAddress(), 60) }}</div>
                    </td>
                    <td style="font-size:12.5px;">{{ $rs->returnRequest?->reason }}
                        <div class="text-muted text-sm">{{ $rs->returnRequest?->statusLabel() }}</div></td>
                    <td>
                        <span class="badge {{ in_array($rs->status, ['returned'], true) ? 'badge-green' : ($rs->status === 'cancelled' ? 'badge-gray' : 'badge-orange') }}">{{ $rs->statusLabel() }}</span>
                        @if ($rs->pickupRider)<div class="text-muted text-sm">Pickup: {{ $rs->pickupRider->full_name }}{{ $rs->status === 'pickup_assigned' ? ($rs->acknowledged_at ? ' · accepted' : ' · not accepted yet') : '' }}</div>@endif
                        @if ($rs->sellerRider)<div class="text-muted text-sm">To seller: {{ $rs->sellerRider->full_name }}</div>@endif
                        @if ($rs->pickup_proof_path)<a href="{{ \App\Support\ProtectedFile::url($rs->pickup_proof_path) }}" target="_blank" rel="noopener" class="text-sm">Proof of pickup</a>@endif
                        <div class="text-muted text-sm">{{ $rs->updated_at?->copy()->tz($tz)->format('M j, g:i A') }}</div>
                    </td>
                    <td style="min-width:220px;">
                        @if (in_array($rs->status, ['requested', 'pickup_assigned'], true))
                            <form method="POST" action="{{ route('sc.returns.assign', $rs) }}" style="display:flex;gap:4px;">@csrf
                                <select name="rider_id" class="form-select" style="flex:1;padding:5px 8px;font-size:12px;" required aria-label="Rider to collect the return">
                                    @include('sc.partials.rider-options', ['placeholder' => $rs->pickup_rider_id ? 'Change rider…' : 'Assign rider…', 'except' => $rs->pickup_rider_id, 'bulky' => (bool) $rs->parcel?->isBulky()])
                                </select>
                                <button class="btn btn-green btn-sm">{{ $rs->pickup_rider_id ? 'Change' : 'Assign' }}</button>
                            </form>
                        @elseif ($rs->status === 'at_hub' && $rs->trip)
                            <a href="{{ route('sc.trips.show', $rs->trip) }}" class="text-sm">On {{ $rs->trip->code }} to {{ $rs->nextHub?->code }} · waiting to leave</a>
                        @elseif (in_array($rs->status, ['picked_up', 'at_hub'], true))
                            @php $stepNames = ['sort' => '1 · Sort', 'check' => '2 · Check', 'scan' => '3 · Scan', 'send' => '4 · Send']; @endphp
                            <a href="{{ route('sc.returns.show', $rs) }}" class="btn btn-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;" data-keep-colors data-process-return>
                                {{ $rs->status === 'picked_up' ? 'It arrived · process it' : 'Process · ' . $stepNames[$rs->stepAtHub()] }}</a>
                            @if ($rs->next_stop)<div class="text-muted text-sm" style="margin-top:4px;">Next: {{ $rs->next_stop === 'hub' ? ($rs->nextHub?->code ?? 'next hub') : 'the seller' }}</div>@endif
                        @elseif ($rs->status === 'in_transit')
                            <a href="{{ $rs->trip ? route('sc.trips.show', $rs->trip) : '#' }}" class="text-sm">On {{ $rs->trip?->code ?? 'a trip' }} to {{ $rs->nextHub?->code }}</a>
                        @elseif ($rs->status === 'to_seller')
                            <form method="POST" action="{{ route('sc.returns.to-seller', $rs) }}" style="display:flex;gap:4px;">@csrf
                                <select name="rider_id" class="form-select" style="flex:1;padding:5px 8px;font-size:12px;" required aria-label="Rider to the seller">
                                    @include('sc.partials.rider-options', ['placeholder' => $rs->seller_rider_id ? 'Change rider…' : 'Rider to the seller…', 'except' => $rs->seller_rider_id, 'bulky' => (bool) $rs->parcel?->isBulky()])
                                </select>
                                <button class="btn btn-green btn-sm">{{ $rs->seller_rider_id ? 'Change' : 'Assign' }}</button>
                            </form>
                        @else
                            <span class="text-muted text-sm">{{ $rs->status === 'cancelled' ? ($rs->cancel_reason ?: 'Cancelled') : ($rs->status === 'missing' ? 'Missing in transit' : 'Returned ' . $rs->returned_at?->copy()->tz($tz)->format('M j, g:i A')) }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:28px;text-align:center;color:var(--text-muted);">No returns here.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($items->hasPages())<div style="padding:12px 16px;">{{ $items->links() }}</div>@endif
    </div>
</div>
@endsection
