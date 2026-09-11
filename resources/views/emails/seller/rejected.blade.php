<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Application Update</title>
    <style>
        body { margin:0; padding:0; background:#F5F5F5; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; color:#222222; }
        .wrapper { max-width:580px; margin:40px auto; background:#fff; border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        .header  { background:#fa4e1c; padding:32px 40px; text-align:center; }
        .header h1 { margin:0; font-size:22px; font-weight:800; color:#ffffff; letter-spacing:-.3px; }
        .header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.85); }
        .badge   { display:inline-block; margin:24px 0 0; background:#FEF2F2; color:#DC2626; border-radius:3px; padding:8px 22px; font-size:13px; font-weight:800; letter-spacing:.03em; border:1px solid rgba(220,38,38,.2); }
        .body    { padding:36px 40px; }
        .body h2 { margin:0 0 12px; font-size:19px; font-weight:700; color:#222222; }
        .body p  { margin:0 0 14px; font-size:14px; line-height:1.65; color:#555555; }
        .reason-box { background:#FEF2F2; border:1px solid rgba(220,38,38,.2); border-radius:4px; padding:18px 22px; margin:18px 0; }
        .reason-box p { margin:0; font-size:13px; color:#B91C1C; line-height:1.6; }
        .info-box { background:#fff5f3; border:1px solid #FFE3CC; border-radius:4px; padding:18px 22px; margin:18px 0; }
        .info-box p { margin:0 0 8px; font-size:13px; color:#555555; }
        .info-box p:last-child { margin:0; }
        .info-box strong { color:#222222; }
        .btn-wrap { text-align:center; margin:26px 0 8px; }
        .btn { display:inline-block; background:#fa4e1c; color:#ffffff; text-decoration:none; border-radius:3px; padding:13px 36px; font-size:14px; font-weight:700; }
        .footer  { background:#FAFAFA; padding:22px 40px; text-align:center; font-size:12px; color:#6b90aa; border-top:1px solid #dce8f0; }
        .footer a { color:#fa4e1c; text-decoration:none; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <h1>ALVY</h1>
        <p>Your online marketplace</p>
        <div class="badge">❌ APPLICATION NOT APPROVED</div>
    </div>

    <div class="body">
        <h2>Hi, {{ $application->full_name }}</h2>
        <p>
            Thank you for your interest in selling on ALVY. After reviewing your application for
            <strong>{{ $application->shop_name }}</strong>, we regret to inform you that we were
            unable to approve it at this time.
        </p>

        <div class="info-box">
            <p><strong>Shop Name:</strong> {{ $application->shop_name }}</p>
            <p><strong>Decision Date:</strong> {{ now()->format('F d, Y') }}</p>
        </div>

        @if ($application->rejection_reason)
            <p><strong>Reason for rejection:</strong></p>
            <div class="reason-box">
                <p>{{ $application->rejection_reason }}</p>
            </div>
        @endif

        <p>
            You're welcome to re-apply once you've addressed the reason above.
            Log in to your account and visit the <strong>Become a Seller</strong> page to submit a new application.
        </p>

        <div class="btn-wrap">
            <a href="{{ url('/become-seller') }}" class="btn">Re-apply as Seller →</a>
        </div>

        <p style="font-size:12px;color:#6b90aa;margin-top:22px;">
            If you believe this decision was made in error or have questions, please contact our support team.
        </p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} ALVY. All rights reserved.</p>
        <p><a href="{{ url('/') }}">Visit ALVY</a></p>
    </div>

</div>
</body>
</html>