<x-courier-layout title="My Account" active="account">

@if ($errors->any())
    <div class="cx-alert cx-alert-error" style="margin-bottom:24px;">
        <ul style="list-style:disc;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="cx-page-head">
    <h1 class="font-display">My Account</h1>
    <p>Your personal, vehicle, and account information.</p>
</div>

@php
    $statusMap = [
        'approved'  => ['label'=>'Active','cls'=>'cx-pill-green'],
        'pending'   => ['label'=>'Pending','cls'=>'cx-pill-amber'],
        'rejected'  => ['label'=>'Rejected','cls'=>'cx-pill-red'],
        'suspended' => ['label'=>'Suspended','cls'=>'cx-pill-gray'],
    ];
    $st = $statusMap[$courier->status ?? 'pending'] ?? $statusMap['pending'];
@endphp

{{-- Identity + edit form --}}
<form action="{{ route('courier.account.update') }}" method="POST" enctype="multipart/form-data" class="card" style="padding:24px;margin-bottom:24px;">
    @csrf @method('PUT')

    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:20px;">
        <label class="cx-avatar-ring" for="cx-avatar-input">
            <span class="cx-avatar-circle">
                @if ($user->profile_photo_path)
                    <img data-avatar-preview class="cx-avatar-img" src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="Profile photo">
                @else
                    <span data-avatar-initial class="cx-avatar-initial">{{ strtoupper(substr($courier->first_name ?? $user->name, 0, 1)) }}</span>
                @endif
            </span>
            <span class="cx-avatar-overlay">@include('courier.partials.icon', ['name' => 'camera', 'size' => 22])</span>
        </label>

        <div style="min-width:0;">
            <h2 class="font-display" style="font-size:20px;">{{ $courier->fullName() ?: $user->name }}</h2>
            <p style="margin-top:2px;font-size:14px;color:var(--text-muted);">{{ $maskedEmail }}</p>
            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">
                <span class="cx-pill cx-pill-navy">Courier</span>
                <span class="cx-pill {{ $st['cls'] }}">{{ $st['label'] }}</span>
            </div>
            <div style="margin-top:12px;">
                <input id="cx-avatar-input" data-avatar-input type="file" name="avatar" accept="image/jpg,image/jpeg,image/png,image/webp" style="display:none;">
                <label for="cx-avatar-input" class="cx-btn cx-btn-outline cx-btn-sm" style="cursor:pointer;">
                    @include('courier.partials.icon', ['name' => 'camera', 'size' => 14, 'sw' => 2]) Change Photo
                </label>
                <span data-avatar-filename style="margin-left:8px;font-size:11px;color:#9db3c4;"></span>
            </div>
        </div>

        <a href="{{ route('courier.account.security') }}" class="cx-btn cx-btn-navy" style="margin-left:auto;">
            @include('courier.partials.icon', ['name' => 'lock', 'size' => 15, 'sw' => 2]) Security Settings
        </a>
    </div>

    <div class="cx-grid cx-cols-2" style="margin-top:24px;">
        <div>
            <label class="cx-label">Contact No. *</label>
            <input type="text" name="contact_no" value="{{ old('contact_no', $courier->contact_no ?? $user->phone) }}" class="input cx-field-mt" required>
        </div>
        <div style="display:flex;align-items:flex-end;">
            <button type="submit" class="cx-btn cx-btn-orange">Save Changes</button>
        </div>
    </div>
</form>

{{-- Read-only details --}}
<div class="card" style="padding:24px;margin-bottom:24px;">
    <h3 class="cx-section-title" style="margin-bottom:16px;">Account Details</h3>
    <dl class="cx-dl">
        <div><dt>Full Name</dt><dd>{{ $courier->fullName() ?: '—' }}</dd></div>
        <div><dt>Sex</dt><dd>{{ $courier->sex ?? '—' }}</dd></div>
        <div><dt>Birthday</dt><dd>{{ optional($courier->birthday)->format('M d, Y') ?? '—' }}</dd></div>
        <div><dt>Age</dt><dd>{{ $courier->age ?? '—' }}</dd></div>
        <div class="cx-span-2"><dt>Address</dt><dd>{{ $courier->fullAddress() }}</dd></div>
        <div><dt>Vehicle Type</dt><dd>{{ $courier->vehicle_type ?? '—' }}</dd></div>
        <div><dt>Plate Number</dt><dd>{{ $courier->plate_number ?? '—' }}</dd></div>
    </dl>
    <p style="margin-top:16px;font-size:11px;color:#9db3c4;">Registration details and verified documents can't be edited here. Contact the Logistics team if they need updating.</p>
</div>

{{-- Logout --}}
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="cx-btn cx-btn-danger-outline">Logout</button>
</form>

@push('scripts')
<script src="{{ asset('js/courier-account.js') }}"></script>
@endpush

</x-courier-layout>
