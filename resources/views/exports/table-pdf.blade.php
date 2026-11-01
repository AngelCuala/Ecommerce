{{-- A report table as a printable PDF (TableExport, format=pdf): the same rows as the CSV / XLSX. --}}
@php
    // Right-align number columns; a last row that starts with "Total" is the totals row.
    $numeric = [];
    foreach ($header as $i => $h) {
        $numeric[$i] = collect($rows)->every(fn ($r) => ! isset($r[$i]) || $r[$i] === '' || $r[$i] === null || is_numeric($r[$i]));
    }
    $last = end($rows);
    $hasTotal = $last && is_string($last[0] ?? null) && str_starts_with(strtolower($last[0]), 'total');
    $body = $hasTotal ? array_slice($rows, 0, -1) : $rows;
    $fmt = fn ($v, $i) => is_float($v) || (is_string($v) && is_numeric($v) && str_contains($v, '.')) ? number_format((float) $v, 2) : $v;
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
        h1 { font-size: 18px; margin: 0; color: #002b4d; }
        .brand { color: #fa4e1c; font-weight: bold; font-size: 13px; letter-spacing: 1px; }
        .muted { color: #6b7c8a; }
        .head { border-bottom: 2px solid #fa4e1c; padding-bottom: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; font-size: 9.5px; text-transform: uppercase; text-align: left; padding: 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; border-bottom: none; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="footer">
        ALVY · {{ $title }}{{ $subtitle ? ' · ' . $subtitle : '' }} · Generated {{ $generatedAt->format('M d, Y g:i A') }}{{ $generatedBy ? ' by ' . $generatedBy : '' }}
    </div>

    <div class="head">
        <div class="brand">ALVY</div>
        <h1>{{ $title }}</h1>
        @if ($subtitle)<div class="muted">{{ $subtitle }}</div>@endif
    </div>

    <table>
        <thead>
            <tr>@foreach ($header as $i => $h)<th class="{{ $numeric[$i] ? 'r' : '' }}">{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($body as $row)
                <tr>@foreach ($header as $i => $h)<td class="{{ $numeric[$i] ? 'r' : '' }}">{{ $fmt($row[$i] ?? '', $i) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($header) }}" class="muted">No data for this period.</td></tr>
            @endforelse
        </tbody>
        @if ($hasTotal)
            <tfoot>
                <tr>@foreach ($header as $i => $h)<td class="{{ $numeric[$i] ? 'r' : '' }}">{{ $fmt($last[$i] ?? '', $i) }}</td>@endforeach</tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
