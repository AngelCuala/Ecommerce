@extends('admin.layouts.app')

@section('title', 'Shipment ' . $parcel->tracking_number)

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:12px;flex-wrap:wrap;">
    <div>
        <h2 style="font-size:18px;font-weight:700;">{{ $parcel->tracking_number }} <span class="badge badge--neutral">{{ $parcel->statusLabel() }}</span></h2>
        <p class="text-muted" style="font-size:12.5px;">Waybill {{ $parcel->waybill_number }} · {{ $parcel->order?->number() }} · Seller {{ $parcel->seller?->name }} · To {{ $parcel->destination_municipality }}, {{ $parcel->destination_province }}</p>
    </div>
    <a href="{{ route('admin.logistics.shipments.index') }}" class="btn btn--ghost btn--sm">All shipments</a>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:16px;align-items:start;">
    <div class="panel">
        <div class="panel__header"><h2>Full event history</h2></div>
        <table>
            <thead><tr><th>When</th><th>Event</th><th>Where / who</th><th></th></tr></thead>
            <tbody>
            @foreach ($events as $e)
                <tr data-event="{{ $e->event_type }}">
                    <td style="white-space:nowrap;font-size:12px;">{{ $e->occurred_at->format('M j, Y g:i A') }}</td>
                    <td>
                        <strong>{{ $e->label() }}</strong>@unless ($e->isPublic())<span class="badge badge--neutral" style="margin-left:4px;">internal</span>@endunless
                        @if ($e->note)<div class="text-muted" style="font-size:12px;">{{ $e->note }}</div>@endif
                        @if ($e->corrects)<div style="font-size:12px;color:var(--amber);">Corrects #{{ $e->corrects->id }} ({{ $e->corrects->label() }})</div>@endif
                        @if ($e->from_status)<div class="text-muted" style="font-size:11px;">{{ $e->from_status }} → {{ $e->to_status }}</div>@endif
                    </td>
                    <td style="font-size:12px;">{{ $e->location?->name ?? '—' }}<div class="text-muted">{{ $e->actor?->name ?? 'System' }}{{ $e->actor_role ? ' (' . $e->actor_role . ')' : '' }}</div></td>
                    <td>
                        @if ($e->event_type !== 'correction')
                        <details><summary class="btn btn--ghost btn--sm" style="list-style:none;">Correct</summary>
                            <form method="POST" action="{{ route('admin.logistics.shipments.events.correct', $e) }}" style="display:grid;gap:6px;margin-top:6px;width:220px;" onsubmit="return confirm('Record a correction? The original event is kept.')">
                                @csrf
                                <textarea name="reason" required minlength="10" maxlength="500" rows="2" placeholder="What was wrong and what is right"></textarea>
                                <button class="btn btn--amber btn--sm">Record correction</button>
                            </form>
                        </details>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="panel">
            <div class="panel__header"><h2>Exceptions</h2></div>
            <div style="padding:14px 16px;display:grid;gap:12px;">
                @forelse ($parcel->exceptions->sortByDesc('id') as $x)
                    <div style="border:1px solid var(--border);border-radius:8px;padding:10px;" data-exception="{{ $x->id }}">
                        <strong>{{ $x->typeLabel() }}</strong> <span class="badge {{ $x->isOpen() ? 'badge--rejected' : 'badge--approved' }}">{{ $x->isOpen() ? 'Open' : 'Resolved' }}</span>
                        <p class="text-muted" style="font-size:12px;margin-top:4px;">{{ $x->details }}</p>
                        <p class="text-muted" style="font-size:11px;">{{ $x->sortingCenter?->name }} · {{ $x->created_at->format('M j, g:i A') }}</p>
                        @if ($x->isOpen())
                            <form method="POST" action="{{ route('admin.logistics.shipments.exceptions.resolve', $x) }}" style="display:grid;gap:6px;margin-top:8px;" onsubmit="return confirm('Resolve this exception?')">
                                @csrf
                                <select name="action" required>
                                    <option value="note">Resolve with a note only</option>
                                    <option value="custody">The parcel is physically at…</option>
                                    <option value="lost">Declare the parcel lost</option>
                                </select>
                                <select name="center_id"><option value="">— Sorting center (for "physically at") —</option>@foreach ($hubs as $h)<option value="{{ $h->id }}">{{ $h->name }} — {{ $h->assigned_municipality }}</option>@endforeach</select>
                                <textarea name="reason" required minlength="10" maxlength="500" rows="2" placeholder="Reason (required)"></textarea>
                                <button class="btn btn--blue btn--sm">Resolve</button>
                            </form>
                        @else
                            <p style="font-size:12px;margin-top:4px;">Resolution: {{ $x->resolution }} — {{ $x->resolver?->name }}, {{ $x->resolved_at?->format('M j, g:i A') }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-muted" style="font-size:13px;">No exceptions.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="panel__header"><h2>Transfers, pickups &amp; deliveries</h2></div>
            <div style="padding:14px 16px;display:grid;gap:6px;font-size:12.5px;">
                @foreach ($parcel->transfers->sortBy('id') as $t)
                    <div>{{ $t->reference }}: {{ \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }} → {{ \App\Support\SortingCenters::nameOf($t->toSortingCenter) }} · <strong>{{ $t->statusLabel() }}</strong>{{ $t->exception_reason ? ' — ' . $t->exception_reason : '' }}</div>
                @endforeach
                @foreach ($parcel->pickups->sortBy('id') as $pu)
                    <div>Pickup: {{ $pu->rider?->full_name }} · {{ $pu->statusLabel() }}{{ $pu->remarks ? ' (' . $pu->remarks . ')' : '' }}</div>
                @endforeach
                @foreach ($parcel->deliveries->sortBy('id') as $d)
                    <div>Delivery attempt {{ $d->attempt }}: {{ $d->rider?->full_name }} · {{ $d->statusLabel() }}{{ $d->failure_reason ? ' (' . $d->failure_reason . ')' : '' }}{{ $d->remarks && $d->status === 'declined' ? ' (' . $d->remarks . ')' : '' }}</div>
                @endforeach
                <p class="text-muted" style="margin-top:6px;">Reassign a delivery from the sorting center's Rider Dispatch, or the Delivery Assignment page.</p>
            </div>
        </div>
    </div>
</div>
@endsection
