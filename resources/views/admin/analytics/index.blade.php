<x-admin-layout title="Analytics & Reports" active="analytics">

{{-- ── Account composition ─────────────────────────────── --}}
<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    @php
        $cards = [
            ['label'=>'Total Accounts','value'=>$accountStats['total'],   'color'=>'#002b4d'],
            ['label'=>'Buyers',        'value'=>$accountStats['buyers'],  'color'=>'#fa4e1c'],
            ['label'=>'Sellers',       'value'=>$accountStats['sellers'], 'color'=>'#0ea5e9'],
            ['label'=>'Couriers',      'value'=>$accountStats['couriers'],'color'=>'#8b5cf6'],
            ['label'=>'Admins',        'value'=>$accountStats['admins'],  'color'=>'#059669'],
            ['label'=>'Disabled',      'value'=>$accountStats['disabled'],'color'=>'#DC2626'],
        ];
    @endphp
    @foreach ($cards as $c)
        <div class="card p-4">
            <p class="text-[10px] font-bold uppercase tracking-widest" style="color:#6b90aa;">{{ $c['label'] }}</p>
            <p class="mt-1 font-display text-2xl font-extrabold" style="color:{{ $c['color'] }};">{{ $c['value'] }}</p>
        </div>
    @endforeach
</div>

{{-- ── Registration trends ─────────────────────────────── --}}
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @php
        $trends = [
            ['label'=>'New Today',      'value'=>$registrationTrends['today']],
            ['label'=>'This Week',      'value'=>$registrationTrends['week']],
            ['label'=>'This Month',     'value'=>$registrationTrends['month']],
            ['label'=>'This Year',      'value'=>$registrationTrends['year']],
        ];
    @endphp
    @foreach ($trends as $t)
        <div class="card p-4">
            <p class="text-[10px] font-bold uppercase tracking-widest" style="color:#6b90aa;">{{ $t['label'] }}</p>
            <p class="mt-1 font-display text-xl font-extrabold" style="color:#002b4d;">+{{ $t['value'] }}</p>
        </div>
    @endforeach
</div>

@php
    // Reusable SVG line-chart renderer helper values
    $renderChart = function ($series) {
        $w = 720; $h = 220; $padX = 36; $padY = 24;
        $counts = collect($series)->pluck('count');
        $max = max(1, $counts->max());
        $n = count($series);
        $stepX = $n > 1 ? ($w - $padX * 2) / ($n - 1) : 0;
        $points = [];
        foreach (array_values($series) as $i => $row) {
            $x = $padX + $i * $stepX;
            $y = $h - $padY - (($row['count'] / $max) * ($h - $padY * 2));
            $points[] = ['x'=>round($x,1), 'y'=>round($y,1), 'label'=>$row['label'], 'count'=>$row['count']];
        }
        return ['w'=>$w,'h'=>$h,'padX'=>$padX,'padY'=>$padY,'max'=>$max,'points'=>$points];
    };
@endphp

@php $charts = [
    ['title'=>'Daily Registrations (14 days)', 'data'=>$daily],
    ['title'=>'Weekly Registrations (8 weeks)','data'=>$weekly],
    ['title'=>'Monthly Registrations (6 months)','data'=>$monthly],
]; @endphp

<div class="space-y-6">
    @foreach ($charts as $chart)
        @php $c = $renderChart($chart['data']->all()); @endphp
        <div class="card p-6">
            <h3 class="mb-4 font-display text-base font-bold" style="color:#002b4d;">{{ $chart['title'] }}</h3>
            <div class="overflow-x-auto">
                <svg viewBox="0 0 {{ $c['w'] }} {{ $c['h'] }}" class="w-full" style="min-width:640px;height:220px;">
                    {{-- baseline --}}
                    <line x1="{{ $c['padX'] }}" y1="{{ $c['h'] - $c['padY'] }}" x2="{{ $c['w'] - $c['padX'] }}" y2="{{ $c['h'] - $c['padY'] }}" stroke="#dce8f0" stroke-width="1"/>
                    {{-- area + line --}}
                    @php
                        $line = collect($c['points'])->map(fn($p) => $p['x'].','.$p['y'])->implode(' ');
                        $first = $c['points'][0];
                        $last = end($c['points']);
                        $area = $first['x'].','.($c['h'] - $c['padY']).' '.$line.' '.$last['x'].','.($c['h'] - $c['padY']);
                    @endphp
                    <polygon points="{{ $area }}" fill="#fa4e1c" fill-opacity="0.08"/>
                    <polyline points="{{ $line }}" fill="none" stroke="#fa4e1c" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
                    @foreach ($c['points'] as $p)
                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5" fill="#fff" stroke="#fa4e1c" stroke-width="2">
                            <title>{{ $p['label'] }}: {{ $p['count'] }}</title>
                        </circle>
                        <text x="{{ $p['x'] }}" y="{{ $c['h'] - 6 }}" text-anchor="middle" font-size="9" fill="#6b90aa">{{ $p['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </div>
    @endforeach
</div>

</x-admin-layout>
