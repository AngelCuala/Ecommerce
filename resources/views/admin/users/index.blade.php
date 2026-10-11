<x-admin-layout title="User Management" active="users">
@include('partials.panel.plain')
<style>
    .role-tab { position: relative; padding: .6rem .25rem; margin-right: 1.25rem; font-size: .875rem; font-weight: 600; white-space: nowrap; color: #8a7f78; transition: color .15s; }
    .role-tab:hover { color: #222; }
    .role-tab.is-active { color: #222; }
    .role-tab.is-active::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: #fa4e1c; border-radius: 2px; }
    .role-tab .tab-count { margin-left: .35rem; font-size: .75rem; color: #b3a79f; }
    .role-tab.is-active .tab-count { color: #fa4e1c; }
    .adm-avatar { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; background: #F5F2EF; color: #57534E; }
    .adm-action { font-size: .8125rem; font-weight: 600; color: #57534E; }
    .adm-action:hover { color: #222; text-decoration: underline; }
    .adm-action.danger { color: #B91C1C; }
    .adm-action.ok { color: #047857; }
    .adm-sep { margin: 0 .4rem; color: #E7E5E4; }
    .usr-table th, .usr-table td { padding-left: .9rem; padding-right: .9rem; }
    .usr-table th:first-child, .usr-table td:first-child { padding-left: 1.25rem; }
    .usr-table th:last-child, .usr-table td:last-child { padding-right: 1.25rem; }
</style>
@php
    $roleLabel = function ($role) {
        $role = strtolower($role ?? 'buyer');
        if (\Illuminate\Support\Str::endsWith($role, '_pending')) {
            return ucfirst(str_replace('_', ' ', \Illuminate\Support\Str::beforeLast($role, '_pending'))) . ' (pending)';
        }
        return ucfirst(str_replace('_', ' ', $role));
    };
    $roleTag = function ($role) {
        $role = strtolower($role ?? 'buyer');
        if (in_array($role, ['suspended', 'deactivated', 'rejected'])) return 'cancelled';
        if ($role === 'pending' || \Illuminate\Support\Str::endsWith($role, '_pending')) return 'pending';
        return 'neutral';
    };
@endphp

<div class="mx-auto max-w-6xl">

    {{-- Role tabs + search --}}
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3 border-b" style="border-color:#ECE7E3;">
        <nav class="flex flex-wrap" id="roleTabs">
            @php $tabs = ['All', 'Admin', 'Seller', 'Buyer', 'Suspended', 'Deactivated']; @endphp
            @foreach ($tabs as $tab)
                <button type="button" class="role-tab {{ $tab === 'All' ? 'is-active' : '' }}"
                        data-role="{{ strtolower($tab) }}">
                    {{ $tab }}<span class="tab-count">{{ $tab==='All' ? $users->count() : $users->filter(fn($u) => strtolower($u->role??'buyer')===strtolower($tab))->count() }}</span>
                </button>
            @endforeach
        </nav>
        <form method="GET" class="mb-2 flex items-center gap-2">
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email"
                       class="input !py-1.5 !pl-8 text-sm" style="width:15rem;">
            </div>
            <button type="submit" class="pl-btn-line !py-1.5">Search</button>
            @if(request('search'))
                <a href="{{ route('admin.users.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
            @endif
        </form>
    </div>

    {{-- Users --}}
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table usr-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Products</th>
                        <th>Orders</th>
                        <th>Joined</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="userRows">
                    @forelse ($users as $u)
                        @php
                            $roleKey = strtolower($u->role ?? 'buyer');
                        @endphp
                        <tr class="user-row"
                            data-role="{{ $roleKey }}"
                            data-search="{{ strtolower($u->name.' '.$u->email.' '.($u->username??'')) }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="adm-avatar">{{ strtoupper(substr($u->name ?? 'U', 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium leading-snug" style="color:#222;">{{ $u->name }}</p>
                                        <p class="break-all text-xs pl-muted">{{ $u->email }}</p>
                                        @if ($u->username)
                                            <p class="text-xs" style="color:#a39890;">{{ '@' . $u->username }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td><span class="pl-tag {{ $roleTag($u->role) }}">{{ $roleLabel($u->role) }}</span></td>
                            <td>{{ $u->books_count ?? 0 }}</td>
                            <td>{{ $u->orders_count ?? 0 }}</td>
                            <td class="whitespace-nowrap pl-muted">{{ $u->created_at->format('M d, Y') }}</td>

                            <td class="whitespace-nowrap text-right">
                                @if ($u->isAdmin())
                                    <span class="text-xs font-medium pl-muted">Protected</span>
                                @else
                                    <a href="{{ route('admin.users.show', $u->id) }}" class="adm-action">View</a>

                                    {{-- Activate (for deactivated/suspended users) --}}
                                    @if (in_array($roleKey, ['deactivated','suspended']))
                                        <span class="adm-sep">|</span>
                                        <form action="{{ route('admin.users.activate', $u->id) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <button class="adm-action ok">Activate</button>
                                        </form>
                                    @endif

                                    {{-- Suspend (for active users) --}}
                                    @if (! in_array($roleKey, ['suspended','deactivated']))
                                        <span class="adm-sep">|</span>
                                        <form action="{{ route('admin.users.suspend', $u->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Suspend {{ addslashes($u->name) }}?')">
                                            @csrf @method('PATCH')
                                            <button class="adm-action danger">Suspend</button>
                                        </form>
                                    @endif

                                    {{-- Deactivate (permanent disable) --}}
                                    @if ($roleKey !== 'deactivated')
                                        <span class="adm-sep">|</span>
                                        <form action="{{ route('admin.users.deactivate', $u->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Permanently deactivate {{ addslashes($u->name) }}? They will not be able to log in.')">
                                            @csrf @method('PATCH')
                                            <button class="adm-action danger">Deactivate</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-10 text-center pl-muted">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="noResults" class="hidden px-5 py-10 text-center text-sm pl-muted">No users match your search.</p>
    </div>
</div>

<script src="{{ asset('js/admin-users.js') }}"></script>

</x-admin-layout>
