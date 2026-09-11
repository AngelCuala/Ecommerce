<x-admin-layout title="Buyer Management" active="customers">

<form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search name, username, or email…" class="input w-64 py-2 text-sm" style="border-color:#FFDCC2;">

    <select name="status" class="input w-40 py-2 text-sm" style="border-color:#FFDCC2;">
        <option value="">All statuses</option>
        <option value="pending"  {{ request('status') == 'pending'  ? 'selected' : '' }}>Pending</option>
        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
        <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
    </select>

    <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Search</button>
    @if (request('search') || request('status'))
        <a href="{{ route('admin.customers.index') }}" class="text-sm" style="color:#fa4e1c;">Clear</a>
    @endif
</form>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Name</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Username</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Email</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Contact</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($customers as $c)
                <tr style="border-top:1px solid #dce8f0;"
                    onmouseover="this.style.background='#e8f0f6';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full font-display text-sm font-bold"
                                 style="background:#fa4e1c;color:#FFFFFF;">
                                {{ strtoupper(substr($c->first_name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold" style="color:#222222;">
                                    {{ $c->last_name }}, {{ $c->first_name }} {{ $c->middle_initial ? $c->middle_initial.'.' : '' }}
                                </p>
                                <p class="text-xs" style="color:#6b90aa;">Joined {{ $c->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $c->username }}</td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $c->email }}</td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $c->contact_no }}</td>
                    <td class="px-3 py-3">
                        @php
                            $sc = match($c->status) {
                                'approved'  => ['bg'=>'#F0FDF4','text'=>'#059669'],
                                'rejected'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                                'suspended' => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                                default     => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'], // pending
                            };
                        @endphp
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                            {{ ucfirst($c->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.customers.show', $c->id) }}"
                           class="text-xs font-semibold" style="color:#fa4e1c;"
                           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No buyers found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $customers->links() }}</div>

</x-admin-layout>