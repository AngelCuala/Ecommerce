<x-admin-layout title="Activity Logs" active="activity">
@include('partials.panel.plain')

@php
    $roleStyle = [
        'buyer' => 'background:#EFF6FF;color:#1D4ED8;', 'seller' => 'background:#FFF7ED;color:#C2410C;',
        'courier' => 'background:#F5F3FF;color:#6D28D9;', 'courier_pending' => 'background:#F5F3FF;color:#6D28D9;',
        'sorting_center' => 'background:#ECFEFF;color:#0E7490;', 'admin' => 'background:#ECFDF5;color:#047857;',
    ];
@endphp

<p class="mb-4 text-sm pl-muted">
    Every important action on ALVY — orders, cart, stock and product changes, vouchers, approvals, sign-ins and more —
    by buyers, sellers, couriers, sorting centers and admins, with the visitor's IP address.
    Passwords, one-time codes and form contents are never stored.
</p>

<div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
    <div class="pl-card pl-body"><p class="text-xs pl-muted">Actions today</p><p class="text-xl font-bold" style="color:#002b4d;">{{ number_format($today) }}</p></div>
    <div class="pl-card pl-body"><p class="text-xs pl-muted">Failed today</p><p class="text-xl font-bold" style="color:#DC2626;">{{ number_format($failedToday) }}</p></div>
    <div class="pl-card pl-body"><p class="text-xs pl-muted">Showing</p><p class="text-xl font-bold" style="color:#002b4d;">{{ number_format($logs->total()) }}</p></div>
</div>

<form method="GET" class="pl-card pl-body mb-5 flex flex-wrap items-end gap-3">
    <div>
        <label class="pl-label">Search</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, IP or details…" class="input w-52 !py-1.5 text-sm">
    </div>
    <div>
        <label class="pl-label">Who</label>
        <select name="role" class="input w-44 !py-1.5 text-sm">
            <option value="">Everyone</option>
            @foreach ($roles as $key => $label)
                <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="pl-label">Action</label>
        <select name="action" class="input w-48 !py-1.5 text-sm">
            <option value="">All actions</option>
            @foreach ($actionOptions as $key => $label)
                <option value="{{ $key }}" @selected(request('action') == $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="pl-label">Result</label>
        <select name="status" class="input w-32 !py-1.5 text-sm">
            <option value="">Any</option>
            <option value="success" @selected(request('status') === 'success')>Success</option>
            <option value="failed" @selected(request('status') === 'failed')>Failed</option>
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
        @if (request()->hasAny(['search', 'role', 'action', 'from', 'to', 'status', 'user']))
            <a href="{{ route('admin.activity.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
        @endif
    </div>
</form>

<div class="pl-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="pl-table min-w-[900px]">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Result</th>
                    <th>IP address</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $tag  = in_array($log->status, ['failed', 'error'], true) ? 'cancelled' : ($log->status === 'warning' ? 'pending' : 'delivered');
                        $role = $log->user_role ?? ($log->admin_id ? null : 'guest');
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap">
                            {{ $log->created_at->format('M d, Y') }}
                            <span class="block text-xs pl-muted">{{ $log->created_at->format('g:i:s A') }}</span>
                        </td>
                        <td>
                            @if ($log->admin_id)
                                <a href="{{ route('admin.activity.index', ['user' => $log->admin_id]) }}" class="font-medium hover:underline" style="color:#222;"
                                   title="Show only this person's activity">{{ $log->admin_name ?? 'User #' . $log->admin_id }}</a>
                            @else
                                <span style="color:#222;">{{ $log->admin_name ?? 'Guest' }}</span>
                            @endif
                            @if ($role)
                                <span class="mt-0.5 block w-max rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                      style="{{ $roleStyle[$role] ?? 'background:#F3F4F6;color:#4B5563;' }}">{{ str_replace('_', ' ', $role) }}</span>
                            @endif
                        </td>
                        <td class="font-medium" style="color:#222;">{{ $log->action_label ?: $log->action }}</td>
                        <td class="text-sm">{{ $log->description ?? '—' }}</td>
                        <td><span class="pl-tag {{ $tag }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="whitespace-nowrap text-xs">
                            @if ($log->ip_address)
                                <a href="{{ route('admin.activity.index', ['search' => $log->ip_address]) }}" class="font-mono hover:underline pl-muted"
                                   title="Show everything from this IP">{{ $log->ip_address }}</a>
                            @else
                                <span class="pl-muted">—</span>
                            @endif
                        </td>
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
