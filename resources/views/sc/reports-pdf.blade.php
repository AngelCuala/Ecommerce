{{-- Sorting center report as a PDF (SortingCenterController::exportReport): one tab, the same numbers as the screen. --}}
@php
    $range = $from->format('M j, Y') . ' – ' . $to->format('M j, Y');
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
        h1 { font-size: 18px; margin: 2px 0 0; color: #002b4d; }
        .brand { color: #fa4e1c; font-weight: bold; font-size: 13px; letter-spacing: 1px; }
        .muted { color: #6b7c8a; }
        .head { border-bottom: 2px solid #fa4e1c; padding-bottom: 8px; margin-bottom: 14px; }
        .head td { border: none; padding: 0; vertical-align: bottom; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 9px; text-transform: uppercase; text-align: left; padding: 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; }
        tbody tr:nth-child(even) td { background: #fafafa; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; border-bottom: none; background: #fff; }
        .empty { padding: 26px; text-align: center; color: #6b7c8a; border: 1px dashed #cbd5e1; }
        .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="brand">ALVY LOGISORT</div>
                <h1>{{ $title }}</h1>
                <div class="muted">{{ $center }} · {{ $range }}</div>
            </td>
            <td class="r muted" style="font-size:9px;">
                Generated {{ $generatedAt->tz(config('app.business_timezone'))->format('M j, Y g:i A') }}<br>
                @if ($generatedBy)by {{ $generatedBy }}@endif
            </td>
        </tr>
    </table>

    @if (empty($rows))
        <div class="empty">No data in this date range.</div>
    @else
        <table>
            <thead>
                <tr>@foreach ($header as $i => $h)<th class="{{ in_array($i, $right, true) ? 'r' : '' }}">{{ $h }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach (array_values($row) as $i => $cell)<td class="{{ in_array($i, $right, true) ? 'r' : '' }}">{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </tbody>
            @if ($totals)
                <tfoot>
                    <tr>@foreach ($totals as $i => $cell)<td class="{{ in_array($i, $right, true) ? 'r' : '' }}">{{ $cell }}</td>@endforeach</tr>
                </tfoot>
            @endif
        </table>
    @endif

    <div class="foot">{{ $title }} · {{ $center }} · {{ $range }}</div>
</body>
</html>
