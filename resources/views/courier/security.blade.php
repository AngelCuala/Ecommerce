<x-courier-layout title="Security Settings" active="account">

<a href="{{ route('courier.account') }}" class="cx-link-back" style="margin-bottom:16px;">
    @include('courier.partials.icon', ['name' => 'back', 'size' => 16, 'sw' => 2.2]) Back to Account
</a>

<div class="cx-page-head">
    <h1 class="font-display">Security Settings</h1>
    <p>Manage your password.</p>
</div>

@if ($errors->any())
    <div class="cx-alert cx-alert-error" style="margin-bottom:24px;">
        <ul style="list-style:disc;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card" style="padding:24px;">
    <h2 class="cx-section-title" style="margin-bottom:4px;">Change Password</h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:20px;">Choose a strong password you don't use elsewhere. Minimum 8 characters.</p>

    <form action="{{ route('courier.account.password') }}" method="POST" style="display:flex;flex-direction:column;gap:16px;">
        @csrf @method('PUT')

        <div>
            <label class="cx-label">Current Password</label>
            <div class="cx-pw-wrap cx-field-mt">
                <input type="password" name="current_password" data-password class="input" style="padding-right:44px;" placeholder="••••••••" required autocomplete="current-password">
                <button type="button" data-toggle-password aria-label="Show password" class="cx-pw-toggle"></button>
            </div>
        </div>

        <div class="cx-grid cx-cols-2">
            <div>
                <label class="cx-label">New Password</label>
                <div class="cx-pw-wrap cx-field-mt">
                    <input type="password" name="password" data-password class="input" style="padding-right:44px;" placeholder="Minimum 8 characters" minlength="8" required autocomplete="new-password">
                    <button type="button" data-toggle-password aria-label="Show password" class="cx-pw-toggle"></button>
                </div>
            </div>
            <div>
                <label class="cx-label">Confirm New Password</label>
                <div class="cx-pw-wrap cx-field-mt">
                    <input type="password" name="password_confirmation" data-password class="input" style="padding-right:44px;" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                    <button type="button" data-toggle-password aria-label="Show password" class="cx-pw-toggle"></button>
                </div>
            </div>
        </div>

        <div>
            <button type="submit" class="cx-btn cx-btn-navy">Update Password</button>
        </div>
    </form>
</div>

@include('partials.password-toggle')

</x-courier-layout>
