<x-admin-layout title="User Management" active="users">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
        ✕ {{ session('error') }}
    </div>
@endif

{{-- Search + role tabs --}}
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2" id="roleTabs">
        @php $tabs = ['All', 'Admin', 'Seller', 'Buyer', 'Suspended', 'Deactivated']; @endphp
        @foreach ($tabs as $tab)
            <button type="button" class="role-tab rounded-full px-4 py-1.5 text-xs font-bold transition"
                    data-role="{{ strtolower($tab) }}"
                    style="background:{{ $tab==='All'?'#fa4e1c':'#e8f0f6' }};color:{{ $tab==='All'?'#fff':'#fa4e1c' }};">
                {{ $tab }}
                <span class="ml-1 opacity-70">({{ $tab==='All' ? $users->count() : $users->filter(fn($u) => strtolower($u->role??'buyer')===strtolower($tab))->count() }})</span>
            </button>
        @endforeach
    </div>
    <div class="flex gap-2">
        <form method="GET" class="flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email…"
                   class="rounded-xl border px-4 py-2 text-sm focus:outline-none"
                   style="border-color:#cfdce8;width:240px;"
                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            <button type="submit" class="rounded-xl px-4 py-2 text-xs font-bold text-white" style="background:#002b4d;">Search</button>
            @if(request('search'))
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border px-4 py-2 text-xs font-semibold" style="border-color:#cfdce8;color:#6b90aa;">Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">User</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Role</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Products</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Orders</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Joined</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
            </tr>
        </thead>
        <tbody id="userRows">
            @forelse ($users as $u)
                @php
                    $roleKey = strtolower($u->role ?? 'buyer');
                    $rc = match($roleKey) {
                        'admin'       => ['bg'=>'#EEF2FF','text'=>'#4338CA'],
                        'seller'      => ['bg'=>'rgba(250,78,28,.12)','text'=>'#fa4e1c'],
                        'suspended'   => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                        'deactivated' => ['bg'=>'#F3F4F6','text'=>'#6B7280'],
                        default       => ['bg'=>'#F0FDF4','text'=>'#059669'], // buyer
                    };
                @endphp
                <tr class="user-row"
                    data-role="{{ $roleKey }}"
                    data-search="{{ strtolower($u->name.' '.$u->email.' '.($u->username??'')) }}"
                    style="border-top:1px solid #dce8f0;"
                    onmouseover="this.style.background='#FFF9F5';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full font-bold text-sm"
                                 style="background:#fa4e1c;color:#fff;">
                                {{ strtoupper(substr($u->name ?? 'U', 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold leading-snug" style="color:#222222;">{{ $u->name }}</p>
                                <p class="text-xs" style="color:#6b90aa;">{{ $u->email }}</p>
                                @if ($u->username)
                                    <p class="text-xs" style="color:#BBBBBB;">@{{ $u->username }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $rc['bg'] }};color:{{ $rc['text'] }};">
                            {{ ucfirst($u->role ?? 'buyer') }}
                        </span>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $u->books_count ?? 0 }}</td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $u->orders_count ?? 0 }}</td>
                    <td class="px-3 py-3 text-xs" style="color:#6b90aa;">{{ $u->created_at->format('M d, Y') }}</td>

                    <td class="px-5 py-3 text-right">
                        @if ($u->isAdmin())
                            <span class="text-xs" style="color:#6b90aa;">Admin — protected</span>
                        @else
                            <div class="flex items-center justify-end gap-2 flex-wrap">

                                {{-- View Profile --}}
                                <a href="{{ route('admin.users.show', $u->id) }}"
                                   class="rounded-lg px-3 py-1 text-xs font-semibold transition"
                                   style="background:#e8f0f6;color:#fa4e1c;border:1px solid #cfdce8;"
                                   onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
                                   onmouseout="this.style.background='#e8f0f6';this.style.color='#fa4e1c';">
                                    👤 View
                                </a>

                                {{-- Activate (for deactivated/suspended users) --}}
                                @if (in_array($roleKey, ['deactivated','suspended']))
                                    <form action="{{ route('admin.users.activate', $u->id) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg px-3 py-1 text-xs font-semibold transition"
                                                style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;"
                                                onmouseover="this.style.background='#059669';this.style.color='#fff';"
                                                onmouseout="this.style.background='#ECFDF5';this.style.color='#059669';">
                                            ✓ Activate
                                        </button>
                                    </form>
                                @endif

                                {{-- Suspend (for active users) --}}
                                @if (! in_array($roleKey, ['suspended','deactivated']))
                                    <form action="{{ route('admin.users.suspend', $u->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Suspend {{ addslashes($u->name) }}?')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg px-3 py-1 text-xs font-semibold transition"
                                                style="background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;"
                                                onmouseover="this.style.background='#D97706';this.style.color='#fff';"
                                                onmouseout="this.style.background='#FFFBEB';this.style.color='#D97706';">
                                            ⏸ Suspend
                                        </button>
                                    </form>
                                @endif

                                {{-- Deactivate (permanent disable) --}}
                                @if ($roleKey !== 'deactivated')
                                    <form action="{{ route('admin.users.deactivate', $u->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Permanently deactivate {{ addslashes($u->name) }}? They will not be able to log in.')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg px-3 py-1 text-xs font-semibold transition"
                                                style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;"
                                                onmouseover="this.style.background='#DC2626';this.style.color='#fff';"
                                                onmouseout="this.style.background='#FEF2F2';this.style.color='#DC2626';">
                                            🚫 Deactivate
                                        </button>
                                    </form>
                                @endif

                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <p id="noResults" class="hidden px-5 py-10 text-center text-sm" style="color:#6b90aa;">No users match your search.</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs   = document.querySelectorAll('.role-tab');
    const rows   = document.querySelectorAll('.user-row');
    const noRes  = document.getElementById('noResults');
    let activeRole = 'all';

    function applyFilters() {
        let visible = 0;
        rows.forEach(row => {
            const show = activeRole === 'all' || row.dataset.role === activeRole;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        noRes.classList.toggle('hidden', visible > 0 || rows.length === 0);
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            activeRole = this.dataset.role;
            tabs.forEach(t => { t.style.background='#e8f0f6'; t.style.color='#fa4e1c'; });
            this.style.background = '#fa4e1c';
            this.style.color = '#FFFFFF';
            applyFilters();
        });
    });
});
</script>

</x-admin-layout>
