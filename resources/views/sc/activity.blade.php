@extends('sc.layout')
@section('title', 'Activity Log')
@section('icon', 'list')

@section('content')
<div class="page-header"><h1>Activity Log</h1></div>
<div class="page-body">
    <p class="text-muted" style="margin:-4px 0 16px;font-size:13px;">
        Every override, rider approval or suspension, rate change and remittance confirmation by this account. Entries can't be edited or deleted.
    </p>
    <div class="card">
        <table class="data-table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Change</th><th>Reason</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr data-log="{{ $log->id }}">
                    <td style="white-space:nowrap;">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $log->admin_name }}</td>
                    <td>{{ $log->description ?: $log->action_label }}</td>
                    <td>@if($log->old_value !== null || $log->new_value !== null){{ $log->old_value ?? '—' }} → {{ $log->new_value ?? '—' }}@else — @endif</td>
                    <td>{{ $log->reason ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">No activity yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:12px;">{{ $logs->links() }}</div>
</div>
@endsection
