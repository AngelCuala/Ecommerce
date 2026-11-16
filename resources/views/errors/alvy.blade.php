{{-- ALVY's error page (403, 404, 500, 503): says what happened and gives a way back, instead of the bare framework page. --}}
@php
    $home = url('/');
    $homeLabel = 'Go to the shop';
    if (($withAccount ?? true)) {
        try {
            if ($me = auth()->user()) {
                [$home, $homeLabel] = match (\App\Support\Portal::home($me)) {
                    'admin'   => [route('admin.dashboard'), 'Go to the admin dashboard'],
                    'sc'      => [route('sc.dashboard'), 'Go to my LogiSort dashboard'],
                    'courier' => [route('courier.dashboard'), 'Go to my courier dashboard'],
                    'seller'  => [route('seller.dashboard'), 'Go to my Seller Centre'],
                    default   => [url('/'), 'Go to the shop'],
                };
            }
        } catch (\Throwable $e) {
            // An error page must never fail: fall back to the shop.
        }
    }
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — ALVY</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #FFF8F5; color: #222; }
        .card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #F1E6E0; border-radius: 16px; padding: 32px 28px; text-align: center;
                box-shadow: 0 8px 30px rgba(250, 78, 28, .08); }
        .logo { width: 52px; height: 52px; }
        .code { margin: 14px 0 0; font-size: 13px; font-weight: 700; letter-spacing: .12em; color: #fa4e1c; }
        h1 { margin: 6px 0 0; font-size: 22px; font-weight: 800; }
        p { margin: 10px 0 0; color: #6b625c; line-height: 1.5; font-size: 15px; }
        .actions { margin-top: 22px; display: flex; flex-direction: column; gap: 10px; }
        a.btn, button.btn { display: block; padding: 11px 16px; border-radius: 10px; font-weight: 700; font-size: 15px; text-decoration: none; cursor: pointer; font-family: inherit; }
        .primary { background: #fa4e1c; color: #fff; border: 0; }
        .primary:hover { background: #e0431a; }
        .ghost { background: #fff; color: #44403C; border: 1px solid #E7E5E4; }
        .ghost:hover { border-color: #fa4e1c; color: #fa4e1c; }
    </style>
</head>
<body>
    <main class="card">
        <img class="logo" src="{{ asset('images/logo-mark.png') }}" alt="ALVY">
        <p class="code">ERROR {{ $code }}</p>
        <h1>{{ $heading }}</h1>
        <p>{{ $text }}</p>
        <div class="actions">
            <a class="btn primary" href="{{ $home }}">{{ $homeLabel }}</a>
            <button type="button" class="btn ghost" onclick="history.length > 1 ? history.back() : location.href='{{ $home }}'">Go back</button>
        </div>
    </main>
</body>
</html>
