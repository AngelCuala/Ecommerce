<x-admin-layout title="Dashboard" active="dashboard">
@include('partials.dash.styles')
@php
    $hour     = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $statusTone = fn ($s) => match (strtolower((string) $s)) {
        'delivered' => 'tone-green', 'shipped' => 'tone-navy', 'cancelled' => 'tone-red', default => 'tone-amber',
    };
@endphp

<div class="space-y-6">

    {{-- ══════════ HERO ══════════ --}}
    <section class="db-hero">
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-white/80">{{ now()->format('l, F j, Y') }}</p>
                <h1 class="mt-1 font-display text-2xl font-extrabold sm:text-3xl" style="color:#fff;">{{ $greeting }}, {{ auth()->user()->name }}</h1>
                <p class="mt-1 text-sm text-white/80">
                    @if ($pendingRequests > 0)
                        <strong class="text-white">{{ $pendingRequests }} {{ \Illuminate\Support\Str::plural('request', $pendingRequests) }}</strong> need your review.
                    @else
                        Nothing waiting for review — the marketplace is running smoothly.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.seller-applications.index', ['status' => 'pending']) }}" class="db-hero-btn solid">
                    Review Applications
                    @if ($pendingSellerApps > 0)
                        <span class="rounded-full px-1.5 text-[11px] text-white" style="background:#fa4e1c;">{{ $pendingSellerApps }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.orders.index') }}" class="db-hero-btn ghost">
                    View Orders
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- ══════════ KEY NUMBERS ══════════ --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="db-card db-stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="db-stat-label">Gross Sales</p>
                    <p class="db-stat-value">₱{{ number_format($grossSales, 2) }}</p>
                </div>
                <span class="db-icon tone-orange">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>
                </span>
            </div>
            <p class="db-stat-foot">₱{{ number_format($salesMonth, 2) }} this month</p>
        </div>

        <div class="db-card db-stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="db-stat-label">Commission Earned</p>
                    <p class="db-stat-value">₱{{ number_format($commissionEarned, 2) }}</p>
                </div>
                <span class="db-icon tone-green">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                </span>
            </div>
            <p class="db-stat-foot">ALVY's fee on completed sales</p>
        </div>

        <div class="db-card db-stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="db-stat-label">Orders</p>
                    <p class="db-stat-value">{{ number_format($totalOrders) }}</p>
                </div>
                <span class="db-icon tone-navy">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16zM3.3 7 12 12l8.7-5M12 22V12"/></svg>
                </span>
            </div>
            <p class="db-stat-foot"><strong style="color:#222;">{{ $ordersMonth }}</strong> placed this month</p>
        </div>

        <div class="db-card db-stat">
            <div class="flex items-start justify-between">
                <div>
                    <p class="db-stat-label">Users</p>
                    <p class="db-stat-value">{{ number_format($totalUsers) }}</p>
                </div>
                <span class="db-icon tone-violet">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
            </div>
            <p class="db-stat-foot">
                <span style="color:#059669;">● {{ number_format($activeUsers) }} active</span>
                <span class="ml-2" style="color:#DC2626;">● {{ number_format($suspendedUsers) }} suspended</span>
            </p>
        </div>
    </section>

    {{-- ══════════ SECONDARY NUMBERS ══════════ --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Buyers',            number_format($totalBuyers),  number_format($activeBuyers) . ' active',                       route('admin.customers.index'),         'tone-navy'],
            ['Sellers',           number_format($totalSellers), number_format($activeSellers) . ' approved',                    route('admin.sellers.index'),           'tone-orange'],
            ['New Registrations', number_format($newToday),     "today · {$newWeek} this week · {$newMonth} this month",        route('admin.users.index'),             'tone-green'],
            ['Pending Requests',  number_format($pendingRequests), "{$pendingSellerApps} seller · {$pendingBuyerVerif} buyer",  route('admin.seller-applications.index'), 'tone-amber'],
        ] as [$label, $value, $foot, $href, $tone])
            <a href="{{ $href }}" class="db-card flex items-center gap-3 px-4 py-3.5 transition hover:-translate-y-0.5">
                <span class="db-icon {{ $tone }} !h-10 !w-10 font-display text-base font-extrabold">{{ $value }}</span>
                <span class="min-w-0">
                    <span class="block text-sm font-bold" style="color:#222;">{{ $label }}</span>
                    <span class="block truncate text-xs" style="color:#8a7a70;">{{ $foot }}</span>
                </span>
            </a>
        @endforeach
    </section>

    {{-- ══════════ SALES CHART + PENDING ══════════ --}}
    <section class="grid gap-6 lg:grid-cols-3">
        <div class="db-card lg:col-span-2">
            <div class="db-card-head">
                <div>
                    <h2 class="db-card-title">Sales</h2>
                    <p class="db-card-sub">Gross sales per month, last 6 months</p>
                </div>
                <a href="{{ route('admin.reports.index') }}" class="db-link">Reports →</a>
            </div>
            <div class="px-5 pb-5 pt-2">
                @include('partials.dash.area-chart', ['points' => $salesChart, 'id' => 'admin-sales', 'prefix' => '₱'])
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-head">
                <div>
                    <h2 class="db-card-title">Pending Applications</h2>
                    <p class="db-card-sub">Sellers waiting for review</p>
                </div>
                <a href="{{ route('admin.seller-applications.index', ['status' => 'pending']) }}" class="db-link">View all</a>
            </div>
            <div class="px-3 pb-4 pt-1">
                @forelse ($pendingList as $app)
                    <a href="{{ route('admin.seller-applications.show', $app->id) }}" class="db-row-link">
                        <span class="db-icon tone-orange !h-9 !w-9 text-sm font-extrabold">{{ strtoupper(mb_substr($app->shop_name ?? $app->full_name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold" style="color:#222;">{{ $app->shop_name ?? $app->full_name }}</span>
                            <span class="block text-xs" style="color:#a3948a;">{{ $app->full_name }} · {{ $app->created_at->diffForHumans() }}</span>
                        </span>
                        <span class="db-badge tone-amber">Pending</span>
                    </a>
                @empty
                    <div class="db-empty">
                        <span class="db-icon tone-green mx-auto mb-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        All caught up.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ══════════ RECENT ORDERS + ATTENTION ══════════ --}}
    <section class="grid gap-6 lg:grid-cols-3">
        <div class="db-card overflow-hidden lg:col-span-2">
            <div class="db-card-head pb-3">
                <div>
                    <h2 class="db-card-title">Recent Orders</h2>
                    <p class="db-card-sub">Latest purchases across the marketplace</p>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="db-link">All orders →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="db-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Buyer</th>
                            <th>Status</th>
                            <th class="!text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="font-semibold hover:underline" style="color:#222;">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</a>
                                    <p class="text-[11px]" style="color:#a3948a;">{{ $order->created_at->format('M d, Y · g:i A') }}</p>
                                </td>
                                <td>{{ $order->user->name ?? $order->full_name ?? 'Guest' }}</td>
                                <td><span class="db-badge {{ $statusTone($order->status) }}">{{ $order->status }}</span></td>
                                <td class="text-right font-bold" style="color:#222;">₱{{ number_format($order->total_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="db-empty">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="db-card">
                <div class="db-card-head">
                    <h2 class="db-card-title">Needs Attention</h2>
                    <a href="{{ route('admin.notifications.index') }}" class="db-link">View all</a>
                </div>
                <div class="px-3 pb-4 pt-1">
                    @forelse ($notifications as $n)
                        <a href="{{ $n['link'] }}" class="db-row-link">
                            <span class="db-icon {{ $n['type'] === 'warning' ? 'tone-red' : 'tone-orange' }} !h-9 !w-9">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg>
                            </span>
                            <span class="text-sm" style="color:#222;">{{ $n['title'] }}</span>
                        </a>
                    @empty
                        <p class="db-empty !py-6">Nothing needs your attention right now.</p>
                    @endforelse
                </div>
            </div>

            <div class="db-card">
                <div class="db-card-head">
                    <h2 class="db-card-title">Order Status</h2>
                </div>
                @php
                    $ostRows = collect(['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'])
                        ->map(fn ($s) => [$s, (int) ($ordersByStatus[$s] ?? 0)]);
                    $ostTotal = max(1, $ostRows->sum(fn ($r) => $r[1]));
                    $ostColor = ['Pending' => '#B45309', 'Processing' => '#fa4e1c', 'Shipped' => '#2563EB', 'Delivered' => '#059669', 'Cancelled' => '#DC2626'];
                @endphp
                <div class="space-y-3 px-5 pb-5 pt-1">
                    @foreach ($ostRows as [$s, $c])
                        <div>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-semibold" style="color:#3a2f29;">{{ $s }}</span>
                                <span class="font-bold" style="color:#222;">{{ $c }}</span>
                            </div>
                            <div class="db-progress"><span style="width:{{ round($c / $ostTotal * 100) }}%;background:{{ $ostColor[$s] }};"></span></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════ USER GROWTH + ACTIVITY ══════════ --}}
    <section class="grid gap-6 lg:grid-cols-2">
        <div class="db-card">
            <div class="db-card-head">
                <div>
                    <h2 class="db-card-title">User Growth</h2>
                    <p class="db-card-sub">New registrations, last 6 months</p>
                </div>
            </div>
            <div class="px-5 pb-5 pt-2">
                @include('partials.dash.area-chart', [
                    'points' => $growth->map(fn ($g) => (object) ['label' => $g->label, 'value' => $g->count]),
                    'id' => 'admin-growth', 'unit' => 'new users',
                ])
            </div>
            <div class="flex flex-wrap gap-2 border-t px-5 py-3" style="border-color:#F3ECE6;">
                @foreach ($systemStatus as $svc => $ok)
                    <span class="db-badge {{ $ok ? 'tone-green' : 'tone-red' }}">● {{ $svc }}</span>
                @endforeach
            </div>
        </div>

        <div class="db-card">
            <div class="db-card-head">
                <div>
                    <h2 class="db-card-title">Recent Activity</h2>
                    <p class="db-card-sub">What admins have been doing</p>
                </div>
                <a href="{{ route('admin.activity.index') }}" class="db-link">View log</a>
            </div>
            <div class="px-5 pb-4 pt-1">
                @forelse ($recentActivity as $log)
                    <div class="relative flex gap-3 pb-4 pl-1 last:pb-0">
                        @unless ($loop->last)
                            <span class="absolute left-[9px] top-5 bottom-0 w-px" style="background:#F0E4DC;"></span>
                        @endunless
                        <span class="relative mt-1 h-3 w-3 shrink-0 rounded-full ring-4" style="background:{{ $log->status === 'failed' ? '#DC2626' : '#fa4e1c' }};--tw-ring-color:#fff1ee;"></span>
                        <div class="min-w-0">
                            <p class="text-sm" style="color:#222;">
                                <strong>{{ $log->admin_name ?? 'System' }}</strong> — {{ $log->action_label ?? $log->action }}
                            </p>
                            @if ($log->description)
                                <p class="truncate text-xs" style="color:#8a7a70;">{{ $log->description }}</p>
                            @endif
                            <p class="text-[11px]" style="color:#b5a79d;">{{ $log->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="db-empty">No administrative activity recorded yet.</p>
                @endforelse
            </div>
        </div>
    </section>

</div>
</x-admin-layout>
