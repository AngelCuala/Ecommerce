@php
    $confirmed = $remittance->status === 'confirmed';
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Remittance receipt {{ $remittance->receiptNumber() }} — ALVY</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f1ef; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; color: #1f2937; }
        .bar { max-width: 720px; margin: 20px auto 0; padding: 0 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
        .bar a, .bar button { font: inherit; font-size: 14px; font-weight: 600; border-radius: 8px; padding: 9px 14px; cursor: pointer; text-decoration: none; }
        .bar a { color: #57534e; background: #fff; border: 1px solid #e7e5e4; }
        .bar button { color: #fff; background: #fa4e1c; border: 0; }
        .flash { max-width: 720px; margin: 12px auto 0; padding: 10px 14px; border-radius: 8px; background: #ecfdf5; color: #047857; font-size: 14px; }
        .flash-wrap { padding: 0 16px; }
        .receipt { position: relative; max-width: 720px; margin: 14px auto 32px; background: #fff; border: 1px solid #e7e5e4; border-radius: 14px; padding: 28px; overflow: hidden; }
        .head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; border-bottom: 2px solid #002b4d; padding-bottom: 16px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand img { width: 40px; height: 40px; object-fit: contain; }
        .brand b { display: block; font-size: 22px; letter-spacing: .02em; color: #002b4d; }
        .brand span { font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #fa4e1c; }
        .doc { text-align: right; }
        .doc h1 { margin: 0; font-size: 15px; letter-spacing: .08em; text-transform: uppercase; color: #002b4d; }
        .doc .no { margin-top: 4px; font-family: ui-monospace, 'SF Mono', Consolas, monospace; font-size: 15px; font-weight: 700; }
        .status { display: inline-block; margin-top: 8px; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 800; letter-spacing: .06em; }
        .status.pending { background: #fffbeb; color: #b45309; border: 1.5px solid #f59e0b; }
        .status.confirmed { background: #ecfdf5; color: #047857; border: 1.5px solid #10b981; }
        .stamp { position: absolute; top: 46%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg); font-size: 44px; font-weight: 900; letter-spacing: .08em; white-space: nowrap; opacity: .09; pointer-events: none; }
        .stamp.pending { color: #b45309; }
        .stamp.confirmed { color: #047857; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; margin: 18px 0; }
        .grid dt { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #78716c; }
        .grid dd { margin: 3px 0 0; font-size: 14px; font-weight: 600; }
        .amount { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 16px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .amount span { font-size: 13px; font-weight: 700; color: #475569; }
        .amount strong { font-size: 26px; color: #002b4d; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 13px; }
        th { text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #78716c; border-bottom: 1px solid #e7e5e4; padding: 8px 6px; }
        td { border-bottom: 1px solid #f1efed; padding: 8px 6px; }
        td.r, th.r { text-align: right; }
        .mono { font-family: ui-monospace, 'SF Mono', Consolas, monospace; font-size: 12px; }
        tfoot td { font-weight: 700; border-bottom: 0; }
        .note { margin-top: 16px; font-size: 12px; color: #78716c; line-height: 1.5; }
        .short { color: #b91c1c; }
        @media (max-width: 560px) {
            .receipt { padding: 18px; }
            .head { flex-direction: column; }
            .doc { text-align: left; }
            .grid { grid-template-columns: 1fr; }
            .stamp { font-size: 30px; }
        }
        @media print {
            body { background: #fff; }
            .bar, .flash-wrap { display: none; }
            .receipt { border: 0; margin: 0 auto; max-width: none; }
            .stamp { opacity: .12; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <a href="{{ route('courier.rider.cod') }}">&larr; COD Remittance</a>
        <button type="button" onclick="window.print()" data-print>Print / Save as PDF</button>
    </div>
    @if (session('success'))
        <div class="flash-wrap"><div class="flash" role="status">{{ session('success') }}</div></div>
    @endif

    <main class="receipt" data-receipt="{{ $remittance->receiptNumber() }}" data-status="{{ $remittance->status }}">
        <div class="stamp {{ $confirmed ? 'confirmed' : 'pending' }}" aria-hidden="true">{{ $confirmed ? 'CONFIRMED' : 'PENDING CONFIRMATION' }}</div>

        <div class="head">
            <div class="brand">
                <img src="{{ asset('images/logo-mark.png') }}" alt="">
                <div><b>ALVY</b><span>Logistics</span></div>
            </div>
            <div class="doc">
                <h1>COD Remittance Receipt</h1>
                <div class="no">{{ $remittance->receiptNumber() }}</div>
                <div class="status {{ $confirmed ? 'confirmed' : 'pending' }}" data-receipt-status>{{ $confirmed ? 'CONFIRMED' : 'PENDING CONFIRMATION' }}</div>
            </div>
        </div>

        <dl class="grid">
            <div><dt>Rider</dt><dd>{{ $rider->full_name }}</dd></div>
            <div><dt>Remitted to</dt><dd>{{ $remittance->sortingCenter ? \App\Support\SortingCenters::displayName($remittance->sortingCenter) : $rider->companyName() }}</dd></div>
            <div><dt>Date and time</dt><dd>{{ $remittance->created_at->copy()->tz($tz)->format('M j, Y · g:i A') }}</dd></div>
            <div><dt>{{ $confirmed ? 'Confirmed' : 'Confirmation' }}</dt>
                <dd>{{ $confirmed && $remittance->confirmed_at
                        ? $remittance->confirmed_at->copy()->tz($tz)->format('M j, Y · g:i A') . ($remittance->confirmer ? ' by ' . \App\Support\SortingCenters::displayName($remittance->confirmer) : '')
                        : 'Waiting for the hub to count and confirm the cash' }}</dd></div>
        </dl>

        <div class="amount"><span>Amount remitted</span><strong data-receipt-amount>{{ $peso($remittance->amount) }}</strong></div>

        <table>
            <thead><tr><th>Delivered</th><th>Tracking number</th><th>Order</th><th class="r">COD collected</th></tr></thead>
            <tbody>
            @forelse ($deliveries as $d)
                <tr data-receipt-line="{{ $d->parcel?->tracking_number }}">
                    <td>{{ $d->delivered_at?->copy()->tz($tz)->format('M j, g:i A') }}</td>
                    <td class="mono">{{ $d->parcel?->tracking_number ?? '—' }}</td>
                    <td>{{ $d->parcel?->order?->number() ?? '—' }}</td>
                    <td class="r">{{ $peso($d->cod_collected) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#78716c;">No new COD deliveries since the previous remittance.</td></tr>
            @endforelse
            @if ($carriedOver > 0)
                <tr><td colspan="3" style="color:#57534e;">Balance carried over from earlier remittances</td><td class="r">{{ $peso($carriedOver) }}</td></tr>
            @endif
            </tbody>
            <tfoot>
                <tr><td colspan="3">COD balance at the time</td><td class="r">{{ $peso($remittance->expected_amount) }}</td></tr>
                @if ((float) $remittance->pay_kept > 0)
                    <tr><td colspan="3">Rider's pay kept from the cash</td><td class="r" data-receipt-pay-kept>− {{ $peso($remittance->pay_kept) }}</td></tr>
                @endif
                <tr><td colspan="3">Amount remitted</td><td class="r">{{ $peso($remittance->amount) }}</td></tr>
                @if ($remittance->shortfall() > 0)
                    <tr class="short"><td colspan="3">Short (stays on the rider's COD balance)</td><td class="r" data-receipt-shortfall>{{ $peso($remittance->shortfall()) }}</td></tr>
                @endif
            </tfoot>
        </table>

        <p class="note">
            @if ($confirmed)
                The hub confirmed it received this cash. Keep this receipt for your records.
            @else
                This receipt shows what you submitted. It is <strong>not proof of payment</strong> until the hub counts the cash and confirms it; the status above then changes to Confirmed.
            @endif
            Receipt {{ $remittance->receiptNumber() }} · generated {{ now($tz)->format('M j, Y g:i A') }}.
        </p>
    </main>
</body>
</html>
