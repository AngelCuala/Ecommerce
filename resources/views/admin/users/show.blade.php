<x-admin-layout title="User Profile" active="users">

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

<div class="mb-6">
    <a href="{{ route('admin.users.index') }}"
       class="text-sm font-semibold transition" style="color:#fa4e1c;"
       onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
        ← Back to Users
    </a>
</div>

<div class="grid gap-6 lg:grid-cols-[320px_1fr]">

    {{-- ── Profile card ── --}}
    <div class="space-y-5">
        <div class="card p-6 text-center">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full font-display text-3xl font-bold text-white"
                 style="background:#002b4d;">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <p class="mt-4 font-bold text-lg" style="color:#222222;">{{ $user->name }}</p>
            <p class="text-sm" style="color:#6b90aa;">{{ $user->email }}</p>
            @if ($user->username)
                <p class="text-xs mt-0.5" style="color:#BBBBBB;">@{{ $user->username }}</p>
            @endif

            {{-- Role badge --}}
            @php
                $roleKey = strtolower($user->role ?? 'buyer');
                $rc = match($roleKey) {
                    'admin'       => ['bg'=>'#EEF2FF','text'=>'#4338CA'],
                    'seller'      => ['bg'=>'rgba(250,78,28,.12)','text'=>'#fa4e1c'],
                    'suspended'   => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                    'deactivated' => ['bg'=>'#F3F4F6','text'=>'#6B7280'],
                    default       => ['bg'=>'#F0FDF4','text'=>'#059669'],
                };
            @endphp
            <span class="mt-3 inline-block rounded-full px-4 py-1.5 text-sm font-bold"
                  style="background:{{ $rc['bg'] }};color:{{ $rc['text'] }};">
                {{ ucfirst($user->role ?? 'buyer') }}
            </span>

            <div class="mt-5 border-t pt-5 text-left space-y-2 text-sm" style="border-color:#dce8f0;">
                <div class="flex justify-between">
                    <span style="color:#6b90aa;">Joined</span>
                    <span style="color:#222;">{{ $user->created_at->format('M d, Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span style="color:#6b90aa;">Products</span>
                    <span style="color:#222;">{{ $user->books_count ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span style="color:#6b90aa;">Orders</span>
                    <span style="color:#222;">{{ $user->orders_count ?? 0 }}</span>
                </div>
                @if ($user->phone)
                    <div class="flex justify-between">
                        <span style="color:#6b90aa;">Phone</span>
                        <span style="color:#222;">{{ $user->phone }}</span>
                    </div>
                @endif
                @if ($user->address)
                    <div class="flex justify-between">
                        <span style="color:#6b90aa;">Address</span>
                        <span style="color:#222;text-align:right;max-width:160px;">{{ $user->address }}</span>
                    </div>
                @endif
                @if ($user->sex)
                    <div class="flex justify-between">
                        <span style="color:#6b90aa;">Sex</span>
                        <span style="color:#222;">{{ $user->sex }}</span>
                    </div>
                @endif
                @if ($user->birthday)
                    <div class="flex justify-between">
                        <span style="color:#6b90aa;">Birthday</span>
                        <span style="color:#222;">{{ \Carbon\Carbon::parse($user->birthday)->format('M d, Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Account actions --}}
        @if (! $user->isAdmin())
            <div class="card p-5 space-y-3">
                <h3 class="text-sm font-bold" style="color:#222222;">Account Actions</h3>

                @if (in_array($roleKey, ['suspended','deactivated']))
                    <form action="{{ route('admin.users.activate', $user->id) }}" method="POST">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-xl py-2.5 text-sm font-bold transition"
                                style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;"
                                onmouseover="this.style.background='#059669';this.style.color='#fff';"
                                onmouseout="this.style.background='#ECFDF5';this.style.color='#059669';">
                            ✓ Activate Account
                        </button>
                    </form>
                @endif

                @if (! in_array($roleKey, ['suspended','deactivated']))
                    <form action="{{ route('admin.users.suspend', $user->id) }}" method="POST"
                          onsubmit="return confirm('Suspend {{ addslashes($user->name) }}?')">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-xl py-2.5 text-sm font-bold transition"
                                style="background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;"
                                onmouseover="this.style.background='#D97706';this.style.color='#fff';"
                                onmouseout="this.style.background='#FFFBEB';this.style.color='#D97706';">
                            ⏸ Suspend Account
                        </button>
                    </form>
                @endif

                @if ($roleKey !== 'deactivated')
                    <form action="{{ route('admin.users.deactivate', $user->id) }}" method="POST"
                          onsubmit="return confirm('Permanently deactivate this account? The user will not be able to log in.')">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-xl py-2.5 text-sm font-bold transition"
                                style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;"
                                onmouseover="this.style.background='#DC2626';this.style.color='#fff';"
                                onmouseout="this.style.background='#FEF2F2';this.style.color='#DC2626';">
                            🚫 Deactivate Account
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Sorting Center municipality assignment --}}
        @if ($roleKey === 'sorting_center')
            <div class="card p-5">
                <h3 class="text-sm font-bold mb-1" style="color:#222222;">Sorting Center Coverage</h3>
                <p class="text-xs mb-3" style="color:#6b90aa;">
                    This center can only manage riders and deliveries within its assigned municipality.
                </p>

                @if ($user->assigned_municipality)
                    <div class="mb-3 rounded-lg p-3 text-sm" style="background:#fff1ee;">
                        <div class="font-bold" style="color:#d93d0e;">{{ $user->assigned_municipality }}</div>
                        <div class="text-xs" style="color:#6b90aa;">{{ $user->assigned_province }}</div>
                    </div>
                @else
                    <div class="mb-3 rounded-lg p-3 text-xs" style="background:#FFFBEB;color:#D97706;">
                        No municipality assigned yet.
                    </div>
                @endif

                <form action="{{ route('admin.users.assign-municipality', $user->id) }}" method="POST" id="sc-assign-form">
                    @csrf @method('PATCH')

                    <label class="block text-[11px] font-bold uppercase tracking-wide mb-1" style="color:#6b90aa;">Province</label>
                    <select id="sc_province" name="province" class="input w-full mb-3 py-2 text-sm" style="border-color:#dce8f0;">
                        <option value="">Loading…</option>
                    </select>

                    <label class="block text-[11px] font-bold uppercase tracking-wide mb-1" style="color:#6b90aa;">Municipality / City</label>
                    <select id="sc_municipality" name="municipality" class="input w-full mb-4 py-2 text-sm" style="border-color:#dce8f0;">
                        <option value="">Select province first</option>
                    </select>

                    {{-- 9-digit PSGC Correspondence Codes (filled by psgc-address.js) --}}
                    <input type="hidden" name="province_code"     id="sc_province_code">
                    <input type="hidden" name="municipality_code" id="sc_municipality_code">

                    <button type="submit" class="btn-gold w-full !py-2 text-sm"
                            style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">
                        Save Assignment
                    </button>
                </form>
            </div>

            <script src="{{ asset('js/psgc-address.js') }}"></script>
            <script>
            (function () {
                // Province → Municipality/City from the local PSA PSGC data. The province list is
                // nationwide and includes NCR and the highly urbanized cities (not under a province).
                PsgcAddress.attach({
                    province: '#sc_province',
                    city:     '#sc_municipality',
                    placeholders: { province: 'Select province…', city: 'Select province first' },
                    old: {
                        province:     @json($user->assigned_province_code ?: $user->assigned_province),
                        municipality: @json($user->assigned_municipality_code ?: $user->assigned_municipality),
                    },
                });

                document.getElementById('sc-assign-form').addEventListener('submit', function (e) {
                    var code = this.querySelector('input[name="municipality_code"]');
                    if (!document.getElementById('sc_municipality').value || !code || !code.value) {
                        e.preventDefault();
                        alert('Please select a municipality that has a PSA correspondence code.');
                    }
                });
            })();
            </script>
        @endif

        {{-- Seller application (if any) --}}
        @if ($user->sellerApplication)
            @php $app = $user->sellerApplication; @endphp
            <div class="card p-5">
                <h3 class="text-sm font-bold mb-3" style="color:#222222;">Seller Application</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span style="color:#6b90aa;">Shop</span>
                        <span style="color:#222;">{{ $app->shop_name }}</span>
                    </div>
                    @if ($app->business_name)
                        <div class="flex justify-between">
                            <span style="color:#6b90aa;">Business</span>
                            <span style="color:#222;">{{ $app->business_name }}</span>
                        </div>
                    @endif
                    @if ($app->line_of_business)
                        <div class="flex justify-between">
                            <span style="color:#6b90aa;">Category</span>
                            <span style="color:#222;">{{ $app->line_of_business }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span style="color:#6b90aa;">Status</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                              style="background:{{ $app->isApproved()?'#ECFDF5':($app->isRejected()?'#FEF2F2':'#FFFBEB') }};
                                     color:{{ $app->isApproved()?'#059669':($app->isRejected()?'#DC2626':'#D97706') }};">
                            {{ ucfirst($app->status) }}
                        </span>
                    </div>
                    @if ($app->rejection_reason)
                        <p class="text-xs mt-1" style="color:#DC2626;">Reason: {{ $app->rejection_reason }}</p>
                    @endif
                </div>
                <a href="{{ route('admin.seller-applications.show', $app->id) }}"
                   class="mt-3 block text-center text-xs font-semibold transition" style="color:#fa4e1c;"
                   onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
                    View Full Application →
                </a>
            </div>
        @endif
    </div>

    {{-- ── Details ── --}}
    <div class="space-y-6">

        {{-- Personal details --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Personal Information</h2>
            <dl class="grid gap-y-0 text-sm">
                @php
                    $details = [
                        'Full Name'    => $user->name,
                        'Email'        => $user->email,
                        'Username'     => $user->username ?? '—',
                        'Sex'          => $user->sex ?? '—',
                        'Birthday'     => $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('M d, Y').' (Age '.$user->age.')' : '—',
                        'Contact No.'  => $user->contact_no ?? $user->phone ?? '—',
                        'Province'     => $user->province ?? '—',
                        'Municipality' => $user->municipality ?? '—',
                        'Barangay'     => $user->barangay ?? '—',
                        'Street'       => $user->street ?? '—',
                        'House No.'    => $user->house_number ?? '—',
                    ];
                @endphp
                @foreach ($details as $label => $val)
                    <div class="flex items-center justify-between border-b py-2.5" style="border-color:#F5F5F5;">
                        <dt style="color:#6b90aa;">{{ $label }}</dt>
                        <dd class="font-medium text-right" style="color:#222222;">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Valid ID (if uploaded) --}}
        @if ($user->valid_id_path ?? false)
            <div class="card p-6">
                <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Valid ID</h2>
                @php $ext = pathinfo($user->valid_id_path, PATHINFO_EXTENSION); @endphp
                @if (in_array(strtolower($ext), ['jpg','jpeg','png']))
                    <a href="{{ asset('storage/'.$user->valid_id_path) }}" target="_blank">
                        <img src="{{ asset('storage/'.$user->valid_id_path) }}"
                             class="max-h-64 rounded-xl border object-contain"
                             style="border-color:#F0E4D8;" alt="Valid ID">
                    </a>
                @else
                    <a href="{{ asset('storage/'.$user->valid_id_path) }}" target="_blank"
                       class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold"
                       style="border-color:#cfdce8;color:#fa4e1c;">
                        📄 View ID Document
                    </a>
                @endif
            </div>
        @endif

        {{-- Recent orders --}}
        @if ($user->orders && $user->orders->count())
            <div class="card overflow-hidden">
                <div class="px-5 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
                    <h2 class="font-display text-base font-bold" style="color:#222222;">Recent Orders</h2>
                </div>
                <table class="w-full text-sm">
                    <thead style="background:#FFFBF7;">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-bold uppercase" style="color:#6b90aa;">#</th>
                            <th class="px-3 py-3 text-left text-[11px] font-bold uppercase" style="color:#6b90aa;">Date</th>
                            <th class="px-3 py-3 text-left text-[11px] font-bold uppercase" style="color:#6b90aa;">Status</th>
                            <th class="px-5 py-3 text-right text-[11px] font-bold uppercase" style="color:#6b90aa;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($user->orders as $order)
                            <tr style="border-top:1px solid #fff1ee;"
                                onmouseover="this.style.background='#FFF9F5';" onmouseout="this.style.background='';">
                                <td class="px-5 py-3 font-semibold" style="color:#fa4e1c;">
                                    <a href="{{ route('admin.orders.show', $order->id) }}"
                                       style="color:#fa4e1c;">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</a>
                                </td>
                                <td class="px-3 py-3 text-xs" style="color:#6b90aa;">{{ $order->created_at->format('M d, Y') }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                          style="background:#e8f0f6;color:#fa4e1c;">{{ $order->status }}</span>
                                </td>
                                <td class="px-5 py-3 text-right font-bold" style="color:#222;">
                                    ₱{{ number_format($order->total_price, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

</x-admin-layout>
