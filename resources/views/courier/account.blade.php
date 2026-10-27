<x-courier-layout title="My Account" active="account">
@php
    $statusMap = [
        'approved'  => ['label' => 'Active',    'cls' => 'cx-pill-green'],
        'pending'   => ['label' => 'Pending',   'cls' => 'cx-pill-amber'],
        'rejected'  => ['label' => 'Rejected',  'cls' => 'cx-pill-red'],
        'suspended' => ['label' => 'Suspended', 'cls' => 'cx-pill-gray'],
    ];
    $st = $rider
        ? ($rider->isSuspended() ? $statusMap['suspended'] : ['label' => $rider->statusLabel(), 'cls' => 'cx-pill-green'])
        : ($statusMap[$courier->status ?? 'approved'] ?? $statusMap['pending']);
    $displayName = $courier?->fullName() ?: ($rider->full_name ?? $user->name);
    $riderFields = ['vehicle_type', 'plate_number', 'license_number', 'license_expiry', 'license_file', 'or_cr_file'];
    $riderFormOpen = collect($riderFields)->contains(fn ($f) => $errors->has($f));
    $expiry = $rider?->license_expiry;
    $expiryState = ! $expiry ? null : ($expiry->isPast() ? 'expired' : ($expiry->lte(now()->addDays(30)) ? 'soon' : null));
@endphp
<style>
    .ac-hero { overflow: hidden; margin-bottom: 20px; padding: 0; }
    .ac-cover { height: 110px; background: radial-gradient(120% 160% at 100% 0, #0b4a7a 0, var(--navy) 60%);
                position: relative; }
    .ac-cover::after { content: ""; position: absolute; inset: 0; opacity: .18;
                       background-image: radial-gradient(rgba(255,255,255,.55) 1px, transparent 1px); background-size: 18px 18px; }
    .ac-hero-body { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 20px; padding: 14px 24px 20px; position: relative; }
    .ac-hero-body .cx-avatar-ring { align-self: flex-start; margin-top: -62px; }   /* only the photo overlaps the cover */
    .ac-hero-body .cx-avatar-circle { border: 4px solid #fff; box-shadow: 0 8px 24px -10px rgba(0,43,77,.45); }
    .ac-hero-id { min-width: 0; flex: 1; padding-bottom: 4px; }
    .ac-hero-id h2 { font-size: 22px; line-height: 1.15; }
    .ac-tags { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
    .ac-tags .cx-pill { white-space: nowrap; }
    /* Phones: photo above the name so the name and tags get the full width. */
    @media (max-width: 560px) {
        .ac-hero-body { flex-direction: column; align-items: flex-start; gap: 12px; padding: 14px 18px 18px; }
        .ac-hero-id { width: 100%; }
        .ac-hero-id h2 { font-size: 20px; }
        .ac-strip > div { padding: 12px 18px; }
        .ac-contact { padding: 14px 18px 18px; }
        .ac-card { padding: 18px; }
    }
    .ac-dot { width: 7px; height: 7px; border-radius: 999px; display: inline-block; }
    .ac-strip { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border-top: 1px solid var(--border); }
    @media (min-width: 900px) { .ac-strip { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ac-strip > div { padding: 14px 24px; border-left: 1px solid var(--border); }
    .ac-strip > div:first-child { border-left: 0; }
    @media (max-width: 899px) { .ac-strip > div:nth-child(3) { border-left: 0; } .ac-strip > div:nth-child(n+3) { border-top: 1px solid var(--border); } }
    .ac-strip dt { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); }
    .ac-strip dd { margin-top: 4px; font-family: 'Nunito', sans-serif; font-size: 18px; font-weight: 800; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ac-contact { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 16px 24px 20px; border-top: 1px solid var(--border); background: #fbfdfe; }
    .ac-contact > div { flex: 1; min-width: 220px; max-width: 360px; }

    .ac-layout { display: grid; gap: 20px; }
    @media (min-width: 1100px) { .ac-layout { grid-template-columns: minmax(0, 1fr) 340px; align-items: start; } }
    .ac-card { padding: 22px 24px; }
    .ac-card-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 16px; }
    .ac-card-head h3 { font-size: 16px; font-weight: 700; color: var(--text); }
    .ac-card-head p { margin-top: 2px; font-size: 12.5px; color: var(--text-muted); }
    /* Facts follow the card's own width: a tidy list (icon beside the text) when the card is narrow,
       three tiles (icon above) when there's room. */
    #rider-profile { container-type: inline-size; }
    .ac-facts { display: grid; gap: 10px; grid-template-columns: 1fr; }
    .ac-fact { border: 1px solid var(--border); border-radius: 14px; padding: 12px 14px; display: flex; flex-direction: row; align-items: center; gap: 12px; min-width: 0; }
    .ac-fact-icon { flex: none; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--accent-soft); color: var(--accent); }
    .ac-fact > div { min-width: 0; flex: 1; }
    .ac-fact dt { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ac-fact dd { margin-top: 2px; font-size: 15px; font-weight: 700; line-height: 1.3; color: var(--text); }
    .ac-fact dd small { display: block; margin-top: 2px; font-weight: 500; font-size: 12.5px; color: var(--text-muted); }
    @container (min-width: 640px) {
        .ac-facts { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .ac-fact { flex-direction: column; align-items: flex-start; gap: 10px; padding: 14px 16px; }
    }
    .ac-nowrap { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .ac-docs { display: grid; gap: 10px; margin-top: 12px; grid-template-columns: 1fr; }
    @container (min-width: 520px) { .ac-docs { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; } }
    .ac-doc { display: flex; align-items: center; gap: 12px; border: 1px dashed var(--border); border-radius: 14px; padding: 12px 14px; min-width: 0; }
    .ac-doc-name { font-size: 13.5px; font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ac-doc-state { font-size: 12px; color: var(--text-muted); }
    .ac-edit { margin-top: 18px; border-top: 1px solid var(--border); padding-top: 14px; }
    .ac-edit summary { list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 700; color: var(--accent); }
    .ac-edit summary::-webkit-details-marker { display: none; }
    .ac-edit summary .chev { transition: transform .15s; }
    .ac-edit[open] summary .chev { transform: rotate(90deg); }
    .ac-dl { display: grid; gap: 14px; }
    .ac-dl dt { font-size: 11.5px; color: var(--text-muted); }
    .ac-dl dd { margin-top: 2px; font-size: 14px; font-weight: 600; color: var(--text); }
    .ac-side-btn { display: flex; width: 100%; align-items: center; gap: 10px; border-radius: 12px; padding: 12px 14px; border: 1px solid var(--border);
                   font-size: 13.5px; font-weight: 700; color: var(--text); background: #fff; cursor: pointer; text-align: left; }
    .ac-side-btn:hover { border-color: var(--accent); }
    .ac-side-btn small { display: block; font-weight: 500; font-size: 12px; color: var(--text-muted); }
</style>

@if ($errors->any() && ! $riderFormOpen)
    <div class="cx-alert cx-alert-error" style="margin-bottom:20px;">
        <ul style="list-style:disc;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Profile header: photo, name, tags, numbers, contact number --}}
<form action="{{ route('courier.account.update') }}" method="POST" enctype="multipart/form-data" class="card ac-hero">
    @csrf @method('PUT')
    <div class="ac-cover"></div>
    <div class="ac-hero-body">
        <label class="cx-avatar-ring" for="cx-avatar-input" title="Change photo">
            <span class="cx-avatar-circle">
                @if ($user->profile_photo_path)
                    <img data-avatar-preview class="cx-avatar-img" src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="Profile photo">
                @else
                    <span data-avatar-initial class="cx-avatar-initial">{{ strtoupper(substr($courier->first_name ?? $displayName, 0, 1)) }}</span>
                @endif
            </span>
            <span class="cx-avatar-overlay">@include('courier.partials.icon', ['name' => 'camera', 'size' => 22])</span>
        </label>
        <input id="cx-avatar-input" data-avatar-input type="file" name="avatar" accept="image/jpg,image/jpeg,image/png,image/webp" style="display:none;">

        <div class="ac-hero-id">
            <h2 class="font-display">{{ $displayName }}</h2>
            <p style="margin-top:2px;font-size:13.5px;color:var(--text-muted);">{{ $maskedEmail }}</p>
            <div class="ac-tags">
                <span class="cx-pill cx-pill-navy">{{ $rider ? 'Rider' : 'Courier' }}</span>
                <span class="cx-pill {{ $st['cls'] }}" data-rider-status>{{ $st['label'] }}</span>
                @if ($rider)
                    <span class="cx-pill cx-pill-blue">{{ $rider->companyName() }}</span>
                    <span class="cx-pill {{ $stats['online'] ? 'cx-pill-green' : 'cx-pill-gray' }}" data-online>
                        <span class="ac-dot" style="background:{{ $stats['online'] ? 'var(--green)' : '#9ca3af' }};"></span>{{ $stats['online'] ? 'Online' : 'Offline' }}
                    </span>
                @endif
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;padding-bottom:4px;">
            <label for="cx-avatar-input" class="cx-btn cx-btn-outline cx-btn-sm" style="cursor:pointer;">
                @include('courier.partials.icon', ['name' => 'camera', 'size' => 14, 'sw' => 2]) Change photo
            </label>
        </div>
        <span data-avatar-filename style="flex-basis:100%;margin-top:-8px;font-size:11px;color:#9db3c4;"></span>
    </div>

    @if ($rider)
        <dl class="ac-strip">
            <div><dt>Total earned</dt><dd data-account-earned>₱{{ number_format($stats['earned'], 2) }}</dd></div>
            <div><dt>Paid jobs</dt><dd>{{ $stats['jobs'] }}</dd></div>
            <div><dt>Rider since</dt><dd>{{ $rider->approved_at?->format('M Y') ?? '—' }}</dd></div>
            <div><dt>Area</dt><dd title="{{ $rider->area?->name }}">{{ $rider->area?->name ?? '—' }}</dd></div>
        </dl>
    @endif

    <div class="ac-contact">
        <div>
            <label class="cx-label" for="contact_no">Contact number</label>
            <input type="text" id="contact_no" name="contact_no" value="{{ old('contact_no', $courier->contact_no ?? $rider->phone ?? $user->phone) }}" class="input cx-field-mt" required maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
        </div>
        <button type="submit" class="cx-btn cx-btn-orange" style="height:42px;">Save changes</button>
        <p style="flex-basis:100%;font-size:11.5px;color:var(--text-muted);">Your sorting center and buyers use this number to reach you during a delivery. A new photo is saved with this button too.</p>
    </div>
</form>

<div class="ac-layout">
    <div style="display:grid;gap:20px;">
        {{-- Rider profile: vehicle, license and documents --}}
        @if ($rider)
        <section class="card ac-card" id="rider-profile" style="scroll-margin-top:90px;">
            <div class="ac-card-head">
                <div>
                    <h3>Rider profile</h3>
                    <p>Changing the plate number or OR/CR sends them to {{ $rider->companyName() }} for re-verification.</p>
                </div>
            </div>

            @if ($rider->needs_reverification)
                <p class="cx-alert" style="margin-bottom:14px;background:var(--amber-soft);color:var(--amber);">Your new vehicle details are waiting for {{ $rider->companyName() }} to re-verify them.</p>
            @endif
            @if ($expiryState)
                <p class="cx-alert {{ $expiryState === 'expired' ? 'cx-alert-error' : '' }}" style="margin-bottom:14px;{{ $expiryState === 'soon' ? 'background:var(--amber-soft);color:var(--amber);' : '' }}" data-license-warning>
                    {{ $expiryState === 'expired' ? 'Your driver\'s license has expired. Renew it and update the details below.' : 'Your driver\'s license expires on ' . $expiry->format('M j, Y') . '. Renew it soon and update the details below.' }}
                </p>
            @endif

            <dl class="ac-facts">
                <div class="ac-fact">
                    <span class="ac-fact-icon">@include('courier.partials.icon', ['name' => 'parcel', 'size' => 17])</span>
                    <div><dt>Sorting center</dt><dd>{{ $rider->companyName() }}<small>{{ $rider->area?->name ? 'Area: ' . $rider->area->name : 'No area set' }}</small></dd></div>
                </div>
                <div class="ac-fact">
                    <span class="ac-fact-icon">@include('courier.partials.icon', ['name' => 'truck', 'size' => 17])</span>
                    <div><dt>Vehicle</dt><dd>{{ $rider->vehicle_type ?? '—' }}<small>Plate <span class="ac-nowrap">{{ $rider->plate_number ?? '—' }}</span></small></dd></div>
                </div>
                <div class="ac-fact">
                    <span class="ac-fact-icon">@include('courier.partials.icon', ['name' => 'user', 'size' => 17])</span>
                    <div><dt>Driver's license</dt><dd data-name="{{ $rider->full_name }}"><span class="ac-nowrap">{{ $rider->license_number ?? '—' }}</span><small>Expires {{ $expiry?->format('m/d/Y') ?? '—' }}</small></dd></div>
                </div>
            </dl>

            <div class="ac-docs">
                @foreach ([['license', "Driver's license", $rider->license_path], ['or-cr', 'OR/CR', $rider->or_cr_path]] as [$key, $label, $path])
                    <div class="ac-doc">
                        <span class="ac-fact-icon" style="{{ $path ? 'background:var(--green-soft);color:var(--green);' : 'background:#f3f4f6;color:#9ca3af;' }}">
                            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h4"/></svg>
                        </span>
                        <div style="flex:1;min-width:0;">
                            <p class="ac-doc-name">{{ $label }}</p>
                            <p class="ac-doc-state">{{ $path ? 'Uploaded' : 'Not uploaded yet' }}</p>
                        </div>
                        @if ($path)<a href="{{ route('courier.rider.document', [$rider->id, $key]) }}" target="_blank" class="cx-btn cx-btn-outline cx-btn-sm">View</a>@endif
                    </div>
                @endforeach
            </div>

            <details class="ac-edit" @if ($riderFormOpen) open @endif>
                <summary><svg class="chev" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>Update vehicle &amp; license</summary>
                <form method="POST" action="{{ route('courier.rider.profile.update') }}" enctype="multipart/form-data" style="margin-top:14px;">
                    @csrf @method('PUT')
                    <div class="cx-grid cx-cols-2">
                        <div>
                            <label class="cx-label" for="vehicle_type">Vehicle</label>
                            <select id="vehicle_type" name="vehicle_type" class="input cx-field-mt">
                                @foreach ($vehicles as $v)<option @selected(old('vehicle_type', $rider->vehicle_type) === $v)>{{ $v }}</option>@endforeach
                            </select>
                            @error('vehicle_type')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="cx-label" for="plate_number">Plate number</label>
                            <input id="plate_number" name="plate_number" maxlength="12" class="input cx-field-mt" value="{{ old('plate_number', $rider->plate_number) }}" style="text-transform:uppercase;">
                            @error('plate_number')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="cx-label" for="license_number">Driver's license number</label>
                            <input id="license_number" name="license_number" maxlength="30" class="input cx-field-mt" value="{{ old('license_number', $rider->license_number) }}">
                            @error('license_number')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="cx-label" for="license_expiry">License expiry</label>
                            <input type="date" id="license_expiry" name="license_expiry" min="{{ now()->toDateString() }}" class="input cx-field-mt" value="{{ old('license_expiry', $expiry?->toDateString()) }}">
                            @error('license_expiry')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="cx-label" for="license_file">New driver's license photo <span style="font-weight:400;">(JPG or PNG, up to 5 MB)</span></label>
                            <input type="file" id="license_file" name="license_file" accept="image/jpeg,image/png" class="input cx-field-mt">
                            @error('license_file')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="cx-label" for="or_cr_file">New OR/CR <span style="font-weight:400;">(PDF, JPG or PNG, up to 5 MB)</span></label>
                            <input type="file" id="or_cr_file" name="or_cr_file" accept="application/pdf,image/jpeg,image/png" class="input cx-field-mt">
                            @error('or_cr_file')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button class="cx-btn cx-btn-orange" style="margin-top:16px;">Save rider profile</button>
                </form>
            </details>
        </section>
        @elseif ($courier)
        <section class="card ac-card">
            <div class="ac-card-head"><div><h3>Vehicle</h3><p>The vehicle you registered with.</p></div></div>
            <dl class="ac-dl" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                <div><dt>Vehicle type</dt><dd>{{ $courier->vehicle_type ?? '—' }}</dd></div>
                <div><dt>Plate number</dt><dd>{{ $courier->plate_number ?? '—' }}</dd></div>
            </dl>
        </section>
        @endif
    </div>

    <aside style="display:grid;gap:20px;">
        @if ($courier)
        <section class="card ac-card">
            <div class="ac-card-head"><div><h3>Personal details</h3><p>From your registration.</p></div></div>
            <dl class="ac-dl">
                <div><dt>Full name</dt><dd>{{ $courier->fullName() ?: '—' }}</dd></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div><dt>Sex</dt><dd>{{ $courier->sex ?? '—' }}</dd></div>
                    <div><dt>Age</dt><dd>{{ $courier->age ?? '—' }}</dd></div>
                </div>
                <div><dt>Birthday</dt><dd>{{ optional($courier->birthday)->format('M d, Y') ?? '—' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $courier->fullAddress() }}</dd></div>
            </dl>
            <p style="margin-top:14px;font-size:11.5px;color:var(--text-muted);">These can't be edited here. Contact the Logistics team if they need updating.</p>
        </section>
        @endif

        <section class="card ac-card">
            <div class="ac-card-head"><div><h3>Account &amp; security</h3><p>Password and sign-in.</p></div></div>
            <div style="display:grid;gap:10px;">
                <a href="{{ route('courier.account.security') }}" class="ac-side-btn">
                    <span class="ac-fact-icon" style="background:rgba(0,43,77,.08);color:var(--navy);">@include('courier.partials.icon', ['name' => 'lock', 'size' => 16, 'sw' => 2])</span>
                    <span>Security settings<small>Change your password</small></span>
                </a>
                <form action="{{ \App\Support\Portal::route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="ac-side-btn" style="color:var(--red);">
                        <span class="ac-fact-icon" style="background:var(--red-soft);color:var(--red);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9M12 21H5a2 2 0 01-2-2V5a2 2 0 012-2h7"/></svg>
                        </span>
                        <span>Log out<small style="color:var(--text-muted);">Sign out of the courier portal</small></span>
                    </button>
                </form>
            </div>
        </section>
    </aside>
</div>

@push('scripts')
<script src="{{ asset('js/courier-account.js') }}"></script>
@endpush

</x-courier-layout>
