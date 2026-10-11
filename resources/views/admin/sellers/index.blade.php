<x-admin-layout title="Seller Management" active="sellers">
@include('partials.panel.plain')
<style>
    .adm-avatar { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; background: #F5F2EF; color: #57534E; }
    .adm-action { font-size: .8125rem; font-weight: 600; color: #57534E; }
    .adm-action:hover { color: #222; text-decoration: underline; }
    .adm-action.danger { color: #B91C1C; }
    .adm-sep { margin: 0 .4rem; color: #E7E5E4; }
</style>

<div class="mx-auto max-w-6xl">

    {{-- Summary --}}
    <div class="pl-card mb-5 grid grid-cols-3">
        @foreach ([
            ['Total sellers', $stats['total']],
            ['Active', $stats['active']],
            ['Suspended / disabled', $stats['suspended']],
        ] as $i => [$label, $value])
            <div class="px-5 py-3 {{ $i > 0 ? 'border-l' : '' }}" style="border-color:#F2EEEA;">
                <p class="text-xs font-medium pl-muted">{{ $label }}</p>
                <p class="mt-0.5 text-xl font-semibold tracking-tight" style="color:#222;">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search name, shop, business, or email" class="input !py-1.5 !pl-8 text-sm" style="width:18rem;">
        </div>

        <select name="status" class="input !py-1.5 text-sm" style="width:12rem;">
            <option value="">All statuses</option>
            <option value="active"    {{ request('status') == 'active'    ? 'selected' : '' }}>Active</option>
            <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspended / Disabled</option>
        </select>

        <button class="pl-btn-line !py-1.5">Search</button>
        @if (request('search') || request('status'))
            <a href="{{ route('admin.sellers.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
        @endif
    </form>

    {{-- Sellers --}}
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Seller</th>
                        <th>Shop / Business</th>
                        <th>Category</th>
                        <th class="!text-center">Products</th>
                        <th>Status</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sellers as $s)
                        @php
                            $app  = $s->sellerApplication;
                            $isSuspended = in_array($s->role, ['suspended', 'deactivated']);
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="adm-avatar">{{ strtoupper(substr($s->name ?? 'S', 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium" style="color:#222;">{{ $s->name }}</p>
                                        <p class="text-xs pl-muted">{{ $s->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <p class="font-medium" style="color:#222;">{{ $app->shop_name ?? '—' }}</p>
                                <p class="text-xs pl-muted">{{ $app->business_name ?? '' }}</p>
                            </td>
                            <td>{{ $app->line_of_business ?? '—' }}</td>
                            <td class="text-center">{{ $s->books_count }}</td>
                            <td>
                                <span class="pl-tag {{ $isSuspended ? 'cancelled' : 'delivered' }}">{{ $isSuspended ? 'Suspended' : 'Active' }}</span>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('admin.sellers.show', $s->id) }}" class="adm-action">View</a>
                                <span class="adm-sep">|</span>
                                @if ($isSuspended)
                                    <form action="{{ route('admin.sellers.activate', $s->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button class="adm-action"
                                                onclick="return confirm('Reactivate this seller account?')">Activate</button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.sellers.suspend', $s->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button class="adm-action danger"
                                                onclick="return confirm('Suspend this seller account?')">Suspend</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-10 text-center pl-muted">No sellers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

</x-admin-layout>
