<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Application Update</title>
    <style>
        body { margin:0; padding:0; background:#F5F5F5; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; color:#222222; }
        .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .header  { background:#002b4d; padding:32px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; font-weight:800; color:#ffffff; letter-spacing:-.3px; }
        .header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.85); }
        .badge   { display:inline-block; margin:24px 0 0; background:#ffffff; color:#DC2626; border-radius:3px; padding:8px 22px; font-size:13px; font-weight:800; letter-spacing:.03em; }
        .body    { padding:36px 40px; }
        .body h2 { margin:0 0 12px; font-size:19px; font-weight:700; color:#222222; }
        .body p  { margin:0 0 14px; font-size:14px; line-height:1.65; color:#555555; }
        .reason-box { background:#FEF2F2; border:1px solid #FECACA; border-radius:4px; padding:18px 22px; margin:18px 0; }
        .reason-box p { margin:0; font-size:13px; color:#555555; }
        .reason-box strong { color:#DC2626; }
        .btn-wrap { text-align:center; margin:26px 0 8px; }
        .btn { display:inline-block; background:#002b4d; color:#ffffff; text-decoration:none; border-radius:3px; padding:13px 36px; font-size:14px; font-weight:700; }
        .footer  { background:#FAFAFA; padding:22px 40px; text-align:center; font-size:12px; color:#6b90aa; border-top:1px solid #dce8f0; }
        .footer a { color:#fa4e1c; text-decoration:none; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <h1>ALVY</h1>
        <p>Logistics &amp; Delivery</p>
        <div class="badge">APPLICATION UPDATE</div>
    </div>

    <div class="body">
        <h2>Hello, {{ $courier->fullName() }}</h2>
        <p>
            Thank you for your interest in becoming an ALVY courier. After reviewing your application,
            we're unable to approve it at this time.
        </p>

        @if ($courier->rejection_reason)
            <div class="reason-box">
                <p><strong>Reason:</strong> {{ $courier->rejection_reason }}</p>
            </div>
        @endif

        <p>
            You're welcome to review the details above and submit a new application. Make sure your
            documents (OR/CR and a valid ID or driver's license) are clear and up to date.
        </p>

        <div class="btn-wrap">
            <a href="{{ url('/become-courier') }}" class="btn">Re-apply →</a>
        </div>

        <p style="font-size:12px;color:#6b90aa;margin-top:22px;">
            If you believe this was a mistake, reply to this email or contact the ALVY help center.
        </p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} ALVY. All rights reserved.</p>
        <p><a href="{{ url('/') }}">Visit ALVY</a></p>
    </div>

</div>
</body>
</html>
