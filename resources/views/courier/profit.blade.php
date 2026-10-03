<x-courier-layout title="My Earnings" active="profit">

<div class="cx-page-head">
    <h1 class="font-display">My Earnings</h1>
    <p>Earnings are calculated automatically from completed deliveries.</p>
</div>

{{-- Primary summary --}}
<div class="cx-grid cx-cols-4" style="margin-bottom:16px;">
    @php
        $cards = [
            ['label'=>'Total Earnings', 'value'=>'₱'.number_format($totalEarnings,2), 'color'=>'var(--accent)'],
            ['label'=>'Today',          'value'=>'₱'.number_format($todayEarnings,2), 'color'=>'var(--green)'],
            ['label'=>'This Week',      'value'=>'₱'.number_format($weekEarnings,2),  'color'=>'var(--blue)'],
            ['label'=>'This Month',     'value'=>'₱'.number_format($monthEarnings,2), 'color'=>'var(--purple)'],
        ];
    @endphp
    @foreach($cards as $c)
        <div class="card cx-stat">
            <p class="cx-stat-value" style="font-size:20px;color:{{ $c['color'] }};">{{ $c['value'] }}</p>
            <p class="cx-stat-label">{{ $c['label'] }}</p>
        </div>
    @endforeach
</div>

{{-- Secondary summary --}}
<div class="cx-grid cx-cols-3" style="margin-bottom:32px;">
    <div class="card cx-stat" style="text-align:center;">
        <p class="cx-stat-value" style="font-size:20px;color:var(--text);">{{ $totalDeliveries }}</p>
        <p class="cx-stat-label">Completed Deliveries</p>
    </div>
    <div class="card cx-stat" style="text-align:center;">
        <p class="cx-stat-value" style="font-size:20px;color:var(--text);">₱{{ number_format($avgPerDelivery,2) }}</p>
        <p class="cx-stat-label">Avg. per Delivery</p>
    </div>
    <div class="card cx-stat" style="text-align:center;">
        <p class="cx-stat-value" style="font-size:20px;color:var(--amber);">₱{{ number_format($pendingEarnings,2) }}</p>
        <p class="cx-stat-label">Pending (in progress)</p>
    </div>
</div>

{{-- Earnings chart --}}
@if($earningsByMonth->count())
    <div class="card" style="padding:24px;margin-bottom:32px;">
        <h2 class="cx-section-title">Earnings Over Time</h2>
        @php
            $W=1000;$H=220;$padL=52;$padR=20;$padT=16;$padB=30;
            $plotW=$W-$padL-$padR;$plotH=$H-$padT-$padB;
            $pts=$earningsByMonth->values();$n=$pts->count();
            $max=max(1,$pts->max('total'));
            $xAt=fn($i)=>$n<=1?$padL+$plotW/2:$padL+($plotW*$i/($n-1));
            $yAt=fn($v)=>$padT+$plotH-($v/$max)*$plotH;
            $coords=[];foreach($pts as $i=>$r){$coords[]=['x'=>$xAt($i),'y'=>$yAt($r->total),'row'=>$r];}
            $line=collect($coords)->map(fn($c)=>round($c['x'],1).','.round($c['y'],1))->implode(' ');
        @endphp
        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" style="display:block;overflow:visible;">
            <defs><linearGradient id="cEarn" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#fa4e1c" stop-opacity="0.22"/><stop offset="100%" stop-color="#fa4e1c" stop-opacity="0"/>
            </linearGradient></defs>
            @for($g=0;$g<=4;$g++)
                @php $gy=$padT+($plotH*$g/4);$gv=$max*(1-$g/4); @endphp
                <line x1="{{ $padL }}" y1="{{ round($gy,1) }}" x2="{{ $W-$padR }}" y2="{{ round($gy,1) }}" stroke="#eef2f6" stroke-width="1"/>
                <text x="{{ $padL-10 }}" y="{{ round($gy+4,1) }}" text-anchor="end" font-size="11" fill="#9db3c4">₱{{ number_format($gv,0) }}</text>
            @endfor
            @if($n>1)
                <polygon points="{{ round($coords[0]['x'],1) }},{{ round($padT+$plotH,1) }} {{ $line }} {{ round($coords[$n-1]['x'],1) }},{{ round($padT+$plotH,1) }}" fill="url(#cEarn)"/>
                <polyline points="{{ $line }}" fill="none" stroke="#fa4e1c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            @endif
            @foreach($coords as $c)
                <circle cx="{{ round($c['x'],1) }}" cy="{{ round($c['y'],1) }}" r="4" fill="#fff" stroke="#fa4e1c" stroke-width="2.5"/>
                <circle cx="{{ round($c['x'],1) }}" cy="{{ round($c['y'],1) }}" r="13" fill="transparent"><title>{{ $c['row']->month }}: ₱{{ number_format($c['row']->total,2) }}</title></circle>
                <text x="{{ round($c['x'],1) }}" y="{{ $H-8 }}" text-anchor="middle" font-size="11" font-weight="600" fill="#6b90aa">{{ $c['row']->month }}</text>
            @endforeach
        </svg>
    </div>
@endif

{{-- Recent earnings table --}}
<h2 class="cx-section-title">Recent Earnings</h2>
<div class="card cx-table-wrap">
    <table class="cx-table" style="min-width:560px;">
        <thead>
            <tr>
                <th>Date</th><th>Order ID</th><th>Delivery</th>
                <th style="text-align:right;">Earnings</th><th style="text-align:right;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentDeliveries as $d)
                <tr>
                    <td style="color:var(--text-muted);">{{ $d->delivered_at?->format('M d, Y') ?? '—' }}</td>
                    <td style="font-weight:600;">#{{ str_pad($d->order_id,6,'0',STR_PAD_LEFT) }}</td>
                    <td style="color:var(--text-muted);">{{ $d->order->full_name ?? '—' }}</td>
                    <td style="text-align:right;" class="cx-num">+₱{{ number_format($d->delivery_fee,2) }}</td>
                    <td style="text-align:right;"><span class="cx-pill cx-pill-green">Paid</span></td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">No completed deliveries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

</x-courier-layout>
