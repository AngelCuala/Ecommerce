<x-admin-layout title="User Management" active="users">

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
  <div class="overflow-x-auto">
    <table class="w-full text-sm" style="min-width:860px;">
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
                                    <p class="text-xs" style="color:#BBBBBB;">{{ '@' . $u->username }}</p>
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
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium" style="color:#6b90aa;">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Protected
                            </span>
                        @else
                            <div class="flex items-center justify-end gap-1.5 flex-nowrap">

                                {{-- View Profile --}}
                                <a href="{{ route('admin.users.show', $u->id) }}"
                                   class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                   style="background:#e8f0f6;color:#1a4d6e;border:1px solid #cfdce8;"
                                   onmouseover="this.style.background='#1a4d6e';this.style.color='#fff';"
                                   onmouseout="this.style.background='#e8f0f6';this.style.color='#1a4d6e';">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                                    View
                                </a>

                                {{-- Activate (for deactivated/suspended users) --}}
                                @if (in_array($roleKey, ['deactivated','suspended']))
                                    <form action="{{ route('admin.users.activate', $u->id) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;"
                                                onmouseover="this.style.background='#059669';this.style.color='#fff';"
                                                onmouseout="this.style.background='#ECFDF5';this.style.color='#059669';">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                                            Activate
                                        </button>
                                    </form>
                                @endif

                                {{-- Suspend (for active users) --}}
                                @if (! in_array($roleKey, ['suspended','deactivated']))
                                    <form action="{{ route('admin.users.suspend', $u->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Suspend {{ addslashes($u->name) }}?')">
                                        @csrf @method('PATCH')
                                        <button class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                style="background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;"
                                                onmouseover="this.style.background='#D97706';this.style.color='#fff';"
                                                onmouseout="this.style.background='#FFFBEB';this.style.color='#D97706';">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                                            Suspend
                                        </button>
                                    </form>
                                @endif

                                {{-- Deactivate (permanent disable) --}}
                                @if ($roleKey !== 'deactivated')
                                    <form action="{{ route('admin.users.deactivate', $u->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Permanently deactivate {{ addslashes($u->name) }}? They will not be able to log in.')">
                                        @csrf @method('PATCH')
                                        <button class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;"
                                                onmouseover="this.style.background='#DC2626';this.style.color='#fff';"
                                                onmouseout="this.style.background='#FEF2F2';this.style.color='#DC2626';">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="m5.6 5.6 12.8 12.8"/></svg>
                                            Deactivate
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
  </div>
    <p id="noResults" class="hidden px-5 py-10 text-center text-sm" style="color:#6b90aa;">No users match your search.</p>
</div>

<script src="{{ asset('js/admin-users.js') }}"></script>

</x-admin-layout>
