@extends('sc.layout')
@section('title', 'Remittance ' . $r->reference())
@section('icon', 'money')

@php
    $tz = config('app.business_timezone');
    $peso = fn ($v) => ((float) $v < 0 ? '−' : '') . '₱' . number_format(abs((float) $v), 2);
@endphp

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('sc.alvy-remittances') }}" class="text-muted" style="font-size:12.5px;">← ALVY Remittance</a>
        <h1>{{ $r->reference() }}</h1>
        <p class="text-muted" style="font-size:12.5px;margin-top:2px;">Sent {{ $r->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}@if ($r->submitter) by {{ $r->submitter->name }}@endif
            · {{ $r->amount >= 0 ? 'To ALVY ' . $peso($r->amount) : 'ALVY owes you ' . $peso(-$r->amount) }}@if ($r->deposit_reference) · deposit ref {{ $r->deposit_reference }}@endif</p>
    </div>
</div>
<div class="page-body">
    <div class="card" style="margin-bottom:16px;padding:14px 16px;">
        <strong>{{ $r->statusLabel() }}</strong>
        @if ($r->confirmed_at) · checked {{ $r->confirmed_at->copy()->tz($tz)->format('M j, g:i A') }}@if ($r->confirmer) by {{ $r->confirmer->name }}@endif · received {{ $peso($r->received_amount) }}@endif
        @if ($r->note)<div style="color:#B91C1C;margin-top:4px;">{{ $r->note }}</div>@endif
    </div>
    <div class="card">
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Paid by</th><th>COD expected</th><th>COD collected</th><th>Shipping fee</th><th>Due to ALVY</th></tr></thead>
            <tbody>
            @foreach ($r->items as $i)
                <tr @if ($i->mismatch) style="background:#FEF2F2;" @endif>
                    <td><strong>{{ $i->tracking_number }}</strong>@if ($i->mismatch)<div style="color:#B91C1C;font-size:11.5px;">Doesn't match the order</div>@endif</td>
                    <td>{{ $i->cod ? 'COD' : 'Prepaid' }}</td>
                    <td>{{ $i->cod ? $peso($i->cod_expected) : '—' }}</td>
                    <td>{{ $i->cod ? $peso($i->cod_collected) : '—' }}</td>
                    <td>{{ $peso($i->shipping_fee) }}</td>
                    <td style="font-weight:700;">{{ $peso($i->amount_due) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
