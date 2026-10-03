<x-admin-layout title="Dashboard" active="dashboard">

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold" style="color:#222222;">Welcome, {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-sm" style="color:#6b90aa;">Platform overview and administrative activity.</p>
</div>

{{-- ══════════ SUMMARY CARDS ══════════ --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

    {{-- Total Users --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest" style="color:#6b90aa;">Total Users</p>
        <p class="mt-1 font-display text-3xl font-extrabold" style="color:#002b4d;">{{ number_format($totalUsers) }}</p>
        <div class="mt-3 flex gap-4 text-xs">
            <span style="color:#059669;">● {{ number_format($activeUsers) }} active</span>
            <span style="color:#DC2626;">● {{ number_format($suspendedUsers) }} suspended</span>
        </div>
    </div>

    {{-- Total Buyers --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest" style="color:#6b90aa;">Total Buyers</p>
        <p class="mt-1 font-display text-3xl font-extrabold" style="color:#002b4d;">{{ number_format($totalBuyers) }}</p>
        <div class="mt-3 flex gap-4 text-xs">
            <span style="color:#059669;">● {{ number_format($activeBuyers) }} active</span>
            <span style="color:#6b90aa;">registered buyers</span>
        </div>
    </div>

    {{-- Total Sellers --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest" style="color:#6b90aa;">Total Sellers</p>
        <p class="mt-1 font-display text-3xl font-extrabold" style="color:#002b4d;">{{ number_format($totalSellers) }}</p>
        <div class="mt-3 flex gap-4 text-xs">
            <span style="color:#059669;">● {{ number_format($activeSellers) }} active</span>
            <span style="color:#6b90aa;">approved sellers</span>
        </div>
    </div>

    {{-- New Registrations --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest" style="color:#6b90aa;">New Registrations</p>
        <p class="mt-1 font-display text-3xl font-extrabold" style="color:#fa4e1c;">{{ number_format($newToday) }}</p>
        <div class="mt-3 flex gap-4 text-xs" style="color:#6b90aa;">
            <span>Today <strong style="color:#222;">{{ $newToday }}</strong></span>
            <span>Week <strong style="color:#222;">{{ $newWeek }}</strong></span>
            <span>Month <strong style="color:#222;">{{ $newMonth }}</strong></span>
        </div>
    </div>

    {{-- Pending Requests --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest" style="color:#6b90aa;">Pending Requests</p>
        <p class="mt-1 font-display text-3xl font-extrabold" style="color:#B45309;">{{ number_format($pendingRequests) }}</p>
        <div class="mt-3 flex gap-4 text-xs" style="color:#6b90aa;">
            <span>{{ $pendingSellerApps }} seller app(s)</span>
            <span>{{ $pendingBuyerVerif }} verification(s)</span>
        </div>
    </div>

    {{-- System Status --}}
    <div class="card p-5">
        <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#6b90aa;">System Status</p>
        <div class="space-y-1.5">
            @foreach ($systemStatus as $svc => $ok)
                <div class="flex items-center justify-between text-xs">
                    <span style="color:#374151;">{{ $svc }}</span>
                    <span style="color:{{ $ok ? '#059669' : '#DC2626' }};">● {{ $ok ? 'Operational' : 'Unavailable' }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-[10px]" style="color:#9db3c4;">Configured status — not live health monitoring.</p>
    </div>
</div>

{{-- ══════════ GROWTH CHART + PENDING LIST ══════════ --}}
<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">

    {{-- User growth chart --}}
    <div class="card p-6">
        <h2 class="font-display text-base font-bold mb-1" style="color:#222;">User Growth</h2>
        <p class="text-xs mb-4" style="color:#6b90aa;">New registrations over the last 6 months.</p>
        @php
            $W=1000;$H=220;$padL=44;$padR=20;$padT=16;$padB=30;
            $plotW=$W-$padL-$padR;$plotH=$H-$padT-$padB;
            $pts=$growth->values();$n=$pts->count();
            $max=max(1,$pts->max('count'));
            $xAt=fn($i)=>$n<=1?$padL+$plotW/2:$padL+($plotW*$i/($n-1));
            $yAt=fn($v)=>$padT+$plotH-($v/$max)*$plotH;
            $coords=[];foreach($pts as $i=>$r){$coords[]=['x'=>$xAt($i),'y'=>$yAt($r->count),'row'=>$r];}
            $line=collect($coords)->map(fn($c)=>round($c['x'],1).','.round($c['y'],1))->implode(' ');
        @endphp
        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" style="display:block;overflow:visible;">
            <defs><linearGradient id="ug" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#fa4e1c" stop-opacity="0.22"/><stop offset="100%" stop-color="#fa4e1c" stop-opacity="0"/>
            </linearGradient></defs>
            @for($g=0;$g<=4;$g++)
                @php $gy=$padT+($plotH*$g/4);$gv=$max*(1-$g/4); @endphp
                <line x1="{{ $padL }}" y1="{{ round($gy,1) }}" x2="{{ $W-$padR }}" y2="{{ round($gy,1) }}" stroke="#eef2f6" stroke-width="1"/>
                <text x="{{ $padL-8 }}" y="{{ round($gy+4,1) }}" text-anchor="end" font-size="11" fill="#9db3c4">{{ round($gv) }}</text>
            @endfor
            @if($n>1)
                <polygon points="{{ round($coords[0]['x'],1) }},{{ round($padT+$plotH,1) }} {{ $line }} {{ round($coords[$n-1]['x'],1) }},{{ round($padT+$plotH,1) }}" fill="url(#ug)"/>
                <polyline points="{{ $line }}" fill="none" stroke="#fa4e1c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            @endif
            @foreach($coords as $c)
                <circle cx="{{ round($c['x'],1) }}" cy="{{ round($c['y'],1) }}" r="4" fill="#fff" stroke="#fa4e1c" stroke-width="2.5"/>
                <circle cx="{{ round($c['x'],1) }}" cy="{{ round($c['y'],1) }}" r="13" fill="transparent"><title>{{ $c['row']->label }}: {{ $c['row']->count }} new users</title></circle>
                <text x="{{ round($c['x'],1) }}" y="{{ $H-8 }}" text-anchor="middle" font-size="11" font-weight="600" fill="#6b90aa">{{ $c['row']->label }}</text>
            @endforeach
        </svg>
    </div>

    {{-- Pending requests list --}}
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-base font-bold" style="color:#222;">Pending Requests</h2>
            <a href="{{ route('admin.seller-applications.index') }}" class="text-xs font-semibold" style="color:#fa4e1c;">View all</a>
        </div>
        @forelse ($pendingList as $app)
            <a href="{{ route('admin.seller-applications.show', $app->id) }}"
               class="flex items-center justify-between rounded-lg px-3 py-2.5 mb-1 transition"
               onmouseover="this.style.background='#f6f9fc';" onmouseout="this.style.background='';">
                <div class="min-w-0">
                    <p class="text-sm font-semibold truncate" style="color:#222;">{{ $app->shop_name ?? $app->full_name }}</p>
                    <p class="text-xs" style="color:#6b90aa;">Seller application · {{ $app->created_at->diffForHumans() }}</p>
                </div>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" style="background:#FFFBEB;color:#B45309;">Pending</span>
            </a>
        @empty
            <p class="py-8 text-center text-sm" style="color:#6b90aa;">No pending requests. All caught up.</p>
        @endforelse
    </div>
</div>

{{-- ══════════ RECENT ACTIVITY + NOTIFICATIONS ══════════ --}}
<div class="mt-6 grid gap-6 lg:grid-cols-2">

    {{-- Recent admin activity --}}
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-base font-bold" style="color:#222;">Recent Activity</h2>
            <a href="{{ route('admin.activity.index') }}" class="text-xs font-semibold" style="color:#fa4e1c;">View log</a>
        </div>
        @forelse ($recentActivity as $log)
            <div class="flex items-start gap-3 py-2.5" style="border-bottom:1px solid #f0f4f8;">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full" style="background:{{ $log->status === 'failed' ? '#DC2626' : '#fa4e1c' }};"></span>
                <div class="min-w-0">
                    <p class="text-sm" style="color:#222;">
                        <strong>{{ $log->admin_name ?? 'System' }}</strong> — {{ $log->action_label ?? $log->action }}
                    </p>
                    @if ($log->description)
                        <p class="text-xs" style="color:#6b90aa;">{{ $log->description }}</p>
                    @endif
                    <p class="text-[11px]" style="color:#c2d1dc;">{{ $log->created_at->format('M d, Y · g:i A') }}</p>
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-sm" style="color:#6b90aa;">No administrative activity recorded yet.</p>
        @endforelse
    </div>

    {{-- Notifications --}}
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-base font-bold" style="color:#222;">Notifications</h2>
            <a href="{{ route('admin.notifications.index') }}" class="text-xs font-semibold" style="color:#fa4e1c;">View all</a>
        </div>
        @forelse ($notifications as $n)
            <a href="{{ $n['link'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 mb-1 transition"
               onmouseover="this.style.background='#f6f9fc';" onmouseout="this.style.background='';">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                      style="background:{{ $n['type'] === 'warning' ? '#FEF2F2' : '#FFF1E6' }};">
                    <svg class="h-4 w-4" fill="none" stroke="{{ $n['type'] === 'warning' ? '#DC2626' : '#fa4e1c' }}" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg>
                </span>
                <p class="text-sm" style="color:#222;">{{ $n['title'] }}</p>
            </a>
        @empty
            <p class="py-8 text-center text-sm" style="color:#6b90aa;">Nothing needs your attention right now.</p>
        @endforelse
    </div>
</div>

</x-admin-layout>
