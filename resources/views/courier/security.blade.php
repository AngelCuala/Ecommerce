<x-courier-layout title="Security Settings" active="account">

@php
    $me = auth()->user();
    $meRider = \App\Models\Rider::where('user_id', $me->id)->first();
    $meTruck = $meRider ? $meRider->isTruck() : (bool) \App\Http\Controllers\Courier\TruckDutyController::truckCourier();
@endphp
<x-account-shell title="Accounts & Security" subtitle="Change the password you sign in with." portal="ALVY {{ $meRider ? 'Rider' : 'Courier' }}"
                 :nav="\App\Support\AccountNav::rider('security', $meTruck)" :name="$meRider->full_name ?? $me->name"
                 :badges="[['text' => $meRider ? ($meTruck ? 'Courier · truck' : 'Rider') : 'Courier']]">

@if ($errors->any())
    <div class="acs-alert acs-alert-error">
        <ul style="list-style:disc;padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<section class="acs-card acs-pad">
    <div class="acs-card-head">
        <div>
            <span class="acs-icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
            <div><h2>Change Password</h2><p>Choose a strong password you don't use elsewhere. Minimum 8 characters.</p></div>
        </div>
    </div>

    @include('partials.password-code')
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
            @include('partials.password-code-via')
            <button type="submit" class="acs-btn">Update Password</button>
        </div>
    </form>
</section>
</x-account-shell>

@include('partials.password-toggle')

</x-courier-layout>
