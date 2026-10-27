<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate your ALVY account</title>
    <style>
        body { margin:0; padding:0; background:#F5F5F5; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; color:#222222; }
        .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .header  { background:#fa4e1c; padding:32px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; font-weight:800; color:#ffffff; letter-spacing:-.3px; }
        .header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.85); }
        .body    { padding:36px 40px; }
        .body h2 { margin:0 0 12px; font-size:19px; font-weight:700; color:#222222; }
        .body p  { margin:0 0 14px; font-size:14px; line-height:1.65; color:#555555; }
        .code    { margin:24px 0; padding:18px; text-align:center; background:#fff5f3; border:1px solid #FFE3CC; border-radius:6px; font-size:34px; font-weight:800; letter-spacing:10px; color:#fa4e1c; }
        .btn     { display:inline-block; margin:18px 0; padding:14px 28px; background:#fa4e1c; color:#ffffff !important; text-decoration:none; border-radius:6px; font-weight:700; font-size:15px; }
        .link    { word-break:break-all; font-size:12px; color:#8a7a70; }
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
        <p><strong>{{ $company }}</strong> added you to ALVY. Set your password to activate your account, then log in to the ALVY app with <strong>{{ $user->email }}</strong>.</p>

        <p style="text-align:center"><a class="btn" href="{{ $link }}">Set my password</a></p>

        <p>This link works once and expires in <strong>{{ $hours }} hours</strong>. If it expires, ask {{ $company }} to send a new one.</p>
        <p class="link">If the button doesn't work, open this link on your phone: {{ $link }}</p>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} ALVY. If you weren't expecting this email, you can ignore it.
    </div>
</div>
</body>
</html>
