<x-courier-layout title="My Account" active="account">
@php
    $statusMap = [
        'approved'  => ['Active', 'green'],
        'pending'   => ['Pending', 'orange'],
        'rejected'  => ['Rejected', 'red'],
        'suspended' => ['Suspended', 'gray'],
    ];
    $st = $rider
        ? ($rider->isSuspended() ? $statusMap['suspended'] : [$rider->statusLabel(), 'green'])
        : ($statusMap[$courier->status ?? 'approved'] ?? $statusMap['pending']);
    $displayName = $rider->full_name ?? $courier?->fullName() ?: $user->name;
    $riderFields = ['vehicle_type', 'plate_number', 'license_number', 'license_expiry', 'license_file', 'or_cr_file'];
    $riderFormOpen = collect($riderFields)->contains(fn ($f) => $errors->has($f));
    $expiry = $rider?->license_expiry;
    $expiryState = ! $expiry ? null : ($expiry->isPast() ? 'expired' : ($expiry->lte(now()->addDays(30)) ? 'soon' : null));
    $isTruck = (bool) ($rider?->isTruck() || (! $rider && $truck));
    $badges = array_values(array_filter([
        ['text' => $rider ? ($isTruck ? 'Courier · truck' : 'Rider') : 'Courier'],
        ['text' => $st[0], 'tone' => $st[1]],
        $stats ? ['text' => $stats['online'] ? 'Online' : 'Offline', 'tone' => $stats['online'] ? 'green' : 'gray'] : null,
    ]));
    $company = $rider?->companyName() ?? $truck?->hubName();
@endphp
<style>
    .ac-docs { display: grid; gap: 10px; margin-top: 14px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    .ac-doc { display: flex; align-items: center; gap: 10px; border: 1px dashed #E6D9CF; border-radius: 12px; padding: 10px 12px; min-width: 0; }
    .ac-doc b { display: block; font-size: 13.5px; color: #222; }
    .ac-doc small { font-size: 12px; color: #8a7a70; }
    .ac-edit { margin-top: 18px; border-top: 1px solid #F0E4DC; padding-top: 14px; }
    .ac-edit summary { list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 700; color: #fa4e1c; }
    .ac-edit summary::-webkit-details-marker { display: none; }
    .ac-edit summary .chev { transition: transform .15s; }
    .ac-edit[open] summary .chev { transform: rotate(90deg); }
    .ac-nowrap { white-space: nowrap; font-variant-numeric: tabular-nums; }
</style>

<x-account-shell title="My Account" subtitle="Manage your profile, vehicle and sign-in." portal="ALVY {{ $rider ? 'Rider' : 'Courier' }}"
                 :nav="\App\Support\AccountNav::rider('account', $isTruck)" :badges="$badges" :name="$displayName" :email="$maskedEmail" :sign-out="true">

    @if ($errors->any() && ! $riderFormOpen)
        <div class="acs-alert acs-alert-error"><ul style="margin:0;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Personal information: contact number and the numbers that matter --}}
    <section class="acs-card acs-pad">
        <div class="acs-card-head">
            <div>
                <span class="acs-icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                <div><h2>Personal Information</h2><p>{{ $company ? $company . ' · ' : '' }}Your sorting center and buyers use your number to reach you.</p></div>
            </div>
        </div>
        @if ($stats)
            <dl class="acs-tiles" style="margin-bottom:18px;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));">
                <div class="acs-tile"><dt>Total earned</dt><dd data-account-earned>₱{{ number_format($stats['earned'], 2) }}</dd></div>
                <div class="acs-tile"><dt>{{ $isTruck ? 'Paid trips' : 'Paid jobs' }}</dt><dd>{{ $stats['jobs'] }}</dd></div>
                <div class="acs-tile"><dt>{{ $rider ? 'Rider since' : 'Courier since' }}</dt><dd>{{ ($rider?->approved_at ?? $truck?->reviewed_at ?? $truck?->created_at)?->format('M Y') ?? '—' }}</dd></div>
                <div class="acs-tile"><dt>{{ $isTruck ? 'Home hub' : 'Area' }}</dt><dd>{{ $isTruck ? ($truck?->homeHub?->code ?? '—') : ($rider?->area?->name ?? '—') }}</dd></div>
            </dl>
        @endif
        <form action="{{ route('courier.account.update') }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="acs-fields">
                <div>
                    <label class="acs-label" for="contact_no">Contact number</label>
                    <input type="text" id="contact_no" name="contact_no" value="{{ old('contact_no', $courier->contact_no ?? $rider->phone ?? $user->phone) }}" class="acs-input" required maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
                </div>
                <div>
                    <span class="acs-label">Email</span>
                    <input type="text" class="acs-input" value="{{ $maskedEmail }}" disabled aria-label="Email">
                </div>
            </div>
            <div class="acs-foot">
                <span class="acs-note" style="margin:0;">Tap your photo on the left to change it.</span>
                <button type="submit" class="acs-btn">Save changes</button>
            </div>
        </form>
    </section>

    {{-- Vehicle, license and documents --}}
    @if ($rider)
    <section class="acs-card acs-pad" id="rider-profile" style="scroll-margin-top:90px;">
        <div class="acs-card-head">
            <div>
                <span class="acs-icon" style="background:#EEF3F7;color:#002b4d;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></span>
                <div><h2>Vehicle &amp; license</h2><p>Changing the plate number or OR/CR sends them to {{ $rider->companyName() }} for re-verification.</p></div>
            </div>
            @if ($isTruck)<span class="acs-badge acs-badge-blue">Courier · truck, hub to hub</span>@endif
        </div>

        @if ($rider->needs_reverification)
            <p class="acs-alert acs-alert-warn" style="margin:0 0 14px;">Your new vehicle details are waiting for {{ $rider->companyName() }} to re-verify them.</p>
        @endif
        @if ($expiryState)
            <p class="acs-alert {{ $expiryState === 'expired' ? 'acs-alert-error' : 'acs-alert-warn' }}" style="margin:0 0 14px;" data-license-warning>
                {{ $expiryState === 'expired' ? 'Your driver\'s license has expired. Renew it and update the details below.' : 'Your driver\'s license expires on ' . $expiry->format('M j, Y') . '. Renew it soon and update the details below.' }}
            </p>
        @endif

        <dl class="acs-tiles">
            <div class="acs-tile"><dt>Sorting center</dt><dd>{{ $rider->companyName() }}<small>{{ $isTruck ? 'Home hub ' . ($truck?->homeHub?->code ?? '—') : ($rider->area?->name ? 'Area: ' . $rider->area->name : 'No area set') }}</small></dd></div>
            <div class="acs-tile"><dt>Vehicle</dt><dd>{{ $rider->vehicle_type ?? '—' }}<small>Plate <span class="ac-nowrap">{{ $rider->plate_number ?? '—' }}</span>{{ $isTruck && $truck?->load_capacity_kg ? ' · up to ' . number_format($truck->load_capacity_kg) . ' kg' : '' }}</small></dd></div>
            <div class="acs-tile"><dt>Driver's license</dt><dd data-name="{{ $rider->full_name }}"><span class="ac-nowrap">{{ $rider->license_number ?? '—' }}</span><small>Expires {{ $expiry?->format('m/d/Y') ?? '—' }}</small></dd></div>
        </dl>

        <div class="ac-docs">
            @foreach ([['license', "Driver's license", $rider->license_path], ['or-cr', 'OR/CR', $rider->or_cr_path]] as [$key, $label, $path])
                <div class="ac-doc">
                    <span class="acs-icon" style="width:34px;height:34px;{{ $path ? 'background:#ECFDF5;color:#059669;' : 'background:#F3EEEA;color:#a39890;' }}">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h4"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;"><b>{{ $label }}</b><small>{{ $path ? 'Uploaded' : 'Not uploaded yet' }}</small></div>
                    @if ($path)<a href="{{ route('courier.rider.document', [$rider->id, $key]) }}" target="_blank" class="acs-btn-ghost" style="padding:6px 12px;">View</a>@endif
                </div>
            @endforeach
        </div>

        <details class="ac-edit" @if ($riderFormOpen) open @endif>
            <summary><svg class="chev" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>Update vehicle &amp; license</summary>
            <form method="POST" action="{{ route('courier.rider.profile.update') }}" enctype="multipart/form-data" style="margin-top:14px;">
                @csrf @method('PUT')
                <div class="acs-fields">
                    <div>
                        <label class="acs-label" for="vehicle_type">Vehicle</label>
                        <select id="vehicle_type" name="vehicle_type" class="acs-input">
                            @foreach ($vehicles as $v)<option @selected(old('vehicle_type', $rider->vehicle_type) === $v)>{{ $v }}</option>@endforeach
                        </select>
                        <p class="acs-note">Car, van or truck: hub-to-hub trips. Motorcycle, tricycle or bicycle: deliveries to buyers.</p>
                        @error('vehicle_type')<p class="acs-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="acs-label" for="plate_number">Plate number</label>
                        <input id="plate_number" name="plate_number" maxlength="12" class="acs-input" value="{{ old('plate_number', $rider->plate_number) }}" style="text-transform:uppercase;">
                        @error('plate_number')<p class="acs-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="acs-label" for="license_number">Driver's license number</label>
                        <input id="license_number" name="license_number" maxlength="30" class="acs-input" value="{{ old('license_number', $rider->license_number) }}">
                        @error('license_number')<p class="acs-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="acs-label" for="license_expiry">License expiry</label>
                        <input type="date" id="license_expiry" name="license_expiry" min="{{ now()->toDateString() }}" class="acs-input" value="{{ old('license_expiry', $expiry?->toDateString()) }}">
                        @error('license_expiry')<p class="acs-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="acs-label" for="license_file">New driver's license photo <span style="font-weight:400;">(JPG or PNG, up to 5 MB)</span></label>
                        <input type="file" id="license_file" name="license_file" accept="image/jpeg,image/png" class="acs-input" style="padding-top:9px;">
                        @error('license_file')<p class="acs-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="acs-label" for="or_cr_file">New OR/CR <span style="font-weight:400;">(PDF, JPG or PNG, up to 5 MB)</span></label>
                        <input type="file" id="or_cr_file" name="or_cr_file" accept="application/pdf,image/jpeg,image/png" class="acs-input" style="padding-top:9px;">
                        @error('or_cr_file')<p class="acs-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="acs-foot"><span></span><button class="acs-btn">Save vehicle &amp; license</button></div>
            </form>
        </details>
    </section>
    @elseif ($truck)
    <section class="acs-card acs-pad" id="rider-profile">
        <div class="acs-card-head"><div><span class="acs-icon" style="background:#EEF3F7;color:#002b4d;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></span>
            <div><h2>Truck &amp; license</h2><p>Your truck for hub-to-hub trips. Ask {{ $truck->hubName() }} to change these.</p></div></div></div>
        <dl class="acs-tiles">
            <div class="acs-tile"><dt>Sorting center</dt><dd>{{ $truck->hubName() }}<small>Home hub {{ $truck->homeHub?->code ?? '—' }}</small></dd></div>
            <div class="acs-tile"><dt>Vehicle</dt><dd>{{ $truck->vehicle_type ?? '—' }}<small>Plate <span class="ac-nowrap">{{ $truck->plate_number ?? '—' }}</span>{{ $truck->load_capacity_kg ? ' · up to ' . number_format($truck->load_capacity_kg) . ' kg' : '' }}</small></dd></div>
            <div class="acs-tile"><dt>Driver's license</dt><dd><span class="ac-nowrap">{{ $truck->license_number ?? '—' }}</span><small>Expires {{ $truck->license_expiry?->format('m/d/Y') ?? '—' }}</small></dd></div>
        </dl>
    </section>
    @elseif ($courier)
    <section class="acs-card acs-pad" id="rider-profile">
        <div class="acs-card-head"><div><span class="acs-icon" style="background:#EEF3F7;color:#002b4d;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></span>
            <div><h2>Vehicle</h2><p>The vehicle you registered with.</p></div></div></div>
        <dl class="acs-tiles">
            <div class="acs-tile"><dt>Vehicle type</dt><dd>{{ $courier->vehicle_type ?? '—' }}</dd></div>
            <div class="acs-tile"><dt>Plate number</dt><dd>{{ $courier->plate_number ?? '—' }}</dd></div>
        </dl>
    </section>
    @endif

    {{-- Registration details (direct couriers) --}}
    @if ($courier && ! $rider && ! $truck)
    <section class="acs-card acs-pad">
        <div class="acs-card-head"><div><span class="acs-icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5"/></svg></span>
            <div><h2>Registration details</h2><p>These can't be edited here. Contact the Logistics team if they need updating.</p></div></div></div>
        <dl class="acs-tiles">
            <div class="acs-tile"><dt>Full name</dt><dd>{{ $courier->fullName() ?: '—' }}</dd></div>
            <div class="acs-tile"><dt>Sex</dt><dd>{{ $courier->sex ?? '—' }}</dd></div>
            <div class="acs-tile"><dt>Age</dt><dd>{{ $courier->age ?? '—' }}</dd></div>
            <div class="acs-tile"><dt>Birthday</dt><dd>{{ optional($courier->birthday)->format('M d, Y') ?? '—' }}</dd></div>
            <div class="acs-tile wide"><dt>Address</dt><dd>{{ $courier->fullAddress() }}</dd></div>
        </dl>
    </section>
    @endif
</x-account-shell>
</x-courier-layout>
