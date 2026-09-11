<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LogiSort — Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0d1117;
            color: #e6edf3;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 14px;
        }

        .wrap {
            width: 100%;
            max-width: 380px;
            padding: 0 20px;
        }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 36px;
        }

        .brand__avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #1c2333;
            border: 1px solid #30363d;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .brand__name {
            font-size: 20px;
            font-weight: 700;
            color: #e6edf3;
        }

        .brand__sub {
            font-size: 12px;
            color: #8b949e;
            margin-top: 3px;
        }

        /* Card */
        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 28px 28px 24px;
        }

        .card__title {
            font-size: 15px;
            font-weight: 600;
            color: #e6edf3;
            margin-bottom: 20px;
        }

        /* Fields */
        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #8b949e;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
        }

        .field input {
            width: 100%;
            padding: 9px 12px;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            color: #e6edf3;
            font-size: 13.5px;
            font-family: inherit;
            outline: none;
            transition: border-color .15s;
        }

        .field input:focus { border-color: #58a6ff; }
        .field input::placeholder { color: #484f58; }

        /* Remember me */
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .remember input { width: auto; accent-color: #58a6ff; }
        .remember label {
            font-size: 12.5px;
            color: #8b949e;
            font-weight: 400;
            text-transform: none;
            letter-spacing: 0;
        }

        /* Submit */
        .btn-submit {
            width: 100%;
            padding: 10px;
            background: #238636;
            border: 1px solid #2ea043;
            border-radius: 6px;
            color: #e6edf3;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }

        .btn-submit:hover { background: #2ea043; }

        /* Error */
        .error {
            background: rgba(248, 81, 73, .1);
            border: 1px solid rgba(248, 81, 73, .3);
            border-left: 3px solid #f85149;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 13px;
            color: #f85149;
            margin-bottom: 18px;
        }

        /* Success */
        .success-msg {
            background: rgba(57, 211, 83, .1);
            border: 1px solid rgba(57, 211, 83, .3);
            border-left: 3px solid #39d353;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 13px;
            color: #39d353;
            margin-bottom: 18px;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12.5px;
            color: #8b949e;
        }

        .login-footer a { color: #58a6ff; text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="wrap">

    <div class="brand">
        <div class="brand__avatar">📦</div>
        <div class="brand__name">LogiSort</div>
        <div class="brand__sub">Sorting Center Management Portal</div>
    </div>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @if (session('success'))
        <div class="success-msg">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card__title">Sign in to your account</div>

        <form action="{{ route('sorting-center.login.store') }}" method="POST">
            @csrf

            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       placeholder="you@example.com"
                       required autofocus autocomplete="email">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••"
                       required autocomplete="current-password">
            </div>

            <div class="remember">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
        </form>
    </div>

    <div class="login-footer">
        <a href="{{ route('home') }}">← Back to store</a>
    </div>

</div>

</body>
</html>
