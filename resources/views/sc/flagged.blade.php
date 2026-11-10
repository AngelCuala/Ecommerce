@extends('sc.layout')
@section('title', 'Flagged Parcels')
@section('icon', 'report')

@php $tz = config('app.business_timezone'); @endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Flagged Parcels</h1>
        <p class="text-muted" style="font-size:12.5px;margin-top:2px;">Parcels flagged at the hub (damaged, missing, wrong sorting center…). A flagged parcel is
            <strong>on hold</strong>: it can't be received, sorted, assigned, transferred or delivered until an administrator resolves the flag.
            To flag a parcel, open it from the Shipment Board and use Flag shipment exception.</p>
    </div>
</div>
<div class="page-body">
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>On hold</h2><span class="card-meta">{{ $open->count() }} {{ \Illuminate\Support\Str::plural('parcel', $open->count()) }}</span></div>
        <table class="data-table">
            <thead><tr><th>Parcel</th><th>Problem</th><th>Flagged</th><th>Parcel status</th></tr></thead>
            <tbody>
            @forelse ($open as $x)
                @php $noHold = in_array($x->type, \App\Models\Parcel::HOLD_EXCEPTIONS_EXCEPT, true); @endphp
                <tr data-flag="{{ $x->id }}">
                    <td>
                        @if ($x->parcel)<a href="{{ route('sc.tracking.show', $x->parcel) }}" class="id-link">{{ $x->parcel->waybill_number ?: $x->parcel->tracking_number }}</a>
                            <div class="text-muted text-sm">{{ $x->parcel->order?->number() }} · {{ $x->parcel->seller?->storeName() ?? $x->parcel->seller?->name }}</div>@endif
                    </td>
                    <td>
                        <span class="badge {{ $noHold ? 'badge-orange' : 'badge-red' }}">{{ $x->typeLabel() }}</span>
                        <div class="text-sm" style="margin-top:4px;max-width:420px;">{{ $x->details }}</div>
                        @if ($noHold)<div class="text-muted text-sm">Not on hold: receiving the transfer clears it.</div>@endif
                    </td>
                    <td style="white-space:nowrap;">{{ $x->created_at?->copy()->tz($tz)->format('M j, g:i A') }}<div class="text-muted text-sm">{{ $x->reporter?->name ?? 'System' }}</div></td>
                    <td>{{ $x->parcel?->statusLabel() ?? '—' }}<div class="text-muted text-sm">Waiting for an administrator</div></td>
                </tr>
            @empty
                <tr><td colspan="4" style="padding:28px;text-align:center;color:var(--accent-green);font-weight:600;">No flagged parcels.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($resolved->isNotEmpty())
    <div class="card">
        <div class="card-header"><h2>Recently resolved</h2></div>
        <table class="data-table">
            <thead><tr><th>Parcel</th><th>Problem</th><th>Resolution</th><th>When</th></tr></thead>
            <tbody>
            @foreach ($resolved as $x)
                <tr>
                    <td>@if ($x->parcel)<a href="{{ route('sc.tracking.show', $x->parcel) }}" class="id-link">{{ $x->parcel->waybill_number ?: $x->parcel->tracking_number }}</a>@endif</td>
                    <td>{{ $x->typeLabel() }}</td>
                    <td>{{ $x->resolution }}<div class="text-muted text-sm">{{ $x->resolver?->name }}</div></td>
                    <td style="white-space:nowrap;">{{ $x->resolved_at?->copy()->tz($tz)->format('M j, g:i A') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
