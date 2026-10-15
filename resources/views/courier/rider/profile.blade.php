<x-courier-layout title="Rider Profile" active="rider-profile">
<div class="cx-page-head">
    <h1 class="font-display">Rider Profile</h1>
    <p>Your vehicle and documents. Changing the plate or OR/CR sends them to {{ $rider->companyName() }} for re-verification.</p>
</div>

<div class="card" style="padding:18px 20px;margin-bottom:20px;">
    <dl class="cx-dl">
        <dt>Name</dt><dd data-name>{{ $rider->full_name }}</dd>
        <dt>License</dt><dd>{{ $rider->license_number ?? '—' }} · expires {{ $rider->license_expiry?->format('m/d/Y') ?? '—' }}</dd>
        <dt>Vehicle</dt><dd>{{ $rider->vehicle_type ?? '—' }} · plate {{ $rider->plate_number ?? '—' }}</dd>
        <dt>Company</dt><dd>{{ $rider->companyName() }}</dd>
        <dt>Status</dt><dd>{{ $rider->statusLabel() }}@if($rider->needs_reverification) · <span class="cx-pill cx-pill-amber">Vehicle re-verification pending</span>@endif</dd>
        <dt>Documents</dt><dd>
            @if($rider->license_path)<a href="{{ route('courier.rider.document', [$rider->id, 'license']) }}" target="_blank">Driver's license</a>@else No license uploaded @endif
            ·
            @if($rider->or_cr_path)<a href="{{ route('courier.rider.document', [$rider->id, 'or-cr']) }}" target="_blank">OR/CR</a>@else No OR/CR uploaded @endif
        </dd>
    </dl>
</div>

<form method="POST" action="{{ route('courier.rider.profile.update') }}" enctype="multipart/form-data" class="card" style="padding:18px 20px;">
    @csrf @method('PUT')
    <div class="cx-grid cx-cols-2">
        <div>
            <label class="cx-label" for="vehicle_type">Vehicle</label>
            <select id="vehicle_type" name="vehicle_type" class="input">
                @foreach($vehicles as $v)<option @selected(old('vehicle_type', $rider->vehicle_type) === $v)>{{ $v }}</option>@endforeach
            </select>
            @error('vehicle_type')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="cx-label" for="plate_number">Plate number</label>
            <input id="plate_number" name="plate_number" maxlength="12" class="input" value="{{ old('plate_number', $rider->plate_number) }}" style="text-transform:uppercase;">
            @error('plate_number')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="cx-label" for="license_number">Driver's license number</label>
            <input id="license_number" name="license_number" maxlength="30" class="input" value="{{ old('license_number', $rider->license_number) }}">
            @error('license_number')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="cx-label" for="license_expiry">License expiry</label>
            <input type="date" id="license_expiry" name="license_expiry" min="{{ now()->toDateString() }}" class="input" value="{{ old('license_expiry', $rider->license_expiry?->toDateString()) }}">
            @error('license_expiry')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="cx-label" for="license_file">Driver's license photo (JPG or PNG, up to 5 MB)</label>
            <input type="file" id="license_file" name="license_file" accept="image/jpeg,image/png" class="input">
            @error('license_file')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="cx-label" for="or_cr_file">OR/CR (PDF, JPG or PNG, up to 5 MB)</label>
            <input type="file" id="or_cr_file" name="or_cr_file" accept="application/pdf,image/jpeg,image/png" class="input">
            @error('or_cr_file')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
        </div>
    </div>
    <button class="cx-btn cx-btn-orange" style="margin-top:16px;">Save</button>
</form>
</x-courier-layout>
