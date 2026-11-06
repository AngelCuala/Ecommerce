<x-admin-layout title="Analytics & Reports" active="analytics">

@include('partials.page-tabs', ['tabs' => [['Sales Reports', 'admin.reports.index', 'admin.reports.*'], ['Analytics', 'admin.analytics.index', 'admin.analytics.*']]])
@php
    $total = max(1, (int) $accountStats['total']);
    $icons = [
        'users'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'bag'     => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'store'   => '<path d="M3 9l1-5h16l1 5"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
        'truck'   => '<rect x="1" y="3" width="15" height="13" rx="1.5"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'shield'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'ban'     => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>',
        'week'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'month'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        'year'    => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
    ];
    $svg = fn ($name) => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">' . $icons[$name] . '</svg>';
@endphp
<style>
    .an-tile { position: relative; overflow: hidden; }
    .an-icon { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; }
    .an-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6b90aa; }
    .an-value { font-family: 'Nunito', ui-sans-serif, system-ui; font-weight: 800; color: #002b4d; line-height: 1.1; }
    .an-share { height: 4px; border-radius: 999px; background: #EEF2F6; overflow: hidden; }
    .an-share span { display: block; height: 100%; border-radius: 999px; }
    .an-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: #F1F5F9; }
    .an-tabs button { padding: 6px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; color: #57534E; }
    .an-tabs button[aria-selected="true"] { background: #fff; color: #002b4d; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
    .an-tip { position: absolute; pointer-events: none; transform: translate(-50%, calc(-100% - 12px)); background: #002b4d; color: #fff;
              font-size: 12px; line-height: 1.35; padding: 6px 10px; border-radius: 8px; white-space: nowrap; box-shadow: 0 6px 16px rgba(0,43,77,.25); }
    .an-tip b { font-size: 14px; }
    .an-hit { cursor: crosshair; }
</style>

{{-- ── Accounts by role ─────────────────────────────── --}}
@php
    $cards = [
        ['label' => 'Total Accounts', 'value' => $accountStats['total'],    'icon' => 'users',  'tint' => '#E8F0F6', 'ink' => '#002b4d', 'share' => null],
        ['label' => 'Buyers',         'value' => $accountStats['buyers'],   'icon' => 'bag',    'tint' => '#FFF1EE', 'ink' => '#fa4e1c', 'share' => '#fa4e1c'],
        ['label' => 'Sellers',        'value' => $accountStats['sellers'],  'icon' => 'store',  'tint' => '#E0F2FE', 'ink' => '#0284c7', 'share' => '#0ea5e9'],
        ['label' => 'Couriers',       'value' => $accountStats['couriers'], 'icon' => 'truck',  'tint' => '#EDE9FE', 'ink' => '#7c3aed', 'share' => '#8b5cf6'],
        ['label' => 'Admins',         'value' => $accountStats['admins'],   'icon' => 'shield', 'tint' => '#DCFCE7', 'ink' => '#047857', 'share' => '#059669'],
        ['label' => 'Disabled',       'value' => $accountStats['disabled'], 'icon' => 'ban',    'tint' => '#FEE2E2', 'ink' => '#B91C1C', 'share' => '#DC2626'],
    ];
@endphp
<div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6" data-account-tiles>
    @foreach ($cards as $i => $c)
        <div class="card an-tile p-4" @if ($i === 0) style="background:linear-gradient(135deg,#002b4d,#0b4a7a);border:0;" @endif>
            <div class="flex items-start justify-between gap-2">
                <p class="an-label" @if ($i === 0) style="color:rgba(255,255,255,.75);" @endif>{{ $c['label'] }}</p>
                <span class="an-icon" style="{{ $i === 0 ? 'background:rgba(255,255,255,.14);color:#fff;' : 'background:' . $c['tint'] . ';color:' . $c['ink'] . ';' }}">{!! $svg($c['icon']) !!}</span>
            </div>
            <p class="an-value mt-2 text-3xl" @if ($i === 0) style="color:#fff;" @endif data-tile="{{ \Illuminate\Support\Str::slug($c['label']) }}">{{ number_format($c['value']) }}</p>
            @if ($c['share'])
                @php $pct = round($c['value'] / $total * 100); @endphp
                <div class="mt-3 flex items-center gap-2">
                    <div class="an-share flex-1"><span style="width:{{ $pct }}%;background:{{ $c['share'] }};"></span></div>
                    <span class="text-[11px] font-semibold" style="color:#6b90aa;">{{ $pct }}%</span>
                </div>
            @else
                <p class="mt-3 text-[11px] font-medium" style="color:rgba(255,255,255,.7);">All roles, every status</p>
            @endif
        </div>
    @endforeach
</div>

{{-- ── New sign-ups ─────────────────────────────── --}}
@php
    $trends = [
        ['label' => 'New Today',  'value' => $registrationTrends['today'], 'icon' => 'sun',   'note' => now()->format('D, M j')],
        ['label' => 'This Week',  'value' => $registrationTrends['week'],  'icon' => 'week',  'note' => 'since ' . now()->startOfWeek()->format('M j')],
        ['label' => 'This Month', 'value' => $registrationTrends['month'], 'icon' => 'month', 'note' => 'since ' . now()->startOfMonth()->format('M j')],
        ['label' => 'This Year',  'value' => $registrationTrends['year'],  'icon' => 'year',  'note' => 'since Jan 1, ' . now()->year],
    ];
@endphp
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4" data-trend-tiles>
    @foreach ($trends as $t)
        <div class="card flex items-center gap-3 p-4">
            <span class="an-icon" style="background:#FFF1EE;color:#fa4e1c;">{!! $svg($t['icon']) !!}</span>
            <div class="min-w-0">
                <p class="an-label">{{ $t['label'] }}</p>
                <p class="an-value text-2xl">+{{ number_format($t['value']) }}</p>
                <p class="truncate text-[11px]" style="color:#8a9bab;">{{ $t['note'] }}</p>
            </div>
        </div>
    @endforeach
</div>

{{-- ── Registrations chart (one card, three ranges) ─────────────────────────────── --}}
@php
    $renderChart = function ($series) {
        $w = 1000; $h = 300; $left = 40; $right = 20; $top = 28; $bottom = 32;
        $counts = collect($series)->pluck('count');
        $peak = (int) $counts->max();
        // A round top for the scale: 1, 2, 5, 10, 20, 50… so gridlines read as whole numbers.
        $nice = 1;
        foreach ([1, 2, 4, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000] as $step) { if ($step >= $peak) { $nice = $step; break; } $nice = $step; }
        $max = max(1, $nice);
        $n = count($series);
        $stepX = $n > 1 ? ($w - $left - $right) / ($n - 1) : 0;
        $points = [];
        foreach (array_values($series) as $i => $row) {
            $x = $left + $i * $stepX;
            $y = $h - $bottom - (($row['count'] / $max) * ($h - $top - $bottom));
            $points[] = ['x' => round($x, 1), 'y' => round($y, 1), 'label' => $row['label'], 'count' => (int) $row['count']];
        }
        $grid = collect([0, .5, 1])->map(fn ($f) => ['y' => round($h - $bottom - $f * ($h - $top - $bottom), 1), 'v' => round($max * $f)])->unique('v')->values();
        $peakIndex = $peak > 0 ? $counts->search($peak) : null;
        return compact('w', 'h', 'left', 'right', 'top', 'bottom', 'max', 'points', 'grid', 'stepX', 'peakIndex') + ['total' => (int) $counts->sum(), 'peak' => $peak];
    };
    $charts = [
        'daily'   => ['tab' => 'Daily',   'title' => 'Last 14 days',  'unit' => 'day',   'data' => $daily],
        'weekly'  => ['tab' => 'Weekly',  'title' => 'Last 8 weeks',  'unit' => 'week',  'data' => $weekly],
        'monthly' => ['tab' => 'Monthly', 'title' => 'Last 6 months', 'unit' => 'month', 'data' => $monthly],
    ];
@endphp
<div class="card p-6" data-reg-chart>
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="font-display text-lg font-bold" style="color:#002b4d;">New registrations</h3>
            <p class="text-sm" style="color:#6b90aa;">Accounts created, all roles</p>
        </div>
        <div class="an-tabs" role="tablist" aria-label="Range">
            @foreach ($charts as $key => $chart)
                <button type="button" role="tab" data-tab="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $chart['tab'] }}</button>
            @endforeach
        </div>
    </div>

    @foreach ($charts as $key => $chart)
        @php $c = $renderChart($chart['data']->all()); @endphp
        <div data-panel="{{ $key }}" @unless ($loop->first) hidden @endunless>
            <div class="mb-3 flex flex-wrap gap-6">
                <div><p class="an-label">{{ $chart['title'] }}</p><p class="an-value text-2xl" data-range-total>{{ number_format($c['total']) }}</p></div>
                <div><p class="an-label">Busiest {{ $chart['unit'] }}</p>
                    <p class="an-value text-2xl">{{ $c['peak'] }}<span class="ml-1 text-sm font-semibold" style="color:#6b90aa;">{{ $c['peakIndex'] !== null ? $c['points'][$c['peakIndex']]['label'] : '—' }}</span></p></div>
            </div>

            <div class="relative overflow-x-auto" data-plot>
                @php
                    $line = collect($c['points'])->map(fn ($p) => $p['x'] . ',' . $p['y'])->implode(' ');
                    $first = $c['points'][0]; $last = end($c['points']);
                    $base = $c['h'] - $c['bottom'];
                    $area = $first['x'] . ',' . $base . ' ' . $line . ' ' . $last['x'] . ',' . $base;
                    $gid = 'an-fill-' . $key;
                @endphp
                <svg viewBox="0 0 {{ $c['w'] }} {{ $c['h'] }}" class="block w-full" style="min-width:640px;height:auto;" role="img"
                     aria-label="{{ $chart['tab'] }} registrations: {{ $c['total'] }} in total, busiest {{ $c['peak'] }}">
                    <defs>
                        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fa4e1c" stop-opacity=".22"/>
                            <stop offset="100%" stop-color="#fa4e1c" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    {{-- recessive grid + y values --}}
                    @foreach ($c['grid'] as $g)
                        <line x1="{{ $c['left'] }}" y1="{{ $g['y'] }}" x2="{{ $c['w'] - $c['right'] }}" y2="{{ $g['y'] }}" stroke="{{ $g['v'] == 0 ? '#cfdce8' : '#EEF2F6' }}" stroke-width="1" @if ($g['v'] != 0) stroke-dasharray="3 4" @endif/>
                        <text x="{{ $c['left'] - 8 }}" y="{{ $g['y'] + 3 }}" text-anchor="end" font-size="10" fill="#8a9bab">{{ $g['v'] }}</text>
                    @endforeach
                    <polygon points="{{ $area }}" fill="url(#{{ $gid }})"/>
                    <polyline points="{{ $line }}" fill="none" stroke="#fa4e1c" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                    <line data-cross x1="0" y1="{{ $c['top'] }}" x2="0" y2="{{ $base }}" stroke="#002b4d" stroke-opacity=".25" stroke-width="1" visibility="hidden"/>
                    @foreach ($c['points'] as $i => $p)
                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="#fff" stroke="#fa4e1c" stroke-width="2" data-dot="{{ $i }}"/>
                        @if ($i === $c['peakIndex'])
                            <text x="{{ $p['x'] }}" y="{{ $p['y'] - 12 }}" text-anchor="middle" font-size="11" font-weight="700" fill="#002b4d">{{ $p['count'] }}</text>
                        @endif
                        <text x="{{ $p['x'] }}" y="{{ $c['h'] - 10 }}" text-anchor="middle" font-size="10" fill="#8a9bab">{{ $p['label'] }}</text>
                        {{-- hit area: the whole column, wider than the dot --}}
                        <rect class="an-hit" x="{{ $p['x'] - max(8, $c['stepX'] / 2) }}" y="{{ $c['top'] }}" width="{{ max(16, $c['stepX']) }}" height="{{ $base - $c['top'] }}" fill="transparent"
                              data-i="{{ $i }}" data-x="{{ $p['x'] }}" data-y="{{ $p['y'] }}" data-label="{{ $p['label'] }}" data-count="{{ $p['count'] }}"/>
                    @endforeach
                </svg>
                <div class="an-tip" data-tip hidden></div>
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-xs font-semibold" style="color:#6b90aa;">Show as table</summary>
                <table class="mt-2 w-full max-w-sm text-sm">
                    <thead><tr style="border-bottom:1px solid #cfdce8;"><th class="py-1 text-left text-xs" style="color:#6b90aa;">{{ ucfirst($chart['unit']) }}</th><th class="py-1 text-right text-xs" style="color:#6b90aa;">New accounts</th></tr></thead>
                    <tbody>
                        @foreach ($c['points'] as $p)
                            <tr style="border-bottom:1px solid #F5F0EB;"><td class="py-1" style="color:#002b4d;">{{ $p['label'] }}</td><td class="py-1 text-right" style="color:#002b4d;">{{ $p['count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </div>
    @endforeach
</div>

<script>
(function () {
    var card = document.querySelector('[data-reg-chart]');
    if (!card) return;

    // Tabs: Daily / Weekly / Monthly
    card.querySelectorAll('[data-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            card.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
            card.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== btn.dataset.tab; });
        });
    });

    // Hover: crosshair + tooltip for the column under the pointer
    card.querySelectorAll('[data-plot]').forEach(function (plot) {
        var svg = plot.querySelector('svg'), tip = plot.querySelector('[data-tip]'), cross = svg.querySelector('[data-cross]');
        function show(hit) {
            var box = svg.getBoundingClientRect(), vb = svg.viewBox.baseVal, sx = box.width / vb.width, sy = box.height / vb.height;
            var x = +hit.dataset.x, y = +hit.dataset.y, n = +hit.dataset.count;
            cross.setAttribute('x1', x); cross.setAttribute('x2', x); cross.setAttribute('visibility', 'visible');
            svg.querySelectorAll('[data-dot]').forEach(function (d) { d.setAttribute('r', d.dataset.dot === hit.dataset.i ? 6 : 4); });
            tip.innerHTML = hit.dataset.label + '<br><b>' + n + '</b> new ' + (n === 1 ? 'account' : 'accounts');
            // Position inside the plot box (SVG elements have no offsetLeft): from the SVG's on-screen box.
            var pbox = plot.getBoundingClientRect();
            tip.style.left = (box.left - pbox.left + plot.scrollLeft + x * sx) + 'px';
            tip.style.top = (box.top - pbox.top + y * sy) + 'px';
            tip.hidden = false;
        }
        function hide() {
            tip.hidden = true; cross.setAttribute('visibility', 'hidden');
            svg.querySelectorAll('[data-dot]').forEach(function (d) { d.setAttribute('r', 4); });
        }
        svg.querySelectorAll('.an-hit').forEach(function (hit) {
            hit.addEventListener('mouseenter', function () { show(hit); });
            hit.addEventListener('touchstart', function () { show(hit); }, { passive: true });
        });
        svg.addEventListener('mouseleave', hide);
    });
})();
</script>

</x-admin-layout>
