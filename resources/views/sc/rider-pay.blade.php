@extends('sc.layout')
@section('title', 'Rider Pay')
@section('icon', 'rider')

@php
    $tz = config('app.business_timezone');
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $pickup = \App\Services\RiderDispatch::PAY_PICKUP;
    $delivery = \App\Services\RiderDispatch::PAY_DELIVERY;
    $total = $riders->sum('to_send');
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Rider Pay</h1>
        <p class="text-muted" style="font-size:12.5px;margin-top:2px;">Each rider's share of the shipping fee ({{ $peso($pickup) }} a pickup, {{ $peso($delivery) }} a delivery;
            nothing for a failed attempt). Press <strong>Send pay</strong> to put it in the rider's e-wallet. Riders now remit all the COD they collect;
            their pay is no longer kept out of it.</p>
    </div>
</div>
<div class="page-body">
    {{-- GCash cash-outs: send the money from your GCash, then record the reference. --}}
    <div class="card" style="margin-bottom:16px;" id="cashouts">
        <div class="card-header"><h2>GCash cash-out requests</h2><span class="card-meta">{{ $cashouts->count() }} waiting</span></div>
        <table class="data-table">
            <thead><tr><th>Requested</th><th>Rider</th><th>Amount</th><th>Send to GCash</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($cashouts as $c)
                <tr data-cashout="{{ $c->id }}">
                    <td style="white-space:nowrap;">{{ $c->created_at->copy()->tz($tz)->format('M j, g:i A') }}</td>
                    <td><strong>{{ $c->rider?->full_name }}</strong><div class="text-muted text-sm">{{ $c->rider?->phone }}</div></td>
                    <td><strong style="color:var(--accent-blue-d);">{{ $peso(abs((float) $c->amount)) }}</strong></td>
                    <td><strong style="font-family:monospace;font-size:14px;">{{ $c->gcash_number }}</strong><div class="text-sm">{{ $c->gcash_name }}</div></td>
                    <td style="min-width:240px;">
                        <form method="POST" action="{{ route('sc.rider-pay.cashout.paid', $c) }}" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;">
                            @csrf
                            <input name="reference" class="form-input" placeholder="GCash reference no." required minlength="6" maxlength="60" style="flex:1;min-width:150px;" aria-label="GCash reference number">
                            <button class="btn btn-green btn-sm" type="submit">Mark sent</button>
                        </form>
                        <details>
                            <summary class="text-sm" style="cursor:pointer;color:#B91C1C;">Reject…</summary>
                            <form method="POST" action="{{ route('sc.rider-pay.cashout.reject', $c) }}" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                                @csrf
                                <input name="reason" class="form-input" placeholder="Why (e.g. GCash number not found)" required minlength="5" maxlength="300" style="flex:1;min-width:150px;" aria-label="Reason">
                                <button class="btn btn-red btn-sm" type="submit">Reject</button>
                            </form>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--text-muted);">No cash-out requests.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($cashoutsDone->isNotEmpty())
            <details style="padding:10px 16px;border-top:1px solid var(--border);">
                <summary class="text-sm" style="cursor:pointer;font-weight:600;">Recent cash-outs</summary>
                @foreach ($cashoutsDone as $c)
                    <div class="text-sm" style="padding:4px 0;">{{ $c->processed_at?->copy()->tz($tz)->format('M j, g:i A') }} · {{ $c->rider?->full_name }} · {{ $peso(abs((float) $c->amount)) }} ·
                        @if ($c->status === 'paid')<span style="color:var(--accent-green);font-weight:600;">Sent</span> (ref {{ $c->reference }})@else<span style="color:#B91C1C;font-weight:600;">Rejected</span>: {{ $c->reject_reason }}@endif
                    </div>
                @endforeach
            </details>
        @endif
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>Waiting to be sent</h2><span class="card-meta">{{ $peso($total) }} in all</span></div>
        <table class="data-table">
            <thead><tr><th>Rider</th><th>Jobs not sent yet</th><th>To send</th><th>In wallet</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($riders as $row)
                @php $r = $row['rider']; $jobs = $row['jobs']; @endphp
                <tr data-rider="{{ $r->id }}">
                    <td><strong>{{ $r->full_name }}</strong><div class="text-muted text-sm">{{ $r->vehicle_type }} · {{ $r->phone }}</div></td>
                    <td>
                        @if ($jobs->isEmpty())
                            <span class="text-muted">None</span>
                        @else
                            {{ $jobs->where('type', 'Pickup')->count() }} {{ \Illuminate\Support\Str::plural('pickup', $jobs->where('type', 'Pickup')->count()) }},
                            {{ $jobs->where('type', 'Delivery')->count() }} {{ \Illuminate\Support\Str::plural('delivery', $jobs->where('type', 'Delivery')->count()) }}
                            <details style="margin-top:4px;"><summary class="text-sm" style="cursor:pointer;color:var(--accent);">See jobs</summary>
                                @foreach ($jobs as $j)
                                    <div class="text-sm">{{ $j['at']->copy()->tz($tz)->format('M j, g:i A') }} · {{ $j['type'] }} · {{ $j['tracking'] }} · {{ $peso($j['pay']) }}</div>
                                @endforeach
                            </details>
                        @endif
                    </td>
                    <td><strong style="{{ $row['to_send'] > 0 ? 'color:var(--accent-blue-d);' : '' }}">{{ $peso($row['to_send']) }}</strong></td>
                    <td>{{ $peso($row['balance']) }}</td>
                    <td>
                        @if ($row['to_send'] > 0)
                            <form method="POST" action="{{ route('sc.rider-pay.send', $r) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Send ' . $peso($row['to_send']) . ' to the wallet of ' . $r->full_name . '?') }});">
                                @csrf
                                <button class="btn btn-blue btn-sm" type="submit">Send pay</button>
                            </form>
                        @else
                            <span class="text-muted text-sm">Nothing to send</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:28px;text-align:center;color:var(--text-muted);">No approved riders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><h2>Sent</h2></div>
        <table class="data-table">
            <thead><tr><th>When</th><th>Rider</th><th>Amount</th><th>Jobs</th><th>Sent by</th></tr></thead>
            <tbody>
            @forelse ($sent as $e)
                <tr>
                    <td style="white-space:nowrap;">{{ $e->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}</td>
                    <td>{{ $e->rider?->full_name }}</td>
                    <td><strong>{{ $peso($e->amount) }}</strong></td>
                    <td>{{ $e->jobs }}</td>
                    <td>{{ $e->sender?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--text-muted);">No pay sent yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($sent->hasPages())<div style="padding:12px 16px;">{{ $sent->links() }}</div>@endif
    </div>
</div>
@endsection
