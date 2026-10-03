<x-admin-layout title="Activity Logs" active="activity">

<div class="card mb-4 p-3">
    <p class="text-xs" style="color:#6b90aa;">
        Activity logs record administrative actions (approvals, suspensions, settings changes, sign-ins).
        Sensitive values such as passwords, one-time codes, and tokens are never stored.
    </p>
</div>

<form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="mb-1 block text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Admin</label>
        <select name="admin_id" class="input w-44 py-2 text-sm" style="border-color:#FFDCC2;">
            <option value="">All admins</option>
            @foreach ($admins as $a)
                <option value="{{ $a->id }}" {{ request('admin_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</label>
        <select name="action" class="input w-48 py-2 text-sm" style="border-color:#FFDCC2;">
            <option value="">All actions</option>
            @foreach ($actionOptions as $key => $label)
                <option value="{{ $key }}" {{ request('action') == $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">From</label>
        <input type="date" name="from" value="{{ request('from') }}" class="input w-40 py-2 text-sm" style="border-color:#FFDCC2;">
    </div>
    <div>
        <label class="mb-1 block text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">To</label>
        <input type="date" name="to" value="{{ request('to') }}" class="input w-40 py-2 text-sm" style="border-color:#FFDCC2;">
    </div>
    <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Filter</button>
    @if (request()->hasAny(['admin_id','action','from','to','status']))
        <a href="{{ route('admin.activity.index') }}" class="text-sm" style="color:#fa4e1c;">Clear</a>
    @endif
</form>

<div class="card overflow-x-auto">
    <table class="w-full min-w-[760px] text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">When</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Admin</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Details</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                @php
                    $st = match($log->status) {
                        'failed', 'error' => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                        'warning'          => ['bg'=>'#FFF7ED','text'=>'#d97706'],
                        default            => ['bg'=>'#F0FDF4','text'=>'#059669'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;">
                    <td class="px-5 py-3 whitespace-nowrap" style="color:#555;">
                        {{ $log->created_at->format('M d, Y') }}
                        <span class="block text-xs" style="color:#6b90aa;">{{ $log->created_at->format('g:i A') }}</span>
                    </td>
                    <td class="px-3 py-3" style="color:#002b4d;">{{ $log->admin_name ?? ($log->admin->name ?? 'System') }}</td>
                    <td class="px-3 py-3 font-medium" style="color:#002b4d;">{{ $log->action_label ?: $log->action }}</td>
                    <td class="px-3 py-3" style="color:#555;">{{ $log->description ?? '—' }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" style="background:{{ $st['bg'] }};color:{{ $st['text'] }};">
                            {{ ucfirst($log->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-xs" style="color:#6b90aa;">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No activity recorded yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>

</x-admin-layout>
