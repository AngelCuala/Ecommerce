<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorting Center Registration Approved</title>
    <style>
        body { margin:0; padding:0; background:#F5F5F5; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; color:#222222; }
        .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .header  { background:#fa4e1c; padding:32px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; font-weight:800; color:#ffffff; }
        .header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.85); }
        .badge   { display:inline-block; margin:24px 0 0; background:#ffffff; color:#fa4e1c; border-radius:3px; padding:8px 22px; font-size:13px; font-weight:800; }
        .body    { padding:36px 40px; }
        .body h2 { margin:0 0 12px; font-size:19px; font-weight:700; }
        .body p  { margin:0 0 14px; font-size:14px; line-height:1.65; color:#555555; }
        .info-box { background:#fff5f3; border:1px solid #FFE3CC; border-radius:4px; padding:18px 22px; margin:18px 0; }
        .info-box p { margin:0 0 8px; font-size:13px; }
        .btn-wrap { text-align:center; margin:26px 0 8px; }
        .btn { display:inline-block; background:#fa4e1c; color:#ffffff; text-decoration:none; border-radius:3px; padding:13px 36px; font-size:14px; font-weight:700; }
        .footer  { background:#FAFAFA; padding:22px 40px; text-align:center; font-size:12px; color:#6b90aa; border-top:1px solid #dce8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>ALVY · LogiSort</h1>
        <p>Logistics / Sorting Center Portal</p>
        <div class="badge">REGISTRATION APPROVED</div>
    </div>
    <div class="body">
        <h2>Good news, {{ $application->fullName() }}!</h2>
        <p>Your sorting center <strong>{{ $application->business_name }}</strong> has been <strong style="color:#059669;">approved</strong>.</p>
        <div class="info-box">
            <p><strong>Coverage municipality:</strong> {{ $application->municipality }}{{ $application->province ? ', ' . $application->province : '' }}</p>
            <p><strong>Approved on:</strong> {{ now()->format('F d, Y') }}</p>
        </div>
        <p>Sign in to set up your coverage barangays and riders, then start handling parcels.</p>
        <div class="btn-wrap"><a href="{{ route('sc.login') }}" class="btn">Sign in to LogiSort →</a></div>
    </div>
    <div class="footer">&copy; {{ date('Y') }} ALVY. All rights reserved.</div>
</div>
</body>
</html>
