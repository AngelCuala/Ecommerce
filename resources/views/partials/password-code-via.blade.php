{{-- Inside a password form: where the confirmation code goes. Shown only when SMS is set up and the account has a mobile number. --}}
@php($pcUser = auth()->user())
@if ($pcUser && \App\Http\Controllers\PasswordChangeController::smsPossible($pcUser))
    <fieldset style="border:0;margin:0 0 12px;padding:0;">
        <legend style="font-size:13px;font-weight:600;color:#44403C;margin-bottom:6px;">Send the confirmation code to</legend>
        <label style="display:flex;gap:8px;align-items:center;font-size:14px;margin-bottom:4px;cursor:pointer;">
            <input type="radio" name="code_via" value="email" {{ old('code_via', 'email') === 'email' ? 'checked' : '' }} style="accent-color:#fa4e1c;">
            Email · {{ $pcUser->email }}
        </label>
        <label style="display:flex;gap:8px;align-items:center;font-size:14px;cursor:pointer;">
            <input type="radio" name="code_via" value="sms" {{ old('code_via') === 'sms' ? 'checked' : '' }} style="accent-color:#fa4e1c;">
            Text message · {{ \App\Services\Sms::mask(\App\Services\Sms::numberOf($pcUser)) }}
        </label>
    </fieldset>
@endif
