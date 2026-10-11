<x-admin-layout title="Buyer Management" active="customers">
@include('partials.panel.plain')
<style>
    .adm-avatar { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; background: #F5F2EF; color: #57534E; }
    .adm-action { font-size: .8125rem; font-weight: 600; color: #57534E; }
    .adm-action:hover { color: #222; text-decoration: underline; }
</style>

<div class="mx-auto max-w-6xl">

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search name, username, or email" class="input !py-1.5 !pl-8 text-sm" style="width:17rem;">
        </div>

        <select name="status" class="input !py-1.5 text-sm" style="width:10rem;">
            <option value="">All statuses</option>
            <option value="pending"  {{ request('status') == 'pending'  ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
            <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
        </select>

        <button class="pl-btn-line !py-1.5">Search</button>
        @if (request('search') || request('status'))
            <a href="{{ route('admin.customers.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
        @endif
    </form>

    {{-- Buyers --}}
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $c)
                        @php
                            $statusTag = ['approved' => 'delivered', 'pending' => 'pending', 'rejected' => 'cancelled', 'suspended' => 'cancelled'][$c->status] ?? 'neutral';
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="adm-avatar">{{ strtoupper(substr($c->first_name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium" style="color:#222;">
                                            {{ $c->last_name }}, {{ $c->first_name }} {{ $c->middle_initial ? $c->middle_initial.'.' : '' }}
                                        </p>
                                        <p class="text-xs pl-muted">Joined {{ $c->created_at->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $c->username }}</td>
                            <td class="break-all">{{ $c->email }}</td>
                            <td class="whitespace-nowrap">{{ $c->contact_no }}</td>
                            <td><span class="pl-tag {{ $statusTag }}">{{ ucfirst($c->status) }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('admin.customers.show', $c->id) }}" class="adm-action">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-10 text-center pl-muted">No buyers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
</div>

</x-admin-layout>
