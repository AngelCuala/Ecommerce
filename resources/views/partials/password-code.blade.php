{{-- A password change waiting for the code emailed to the account (see PasswordChangeController). --}}
@if (auth()->user()?->pending_password)
    <div id="password-code" style="margin-bottom:20px;padding:18px 20px;border:1px solid #FED7AA;background:#FFF7ED;border-radius:10px;color:#7C2D12;">
        <p style="margin:0 0 4px;font-weight:700;font-size:15px;">Confirm your new password</p>
        <p style="margin:0 0 12px;font-size:13px;line-height:1.5;">
            @php($pcTo = \App\Http\Controllers\PasswordChangeController::sentTo(auth()->user()))
            We sent a 6-digit code {{ $pcTo['via'] === 'sms' ? 'by text' : '' }} to <strong>{{ $pcTo['to'] }}</strong>. Your new password is used only after you enter it; until then your current password keeps working.
        </p>
        <form method="POST" action="{{ route('password.change.verify') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;">
            @csrf
            <div style="flex:1 1 180px;">
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required
                       oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)" placeholder="6-digit code" aria-label="6-digit code"
                       style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid {{ $errors->has('code') ? '#DC2626' : '#FDBA74' }};border-radius:8px;font-size:16px;letter-spacing:4px;background:#fff;color:#111;">
                @error('code')<p style="margin:6px 0 0;font-size:12px;color:#B91C1C;">{{ $message }}</p>@enderror
            </div>
            <button type="submit" style="padding:10px 18px;border:0;border-radius:8px;background:#fa4e1c;color:#fff;font-weight:700;cursor:pointer;">Confirm</button>
        </form>
        <div style="display:flex;gap:16px;margin-top:10px;font-size:13px;">
            <form method="POST" action="{{ route('password.change.resend') }}">@csrf
                <button type="submit" style="background:none;border:0;padding:0;color:#C2410C;font-weight:600;cursor:pointer;text-decoration:underline;">Send a new code</button>
            </form>
            <form method="POST" action="{{ route('password.change.cancel') }}">@csrf
                <button type="submit" style="background:none;border:0;padding:0;color:#78716C;cursor:pointer;text-decoration:underline;">Cancel the change</button>
            </form>
        </div>
    </div>
@endif
