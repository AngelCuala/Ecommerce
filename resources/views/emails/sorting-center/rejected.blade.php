<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorting Center Registration Update</title>
    <style>
        body { margin:0; padding:0; background:#F5F5F5; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; color:#222222; }
        .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .header  { background:#002b4d; padding:32px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; font-weight:800; color:#ffffff; }
        .header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.85); }
        .body    { padding:36px 40px; }
        .body h2 { margin:0 0 12px; font-size:19px; font-weight:700; }
        .body p  { margin:0 0 14px; font-size:14px; line-height:1.65; color:#555555; }
        .info-box { background:#FEF2F2; border:1px solid rgba(220,38,38,.2); border-radius:4px; padding:18px 22px; margin:18px 0; }
        .info-box p { margin:0; font-size:13px; color:#555555; }
        .footer  { background:#FAFAFA; padding:22px 40px; text-align:center; font-size:12px; color:#6b90aa; border-top:1px solid #dce8f0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>ALVY · LogiSort</h1>
        <p>Logistics / Sorting Center Portal</p>
    </div>
    <div class="body">
        <h2>Hello {{ $application->fullName() }},</h2>
        <p>Thank you for registering <strong>{{ $application->business_name }}</strong>. After review, your registration was <strong style="color:#DC2626;">not approved</strong>.</p>
        <div class="info-box">
            <p><strong>Reason:</strong> {{ $application->rejection_reason }}</p>
        </div>
        <p>If you believe this was a mistake or can provide corrected documents, please contact the ALVY administrator.</p>
    </div>
    <div class="footer">&copy; {{ date('Y') }} ALVY. All rights reserved.</div>
</div>
</body>
</html>
