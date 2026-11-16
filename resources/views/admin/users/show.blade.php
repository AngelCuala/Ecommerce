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

@php
    $roleKey = strtolower($user->role ?? 'buyer');
    $isDisabled = in_array($roleKey, ['suspended', 'deactivated'], true);
    $roleLabel = [
        'buyer' => 'Buyer', 'seller' => 'Seller', 'courier' => 'Rider', 'courier_pending' => 'Rider (pending)',
        'sorting_center' => 'Sorting center', 'sorting_center_pending' => 'Sorting center (pending)', 'admin' => 'Admin',
    ][$roleKey] ?? null;
    $isCenter = in_array($roleKey, ['sorting_center', 'sorting_center_pending'], true) || ($isDisabled && $user->sortingCenterApplications()->exists());
    $scApp = $isCenter ? $user->sortingCenterApplications()->latest('id')->first() : null;
    $rider = in_array($roleKey, ['courier', 'courier_pending'], true) ? \App\Models\Rider::with('sortingCenter:id,name')->where('user_id', $user->id)->first() : null;

    // Back to the list this account belongs to.
    [$backUrl, $backLabel] = match (true) {
        $isCenter                                          => [route('admin.sorting-centers.index'), 'Sorting centers'],
        $roleKey === 'seller'                              => [route('admin.sellers.index'), 'Sellers'],
        in_array($roleKey, ['courier', 'courier_pending']) => [route('admin.riders.index'), 'Riders'],
        $roleKey === 'buyer'                               => [route('admin.customers.index'), 'Buyers'],
        default                                            => [route('admin.users.index'), 'All accounts'],
    };

    [$statusLabel, $statusBg, $statusText] = match ($roleKey) {
        'suspended'   => ['Suspended', '#FFFBEB', '#B45309'],
        'deactivated' => ['Deactivated', '#F3F4F6', '#4B5563'],
        default       => str_ends_with($roleKey, '_pending') ? ['Waiting for approval', '#FFFBEB', '#B45309'] : ['Active', '#ECFDF5', '#047857'],
    };

    // What is worth counting depends on the kind of account.
    $stats = match (true) {
        $isCenter => [
            ['Hubs', \Illuminate\Support\Facades\DB::table('logistics_hubs')->where('sorting_center_id', $user->id)->count()],
            ['Barangays', $user->deliveryAreas()->count()],
            ['Riders', $user->riders()->where('application_status', 'approved')->where('is_active', true)->count() . ' / ' . $user->riders()->count()],
        ],
        $roleKey === 'seller' => [['Products', $user->books_count ?? 0], ['Orders placed', $user->orders_count ?? 0]],
        $rider !== null       => [['Sorting center', $rider->sortingCenter?->sortingCenterApplications()->latest('id')->value('business_name') ?: ($rider->sortingCenter->name ?? '—')], ['Vehicle', ucfirst($rider->vehicle_type ?: '—')]],
        default               => [['Orders', $user->orders_count ?? 0]],
    };
    $stats[] = ['Joined', $user->created_at->format('M d, Y')];
    $stats[] = ['Last sign-in', $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never'];

    $address = collect([$user->house_number, $user->street, $user->barangay ? 'Brgy. ' . $user->barangay : null, $user->municipality, $user->province])->filter()->implode(', ');
    $phone = $user->contact_no ?: $user->phone;
    $svg = fn (string $d) => '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $d . '</svg>';
@endphp
<style>
    .up-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .65rem; border-radius: 9999px; font-size: .75rem; font-weight: 700; }
    .up-link { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .8rem; border: 1px solid #dce8f0; border-radius: .6rem; background: #fff; font-size: .8125rem; font-weight: 600; color: #002b4d; transition: border-color .15s, color .15s; }
    .up-link:hover { border-color: #fa4e1c; color: #fa4e1c; }
    .up-stat { padding: .75rem .9rem; border-radius: .75rem; background: #F6F9FB; }
    .up-stat dt { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6b90aa; }
    .up-stat dd { margin-top: .15rem; font-size: .95rem; font-weight: 700; color: #222; overflow-wrap: anywhere; }
    .up-act { display: flex; width: 100%; align-items: center; gap: .6rem; padding: .7rem .85rem; border: 1px solid; border-radius: .75rem; text-align: left; transition: background .15s, color .15s; }
    .up-act-title { display: block; font-size: .875rem; font-weight: 700; }
    .up-act-sub { display: block; font-size: .75rem; font-weight: 500; opacity: .85; }
    .up-field { padding: .75rem 0; border-bottom: 1px solid #F1F4F7; }
    .up-field dt { font-size: .75rem; color: #6b90aa; }
    .up-field dd { margin-top: .1rem; font-size: .875rem; font-weight: 600; color: #222; overflow-wrap: anywhere; }
    .up-field dd.is-empty { font-weight: 500; color: #a7b8c6; }
</style>

<div class="mb-5">
    <a href="{{ $backUrl }}" class="text-sm font-semibold hover:underline" style="color:#fa4e1c;">&larr; Back to {{ $backLabel }}</a>
</div>

{{-- ── Header ── --}}
<div class="card mb-6 flex flex-wrap items-center gap-5 p-6" data-user-header>
    @if ($user->profile_photo_url)
        <img src="{{ $user->profile_photo_url }}" alt="" class="h-16 w-16 shrink-0 rounded-full object-cover">
    @else
        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full font-display text-2xl font-bold" style="background:#002b4d;color:#fff;">{{ strtoupper(mb_substr($user->name ?? 'U', 0, 1)) }}</span>
    @endif
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="font-display text-xl font-bold" style="color:#222;">{{ $scApp->business_name ?? $user->name }}</h1>
            @if ($roleLabel)<span class="up-pill" style="background:#EEF3F7;color:#002b4d;">{{ $roleLabel }}</span>@endif
            <span class="up-pill" style="background:{{ $statusBg }};color:{{ $statusText }};" data-account-status>{{ $statusLabel }}</span>
        </div>
        <p class="mt-1 text-sm" style="color:#6b90aa;">
            @if ($scApp){{ $user->name }} · @endif{{ $user->email }}@if ($phone) · {{ $phone }}@endif
            @if ($user->username) · @{{ $user->username }}@endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($scApp)
            <a href="{{ route('admin.sc-applications.show', $scApp->id) }}" class="up-link">{!! $svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6"/>') !!} Application</a>
        @endif
        @if ($isCenter && $user->riders()->exists())
            <a href="{{ route('admin.riders.index', ['center' => $user->id]) }}" class="up-link">{!! $svg('<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/>') !!} Riders</a>
        @endif
        @if ($user->sellerApplication)
            <a href="{{ route('admin.seller-applications.show', $user->sellerApplication->id) }}" class="up-link">{!! $svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6"/>') !!} Seller application</a>
        @endif
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-[320px_1fr]">

    <div class="space-y-5">
        {{-- Overview --}}
        <div class="card p-5">
            <h2 class="mb-3 text-sm font-bold" style="color:#222;">Overview</h2>
            <dl class="grid grid-cols-2 gap-2" data-user-stats>
                @foreach ($stats as [$label, $value])
                    <div class="up-stat {{ strlen((string) $value) > 14 ? 'col-span-2' : '' }}"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
        </div>

        {{-- Account actions --}}
        @if (! $user->isAdmin())
            <div class="card space-y-2 p-5">
                <h2 class="mb-1 text-sm font-bold" style="color:#222;">Account actions</h2>

                @if ($isDisabled)
                    <form action="{{ route('admin.users.activate', $user->id) }}" method="POST"
                          data-confirm="{{ $user->name }} can sign in again with the same kind of account." data-confirm-title="Activate this account?" data-confirm-ok="Activate">
                        @csrf @method('PATCH')
                        <button class="up-act" style="border-color:#A7F3D0;background:#ECFDF5;color:#047857;">
                            {!! $svg('<path d="M20 6 9 17l-5-5"/>') !!}
                            <span><span class="up-act-title">Activate Account</span><span class="up-act-sub">Let them sign in again</span></span>
                        </button>
                    </form>
                @else
                    <form action="{{ route('admin.users.suspend', $user->id) }}" method="POST"
                          data-confirm="{{ $user->name }} is signed out and can't sign in until you activate the account again." data-confirm-title="Suspend this account?" data-confirm-ok="Suspend">
                        @csrf @method('PATCH')
                        <button class="up-act" style="border-color:#FDE68A;background:#FFFBEB;color:#B45309;">
                            {!! $svg('<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>') !!}
                            <span><span class="up-act-title">Suspend Account</span><span class="up-act-sub">Pause it for now; you can undo this</span></span>
                        </button>
                    </form>
                @endif

                @if ($roleKey !== 'deactivated')
                    <form action="{{ route('admin.users.deactivate', $user->id) }}" method="POST"
                          data-confirm="{{ $user->name }} is signed out of every device and can't sign in. You can still activate the account later." data-confirm-title="Deactivate this account?" data-confirm-ok="Deactivate">
                        @csrf @method('PATCH')
                        <button class="up-act" style="border-color:#FECACA;background:#FEF2F2;color:#B91C1C;">
                            {!! $svg('<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>') !!}
                            <span><span class="up-act-title">Deactivate Account</span><span class="up-act-sub">Close it and sign them out everywhere</span></span>
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Sorting Center municipality assignment --}}
        @if ($roleKey === 'sorting_center')
            <div class="card p-5">
                <h3 class="text-sm font-bold mb-1" style="color:#222222;">Sorting center coverage</h3>
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
                    <select id="sc_province" class="input w-full mb-3 py-2 text-sm" style="border-color:#dce8f0;">
                        <option value="">Loading…</option>
                    </select>

                    <label class="block text-[11px] font-bold uppercase tracking-wide mb-1" style="color:#6b90aa;">Municipality / City</label>
                    <select id="sc_municipality" class="input w-full mb-4 py-2 text-sm" style="border-color:#dce8f0;">
                        <option value="">Select province first</option>
                    </select>

                    {{-- Submitted values (names + PSGC codes) --}}
                    <input type="hidden" name="province"          id="sc_province_name">
                    <input type="hidden" name="province_code"     id="sc_province_code">
                    <input type="hidden" name="municipality"      id="sc_municipality_name">
                    <input type="hidden" name="municipality_code" id="sc_municipality_code">

                    <button type="submit" class="btn-gold w-full !py-2 text-sm"
                            style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">
                        Save Assignment
                    </button>
                </form>
            </div>

            <script>
            (function () {
                const PSGC = '/api/psgc';
                const provSel = document.getElementById('sc_province');
                const munSel  = document.getElementById('sc_municipality');
                const provName = document.getElementById('sc_province_name');
                const provCode = document.getElementById('sc_province_code');
                const munName  = document.getElementById('sc_municipality_name');
                const munCode  = document.getElementById('sc_municipality_code');

                const current = {
                    province: @json($user->assigned_province),
                    provinceCode: @json($user->assigned_province_code),
                    municipality: @json($user->assigned_municipality),
                    municipalityCode: @json($user->assigned_municipality_code),
                };

                function opt(v, label, code) {
                    const o = document.createElement('option');
                    o.value = v; o.textContent = label; if (code) o.dataset.code = code;
                    return o;
                }

                async function loadProvinces() {
                    provSel.innerHTML = '';
                    provSel.appendChild(opt('', 'Select province…'));
                    try {
                        const res = await fetch(`${PSGC}/provinces`);
                        const list = await res.json();
                        list.forEach(p => provSel.appendChild(opt(p.name, p.name, p.code)));
                    } catch (e) { provSel.appendChild(opt('', 'Failed to load')); }
                    if (current.province) {
                        provSel.value = current.province;
                        await loadMunicipalities(current.municipality);
                    }
                }

                async function loadMunicipalities(preselect) {
                    munSel.innerHTML = '';
                    const sel = provSel.options[provSel.selectedIndex];
                    const code = sel ? sel.dataset.code : '';
                    provName.value = provSel.value;
                    provCode.value = code || '';
                    if (!code) { munSel.appendChild(opt('', 'Select province first')); return; }
                    munSel.appendChild(opt('', 'Loading…'));
                    try {
                        const res = await fetch(`${PSGC}/provinces/${code}/municipalities`);
                        const list = await res.json();
                        munSel.innerHTML = '';
                        munSel.appendChild(opt('', 'Select municipality…'));
                        list.forEach(m => munSel.appendChild(opt(m.name, m.name, m.code)));
                        if (preselect) munSel.value = preselect;
                        syncMunicipality();
                    } catch (e) { munSel.innerHTML = ''; munSel.appendChild(opt('', 'Failed to load')); }
                }

                function syncMunicipality() {
                    const sel = munSel.options[munSel.selectedIndex];
                    munName.value = munSel.value;
                    munCode.value = sel ? (sel.dataset.code || '') : '';
                }

                provSel.addEventListener('change', () => loadMunicipalities());
                munSel.addEventListener('change', syncMunicipality);

                document.getElementById('sc-assign-form').addEventListener('submit', function (e) {
                    if (!munName.value || !munCode.value) {
                        e.preventDefault();
                        alert('Please select a municipality.');
                    }
                });

                loadProvinces();
            })();
            </script>
        @endif

        {{-- Seller application (if any) --}}
        @if ($user->sellerApplication)
            @php $app = $user->sellerApplication; @endphp
            <div class="card p-5">
                <h3 class="text-sm font-bold mb-3" style="color:#222222;">Seller application</h3>
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
            <h2 class="font-display text-base font-bold" style="color:#222222;">Personal information</h2>
            @php
                $details = [
                    'Full name'   => $user->name,
                    'Username'    => $user->username,
                    'Email'       => $user->email,
                    'Contact no.' => $phone,
                    'Sex'         => $user->sex,
                    'Birthday'    => $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('M d, Y') . ($user->age ? ' (age ' . $user->age . ')' : '') : null,
                ];
            @endphp
            <dl class="grid gap-x-8 sm:grid-cols-2" data-user-details>
                @foreach ($details as $label => $val)
                    <div class="up-field"><dt>{{ $label }}</dt><dd class="{{ filled($val) ? '' : 'is-empty' }}">{{ filled($val) ? $val : 'Not provided' }}</dd></div>
                @endforeach
                <div class="up-field sm:col-span-2" style="border-bottom:0;"><dt>Address</dt><dd class="{{ $address ? '' : 'is-empty' }}">{{ $address ?: 'Not provided' }}</dd></div>
            </dl>
        </div>

        {{-- Valid ID (if uploaded) --}}
        @if ($user->valid_id_path ?? false)
            <div class="card p-6">
                <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Valid ID</h2>
                @php $ext = pathinfo($user->valid_id_path, PATHINFO_EXTENSION); @endphp
                @if (in_array(strtolower($ext), ['jpg','jpeg','png']))
                    <a href="{{ \App\Support\ProtectedFile::url($user->valid_id_path) }}" target="_blank">
                        <img src="{{ \App\Support\ProtectedFile::url($user->valid_id_path) }}"
                             class="max-h-64 rounded-xl border object-contain"
                             style="border-color:#F0E4D8;" alt="Valid ID">
                    </a>
                @else
                    <a href="{{ \App\Support\ProtectedFile::url($user->valid_id_path) }}" target="_blank"
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
