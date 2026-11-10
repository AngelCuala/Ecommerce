@extends('sc.layout')
@section('title', 'Failed Deliveries')
@section('icon', 'report')

@php
    $tz = config('app.business_timezone');
    $outcome = function ($d) {
        $p = $d->parcel;
        return match (true) {
            ! $p                                   => ['—', 'badge-gray'],
            $p->status === 'failed'                => ['Waiting for your decision', 'badge-red'],
            $p->status === 'cancelled'             => ['Order cancelled · keep the parcel for the seller', 'badge-gray'],
            in_array($p->status, ['return_to_sender', 'returned'], true) => ['Returning to the seller', 'badge-orange'],
            $p->status === 'delivered'             => ['Delivered on a later try', 'badge-green'],
            default                                => ['Rescheduled · ' . $p->statusLabel(), 'badge-blue'],
        };
    };
@endphp

@section('content')
<div class="page-header">
    <div>
        <h1>Failed Deliveries</h1>
        <p class="text-muted" style="font-size:12.5px;margin-top:2px;">Every delivery a rider could not finish, with the reason.
            <strong>Buyer not home</strong>: rescheduled automatically (up to {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }} tries).
            <strong>Wrong address</strong>: returned to the seller. <strong>Refused</strong> or the last try: the order is cancelled.</p>
    </div>
</div>
<div class="page-body">
    @if ($waiting)
        <div class="card" style="padding:12px 16px;margin-bottom:14px;background:#FEF2F2;border-color:#FCA5A5;color:#991B1B;font-size:13px;" data-failed-waiting>
            <strong>{{ $waiting }} {{ \Illuminate\Support\Str::plural('parcel', $waiting) }}</strong> failed and {{ $waiting === 1 ? 'needs' : 'need' }} your decision before moving on: Reschedule, or Cancel the order.
        </div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        <a href="{{ route('sc.failed-deliveries') }}" class="badge {{ $reason ? 'badge-gray' : 'badge-blue' }}" style="padding:6px 12px;">All · {{ $counts->sum() }}</a>
        @foreach (\App\Models\ParcelDelivery::FAILURE_REASONS as $r)
            <a href="{{ route('sc.failed-deliveries', ['reason' => $r]) }}" class="badge {{ $reason === $r ? 'badge-blue' : 'badge-gray' }}" style="padding:6px 12px;" data-reason-filter="{{ $r }}">{{ $r }} · {{ $counts[$r] ?? 0 }}</a>
        @endforeach
    </div>

    <div class="card">
        <table class="data-table">
            <thead><tr><th>Parcel</th><th>Reason</th><th>Try</th><th>Rider</th><th>When</th><th>What happens now</th></tr></thead>
            <tbody>
            @forelse ($failed as $d)
                @php [$what, $tone] = $outcome($d); $p = $d->parcel; @endphp
                <tr data-failed="{{ $d->id }}">
                    <td>
                        @if ($p)<a href="{{ route('sc.tracking.show', $p) }}" class="id-link">{{ $p->waybill_number ?: $p->tracking_number }}</a>@endif
                        <div class="text-muted text-sm">{{ $p?->receiver_name }} · {{ $p?->area?->name ?? $p?->destination_municipality }}</div>
                        <div class="text-muted text-sm">{{ $p?->order?->number() }}</div>
                    </td>
                    <td><span class="badge badge-red">{{ $d->failure_reason ?: 'Not given' }}</span>
                        @if ($d->remarks && $d->remarks !== $d->failure_reason)<div class="text-muted text-sm" style="margin-top:3px;">{{ \Illuminate\Support\Str::limit($d->remarks, 80) }}</div>@endif</td>
                    <td>{{ $d->attempt }} of {{ \App\Models\ParcelDelivery::MAX_ATTEMPTS }}</td>
                    <td>{{ $d->rider?->full_name ?? '—' }}</td>
                    <td style="white-space:nowrap;">{{ $d->updated_at?->copy()->tz($tz)->format('M j, g:i A') }}</td>
                    <td>
                        <span class="badge {{ $tone }}">{{ $what }}</span>
                        @if ($p && $p->status === 'failed' && (int) $p->current_sorting_center_id === auth()->id())
                            <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('sc.deliveries.reschedule', $d) }}">@csrf<button class="btn btn-blue btn-sm">Reschedule</button></form>
                                <form method="POST" action="{{ route('sc.deliveries.return', $d) }}" onsubmit="return confirm('Cancel the order? The parcel stays at the hub for the seller.')">@csrf<button class="btn btn-ghost btn-sm" style="color:#B91C1C;">Cancel order</button></form>
                            </div>
                        @elseif ($p)
                            @php [$pageName, $pageUrl] = \App\Support\ParcelJobPage::for($p); @endphp
                            <div style="margin-top:4px;"><a href="{{ $pageUrl }}" class="text-sm" style="font-weight:600;">{{ $pageName }} →</a></div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:28px;text-align:center;color:var(--text-muted);">No failed deliveries{{ $reason ? ' for "' . $reason . '"' : '' }}.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($failed->hasPages())<div style="padding:12px 16px;">{{ $failed->links() }}</div>@endif
    </div>
</div>
@endsection
