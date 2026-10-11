<x-admin-layout title="Activity Logs" active="activity">
@include('partials.panel.plain')

<p class="mb-4 text-sm pl-muted">
    Activity logs record administrative actions (approvals, suspensions, settings changes, sign-ins).
    Sensitive values such as passwords, one-time codes, and tokens are never stored.
</p>

<form method="GET" class="pl-card pl-body mb-5 flex flex-wrap items-end gap-3">
    <div>
        <label class="pl-label">Admin</label>
        <select name="admin_id" class="input w-44 !py-1.5 text-sm">
            <option value="">All admins</option>
            @foreach ($admins as $a)
                <option value="{{ $a->id }}" {{ request('admin_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="pl-label">Action</label>
        <select name="action" class="input w-48 !py-1.5 text-sm">
            <option value="">All actions</option>
            @foreach ($actionOptions as $key => $label)
                <option value="{{ $key }}" {{ request('action') == $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="pl-label">From</label>
        <input type="date" name="from" value="{{ request('from') }}" class="input w-40 !py-1.5 text-sm">
    </div>
    <div>
        <label class="pl-label">To</label>
        <input type="date" name="to" value="{{ request('to') }}" class="input w-40 !py-1.5 text-sm">
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" class="pl-btn !py-1.5">Filter</button>
        @if (request()->hasAny(['admin_id','action','from','to','status']))
            <a href="{{ route('admin.activity.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
        @endif
    </div>
</form>

<div class="pl-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="pl-table min-w-[760px]">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $tag = match($log->status) {
                            'failed', 'error' => 'cancelled',
                            'warning'          => 'pending',
                            default            => 'delivered',
                        };
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap">
                            {{ $log->created_at->format('M d, Y') }}
                            <span class="block text-xs pl-muted">{{ $log->created_at->format('g:i A') }}</span>
                        </td>
                        <td style="color:#222;">{{ $log->admin_name ?? ($log->admin->name ?? 'System') }}</td>
                        <td class="font-medium" style="color:#222;">{{ $log->action_label ?: $log->action }}</td>
                        <td>{{ $log->description ?? '—' }}</td>
                        <td><span class="pl-tag {{ $tag }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="text-xs pl-muted">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="!py-10 text-center text-sm pl-muted">No activity recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $logs->links() }}</div>

</x-admin-layout>
