<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your ALVY password</title>
    <style>
        body { margin:0; padding:0; background:#F5F5F5; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; color:#222222; }
        .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .header  { background:#fa4e1c; padding:32px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; font-weight:800; color:#ffffff; letter-spacing:-.3px; }
        .header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.85); }
        .body    { padding:36px 40px; }
        .body h2 { margin:0 0 12px; font-size:19px; font-weight:700; color:#222222; }
        .body p  { margin:0 0 14px; font-size:14px; line-height:1.65; color:#555555; }
        .cta     { margin:26px 0; text-align:center; }
        .cta a   { display:inline-block; padding:14px 34px; background:#fa4e1c; color:#ffffff !important; font-size:15px; font-weight:700; text-decoration:none; border-radius:6px; }
        .link    { word-break:break-all; font-size:12px; color:#8a7a70; }
        .note    { margin-top:22px; padding:14px 16px; background:#fff5f3; border:1px solid #FFE3CC; border-radius:6px; font-size:13px; color:#555555; }
        .footer  { background:#FAFAFA; padding:22px 40px; text-align:center; font-size:12px; color:#8a7a70; border-top:1px solid #F0E4DC; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>ALVY</h1>
        <p>Your online marketplace</p>
    </div>

    <div class="body">
        <h2>Hi {{ $user->first_name ?: $user->name }},</h2>
        <p>We received a request to reset the password for the ALVY account linked to <strong>{{ $user->email }}</strong>.</p>
        <p>Click the button below to choose a new password:</p>

        <div class="cta"><a href="{{ $url }}">Reset Password</a></div>

        <p>This link expires in <strong>{{ $minutes }} minutes</strong> and can only be used once.</p>
        <p class="link">If the button doesn't work, copy this link into your browser:<br>{{ $url }}</p>

        <div class="note">
            <strong>Didn't ask for this?</strong> You can safely ignore this email. Your password won't change unless you open
            the link and set a new one. ALVY will never ask for your password by email, chat or phone.
        </div>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} ALVY. This email was sent because a password reset was requested for your account.
    </div>
</div>
</body>
</html>
