@extends('sc.layout')
@section('title', 'SC Transfers')
@section('icon', '🔄')

@section('content')
<div class="page-header"><h1>Sorting Center Transfers</h1></div>
<div class="page-body">

    {{-- ── Stats row ──────────────────────────────────────────── --}}
    <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon">📤</div>
            <div class="stat-value">{{ $outgoing->count() }}</div>
            <div class="stat-label">Outgoing (Pending)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📥</div>
            <div class="stat-value">{{ $incoming->count() }}</div>
            <div class="stat-label">Incoming (Awaiting)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-value">{{ $history->count() }}</div>
            <div class="stat-label">Completed / History</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        {{-- ══════════════════════════════════════════════════════
             LEFT — Initiate a new transfer
        ══════════════════════════════════════════════════════ --}}
        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Send Parcel to Another Sorting Center</h2></div>
                <div style="padding:18px;">
                    @if($myParcels->isEmpty())
                        <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px 0;">
                            No parcels available for transfer.<br>
                            <small>Only parcels you currently hold (picked up / sorted) can be transferred.</small>
                        </p>
                    @else
                        <form action="{{ route('sc.transfers.initiate') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Select Parcel</label>
                                <select name="parcel_id" required
                                        style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;background:#fff;">
                                    <option value="">— Choose a parcel —</option>
                                    @foreach($myParcels as $p)
                                        <option value="{{ $p->id }}">
                                            {{ $p->tracking_number }} — {{ $p->receiver_name }} ({{ ucwords(str_replace('_',' ',$p->status)) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Destination Sorting Center</label>
                                <select name="to_sorting_center_id" required
                                        style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;background:#fff;">
                                    <option value="">— Select destination —</option>
                                    @foreach($otherCenters as $sc)
                                        <option value="{{ $sc->id }}">{{ $sc->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Reason (optional)</label>
                                <textarea name="reason" rows="2"
                                          placeholder="e.g. Closer to delivery area, overflow…"
                                          style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width:100%;">
                                📤 Initiate Transfer
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Outgoing pending transfers --}}
            @if($outgoing->isNotEmpty())
            <div class="card">
                <div class="card-header"><h2>Outgoing — Awaiting Acceptance</h2></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>To</th>
                            <th>Reason</th>
                            <th>Sent</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($outgoing as $t)
                        <tr>
                            <td><span class="id-link">{{ $t->parcel->tracking_number }}</span></td>
                            <td>{{ $t->toSortingCenter->name }}</td>
                            <td style="color:var(--text-muted);font-size:12px;">{{ $t->reason ?: '—' }}</td>
                            <td style="color:var(--text-muted);font-size:12px;">{{ $t->created_at->diffForHumans() }}</td>
                            <td>
                                <form action="{{ route('sc.transfers.cancel', $t->id) }}" method="POST"
                                      onsubmit="return confirm('Cancel this transfer?')">
                                    @csrf
                                    <button class="btn btn-sm btn-ghost" style="color:#DC2626;border-color:#DC2626;">Cancel</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════
             RIGHT — Incoming transfers + history
        ══════════════════════════════════════════════════════ --}}
        <div>

            {{-- Incoming transfers waiting for acceptance --}}
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h2>Incoming Transfers</h2>
                    @if($incoming->isNotEmpty())
                        <span class="badge badge-orange">{{ $incoming->count() }} pending</span>
                    @endif
                </div>
                @if($incoming->isEmpty())
                    <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">
                        No incoming transfers right now.
                    </div>
                @else
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>From</th>
                                <th>Receiver</th>
                                <th>Reason</th>
                                <th>Sent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($incoming as $t)
                            <tr>
                                <td><span class="id-link">{{ $t->parcel->tracking_number }}</span></td>
                                <td>{{ $t->fromSortingCenter->name }}</td>
                                <td style="font-size:12px;">{{ $t->parcel->receiver_name }}</td>
                                <td style="color:var(--text-muted);font-size:12px;">{{ $t->reason ?: '—' }}</td>
                                <td style="color:var(--text-muted);font-size:12px;">{{ $t->created_at->diffForHumans() }}</td>
                                <td style="display:flex;gap:6px;">
                                    <form action="{{ route('sc.transfers.accept', $t->id) }}" method="POST"
                                          onsubmit="return confirm('Accept this parcel from {{ $t->fromSortingCenter->name }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-primary">✓ Accept</button>
                                    </form>
                                    <form action="{{ route('sc.transfers.reject', $t->id) }}" method="POST"
                                          onsubmit="return confirm('Reject this transfer?')">
                                        @csrf
                                        <button class="btn btn-sm btn-ghost" style="color:#DC2626;border-color:#DC2626;">✕ Reject</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Transfer history --}}
            <div class="card">
                <div class="card-header"><h2>Transfer History (last 30)</h2></div>
                @if($history->isEmpty())
                    <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No transfer history yet.</div>
                @else
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Direction</th>
                                <th>Other SC</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $t)
                            @php
                                $isFrom = $t->from_sorting_center_id === auth()->id();
                                $direction = $isFrom ? 'Sent to' : 'Received from';
                                $otherSc   = $isFrom ? $t->toSortingCenter->name : $t->fromSortingCenter->name;
                                $badgeClass = match($t->status) {
                                    'received' => 'badge-green',
                                    'rejected' => 'badge-red',
                                    default    => 'badge-gray',
                                };
                            @endphp
                            <tr>
                                <td><span class="id-link">{{ $t->parcel->tracking_number }}</span></td>
                                <td style="font-size:12px;color:var(--text-muted);">{{ $direction }}</td>
                                <td style="font-size:13px;">{{ $otherSc }}</td>
                                <td><span class="badge {{ $badgeClass }}">{{ ucfirst($t->status) }}</span></td>
                                <td style="color:var(--text-muted);font-size:12px;">
                                    {{ ($t->received_at ?? $t->updated_at)->format('M d, Y') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
