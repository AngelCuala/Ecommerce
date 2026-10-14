<x-admin-layout title="Rider Management" active="people-riders">
@include('partials.panel.plain')
<style>
    .adm-avatar { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 9999px; font-size: .8125rem; font-weight: 600; background: #F5F2EF; color: #57534E; }
    .adm-action { font-size: .8125rem; font-weight: 600; color: #57534E; }
    .adm-action:hover { color: #222; text-decoration: underline; }
    .rd-stat { display: block; padding: 1rem 1.25rem; transition: box-shadow .15s, border-color .15s; }
    .rd-stat:hover { box-shadow: 0 2px 10px rgba(0,0,0,.06); }
    .rd-stat.is-active { border-color: #fa4e1c; }
</style>

<div class="mx-auto max-w-6xl">

    {{-- Stat boxes (click to filter) --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['',         'All Riders', $stats['total'],    '#222'],
            ['active',   'Active',     $stats['active'],   '#059669'],
            ['pending',  'Pending',    $stats['pending'],  '#B45309'],
            ['inactive', 'Inactive',   $stats['inactive'], '#B91C1C'],
        ] as [$key, $label, $count, $color])
            <a href="{{ route('admin.riders.index', array_filter(['status' => $key, 'center' => request('center'), 'search' => request('search')])) }}"
               class="pl-card rd-stat {{ (string) request('status') === $key ? 'is-active' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-wide pl-muted">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};">{{ number_format($count) }}</p>
            </a>
        @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search name, phone, or email" class="input !py-1.5 !pl-8 text-sm" style="width:17rem;">
        </div>
        <select name="center" class="input !py-1.5 text-sm" style="width:13rem;">
            <option value="">All sorting centers</option>
            @foreach ($centers as $c)
                <option value="{{ $c->id }}" @selected((string) request('center') === (string) $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <button class="pl-btn-line !py-1.5">Search</button>
        @if (request('search') || request('status') || request('center'))
            <a href="{{ route('admin.riders.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
        @endif
    </form>

    {{-- Riders --}}
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Contact / Vehicle</th>
                        <th>Sorting Center</th>
                        <th>Barangay</th>
                        <th>Status</th>
                        <th>Deliveries</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riders as $r)
                        @php
                            [$statusText, $statusTag] = match (true) {
                                $r->application_status === 'pending'  => ['Pending', 'pending'],
                                $r->application_status === 'rejected' => ['Rejected', 'cancelled'],
                                ! $r->is_active                       => ['Inactive', 'cancelled'],
                                default                               => ['Active', 'delivered'],
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="adm-avatar">{{ strtoupper(substr($r->full_name ?? 'R', 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium" style="color:#222;">{{ $r->full_name }}</p>
                                        <p class="break-all text-xs pl-muted">{{ $r->user->email ?? '—' }}</p>
                                        <p class="text-xs pl-muted">Joined {{ $r->created_at?->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $r->phone ?: '—' }}
                                <p class="text-xs pl-muted">{{ ucfirst($r->vehicle_type ?? '—') }}</p>
                            </td>
                            <td>{{ $r->sortingCenter->name ?? '—' }}</td>
                            <td>{{ $r->area ? 'Brgy. ' . $r->area->name : '—' }}</td>
                            <td><span class="pl-tag {{ $statusTag }}">{{ $statusText }}</span></td>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold" style="color:#222;">{{ $r->delivered_count }}</span>
                                <span class="pl-muted">/ {{ $r->deliveries_count }} delivered</span>
                                @if ($r->open_count)
                                    <p class="text-xs" style="color:#B45309;">{{ $r->open_count }} on hand</p>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($r->user_id)
                                    <a href="{{ route('admin.users.show', $r->user_id) }}" class="adm-action">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="!py-10 text-center pl-muted">No riders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $riders->links() }}</div>
</div>

</x-admin-layout>
