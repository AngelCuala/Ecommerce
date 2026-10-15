@extends('sc.layout')
@section('title', 'COD Remittances')
@section('icon', 'rider')

@section('content')
<div class="page-header"><h1>COD Remittances</h1></div>
<div class="page-body">
    <div class="card" style="margin-bottom:16px;">
        <div style="padding:14px 18px;font-weight:700;">COD per rider</div>
        <table class="data-table">
            <thead><tr><th>Rider</th><th>Collected</th><th>Pending remittance</th><th>Outstanding</th></tr></thead>
            <tbody>
            @forelse($riders as $row)
                <tr data-rider-cod="{{ $row['rider']->full_name }}">
                    <td>{{ $row['rider']->full_name }}</td>
                    <td>₱{{ number_format($row['collected'], 2) }}</td>
                    <td>₱{{ number_format($row['pending'], 2) }}</td>
                    <td>@if($row['balance'] > 0)<span class="badge badge-yellow">₱{{ number_format($row['balance'], 2) }}</span>@else ₱0.00 @endif</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-muted);">No riders yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div style="padding:14px 18px;font-weight:700;">Remittances</div>
        <table class="data-table">
            <thead><tr><th>Sent</th><th>Rider</th><th>Amount</th><th>COD balance then</th><th>Reconciliation</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($remittances as $rm)
                <tr data-remittance="{{ $rm->id }}">
                    <td>{{ $rm->created_at->format('M j, g:i A') }}</td>
                    <td>{{ $rm->rider->full_name ?? '—' }}</td>
                    <td>₱{{ number_format($rm->amount, 2) }}</td>
                    <td>₱{{ number_format($rm->expected_amount, 2) }}</td>
                    <td>@if($rm->shortfall() > 0)<span class="badge badge-red" data-shortfall>Short ₱{{ number_format($rm->shortfall(), 2) }}</span>@else <span class="badge badge-green">Matches</span>@endif</td>
                    <td>
                        @if($rm->status === 'pending')
                            <form method="POST" action="{{ route('sc.remittances.confirm', $rm) }}">@csrf<button class="btn btn-green btn-sm">Confirm received</button></form>
                        @else
                            Confirmed {{ $rm->confirmed_at?->format('M j, g:i A') }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">No remittances yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:12px;">{{ $remittances->links() }}</div>
</div>
@endsection
