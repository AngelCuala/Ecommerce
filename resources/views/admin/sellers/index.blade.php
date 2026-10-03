<x-admin-layout title="Seller Management" active="sellers">

{{-- Summary stats --}}
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card p-4">
        <p class="text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Total Sellers</p>
        <p class="mt-1 font-display text-2xl font-extrabold" style="color:#002b4d;">{{ $stats['total'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Active</p>
        <p class="mt-1 font-display text-2xl font-extrabold" style="color:#059669;">{{ $stats['active'] }}</p>
    </div>
    <div class="card p-4">
        <p class="text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Suspended / Disabled</p>
        <p class="mt-1 font-display text-2xl font-extrabold" style="color:#DC2626;">{{ $stats['suspended'] }}</p>
    </div>
</div>

<form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search name, shop, business, or email…" class="input w-72 py-2 text-sm" style="border-color:#FFDCC2;">

    <select name="status" class="input w-40 py-2 text-sm" style="border-color:#FFDCC2;">
        <option value="">All statuses</option>
        <option value="active"    {{ request('status') == 'active'    ? 'selected' : '' }}>Active</option>
        <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspended / Disabled</option>
    </select>

    <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Search</button>
    @if (request('search') || request('status'))
        <a href="{{ route('admin.sellers.index') }}" class="text-sm" style="color:#fa4e1c;">Clear</a>
    @endif
</form>

<div class="card overflow-x-auto">
    <table class="w-full min-w-[820px] text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Seller</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Shop / Business</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Category</th>
                <th class="px-3 py-3 text-center text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Products</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sellers as $s)
                @php
                    $app  = $s->sellerApplication;
                    $isSuspended = in_array($s->role, ['suspended', 'deactivated']);
                    $sc = $isSuspended
                        ? ['bg'=>'#FEF2F2','text'=>'#DC2626','label'=>'Suspended']
                        : ['bg'=>'#F0FDF4','text'=>'#059669','label'=>'Active'];
                @endphp
                <tr style="border-top:1px solid #dce8f0;"
                    onmouseover="this.style.background='#e8f0f6';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full font-display text-sm font-bold"
                                 style="background:#fa4e1c;color:#FFFFFF;">
                                {{ strtoupper(substr($s->name ?? 'S', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold" style="color:#222222;">{{ $s->name }}</p>
                                <p class="text-xs" style="color:#6b90aa;">{{ $s->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">
                        <p class="font-semibold" style="color:#002b4d;">{{ $app->shop_name ?? '—' }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $app->business_name ?? '' }}</p>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $app->line_of_business ?? '—' }}</td>
                    <td class="px-3 py-3 text-center" style="color:#555555;">{{ $s->books_count }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                            {{ $sc['label'] }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.sellers.show', $s->id) }}"
                           class="text-xs font-semibold" style="color:#fa4e1c;"
                           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
                            View
                        </a>
                        @if ($isSuspended)
                            <form action="{{ route('admin.sellers.activate', $s->id) }}" method="POST" class="ml-3 inline">
                                @csrf
                                <button class="text-xs font-semibold" style="color:#059669;"
                                        onclick="return confirm('Reactivate this seller account?')">Activate</button>
                            </form>
                        @else
                            <form action="{{ route('admin.sellers.suspend', $s->id) }}" method="POST" class="ml-3 inline">
                                @csrf
                                <button class="text-xs font-semibold" style="color:#DC2626;"
                                        onclick="return confirm('Suspend this seller account?')">Suspend</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No sellers found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

</x-admin-layout>
