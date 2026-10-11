{{--
    Simple SVG area chart.
    @include('partials.dash.area-chart', [
        'points' => collect of objects with ->label and ->value,
        'id'     => 'unique-id',
        'prefix' => '₱',            // optional value prefix in tooltips/axis
        'unit'   => 'new users',    // optional tooltip suffix
    ])
--}}
@php
    $prefix = $prefix ?? '';
    $unit   = $unit ?? '';
    $W = 1000; $H = 240; $padL = 56; $padR = 16; $padT = 16; $padB = 30;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $pts = collect($points)->values(); $n = $pts->count();
    $max = max(1, (float) $pts->max('value'));
    // Round the top of the axis to a friendly number.
    $mag = pow(10, max(0, floor(log10($max))));
    $max = ceil($max / $mag) * $mag;
    $xAt = fn ($i) => $n <= 1 ? $padL + $plotW / 2 : $padL + ($plotW * $i / ($n - 1));
    $yAt = fn ($v) => $padT + $plotH - ($v / $max) * $plotH;
    $coords = [];
    foreach ($pts as $i => $r) { $coords[] = ['x' => $xAt($i), 'y' => $yAt((float) $r->value), 'row' => $r]; }
    $line = collect($coords)->map(fn ($c) => round($c['x'], 1) . ',' . round($c['y'], 1))->implode(' ');
    $fmt = fn ($v) => $prefix . ($v >= 1000 ? number_format($v / 1000, $v >= 10000 ? 0 : 1) . 'k' : number_format($v, $prefix ? 0 : 0));
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" style="display:block;overflow:visible;" role="img" aria-label="Chart">
    <defs>
        <linearGradient id="grad-{{ $id }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#fa4e1c" stop-opacity="0.25"/>
            <stop offset="100%" stop-color="#fa4e1c" stop-opacity="0"/>
        </linearGradient>
    </defs>
    @for ($g = 0; $g <= 4; $g++)
        @php $gy = $padT + ($plotH * $g / 4); $gv = $max * (1 - $g / 4); @endphp
        <line x1="{{ $padL }}" y1="{{ round($gy, 1) }}" x2="{{ $W - $padR }}" y2="{{ round($gy, 1) }}" stroke="#F3ECE6" stroke-width="1" @if ($g < 4) stroke-dasharray="4 6" @endif/>
        <text x="{{ $padL - 10 }}" y="{{ round($gy + 4, 1) }}" text-anchor="end" font-size="12" fill="#a3948a">{{ $fmt($gv) }}</text>
    @endfor
    @if ($n > 1)
        <polygon points="{{ round($coords[0]['x'], 1) }},{{ round($padT + $plotH, 1) }} {{ $line }} {{ round($coords[$n - 1]['x'], 1) }},{{ round($padT + $plotH, 1) }}" fill="url(#grad-{{ $id }})"/>
        <polyline points="{{ $line }}" fill="none" stroke="#fa4e1c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    @endif
    @foreach ($coords as $c)
        <circle cx="{{ round($c['x'], 1) }}" cy="{{ round($c['y'], 1) }}" r="5" fill="#fff" stroke="#fa4e1c" stroke-width="3"/>
        <circle cx="{{ round($c['x'], 1) }}" cy="{{ round($c['y'], 1) }}" r="16" fill="transparent">
            <title>{{ $c['row']->label }}: {{ $prefix }}{{ number_format((float) $c['row']->value, $prefix ? 2 : 0) }}{{ $unit ? ' ' . $unit : '' }}</title>
        </circle>
        <text x="{{ round($c['x'], 1) }}" y="{{ $H - 6 }}" text-anchor="middle" font-size="12" font-weight="600" fill="#8a7a70">{{ $c['row']->label }}</text>
    @endforeach
</svg>
