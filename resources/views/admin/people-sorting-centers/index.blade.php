<x-admin-layout title="Sorting Centers" active="people-sorting-centers">

@include('admin.partials.people-tabs')
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
            ['',          'All Sorting Centers', $stats['total'],     '#222'],
            ['active',    'Active',              $stats['active'],    '#059669'],
            ['pending',   'Pending',             $stats['pending'],   '#B45309'],
            ['suspended', 'Suspended',           $stats['suspended'], '#B91C1C'],
        ] as [$key, $label, $count, $color])
            <a href="{{ route('admin.sorting-centers.index', array_filter(['status' => $key, 'search' => request('search')])) }}"
               class="pl-card rd-stat {{ (string) request('status') === $key ? 'is-active' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-wide pl-muted">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};">{{ number_format($count) }}</p>
            </a>
        @endforeach
    </div>

    {{-- Search --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search company, contact, email or town" class="input !py-1.5 !pl-8 text-sm" style="width:21rem;max-width:100%;">
        </div>
        <button class="pl-btn-line !py-1.5">Search</button>
        @if (request('search') || request('status'))
            <a href="{{ route('admin.sorting-centers.index') }}" class="text-sm font-medium hover:underline pl-muted">Clear</a>
        @endif
    </form>

    {{-- Sorting centers --}}
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th style="min-width:14rem;">Sorting Center</th>
                        <th>Contact</th>
                        <th>Covers</th>
                        <th>Hubs / Barangays</th>
                        <th>Riders</th>
                        <th>Status</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($centers as $c)
                        @php
                            [$statusText, $statusTag] = \App\Http\Controllers\Admin\SortingCenterDirectoryController::statusOf($c);
                            $company = $c->business_name ?: $c->name;
                        @endphp
                        <tr data-sorting-center="{{ $c->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="adm-avatar">{{ strtoupper(mb_substr($company, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-medium" style="color:#222;">{{ $company }}</p>
                                        <p class="truncate text-xs pl-muted" style="max-width:14rem;" title="{{ $c->email }}">{{ $c->email }}</p>
                                        <p class="text-xs pl-muted">Joined {{ $c->created_at?->format('M d, Y') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $c->name }}
                                <p class="text-xs pl-muted">{{ $c->contact_no ?: ($c->phone ?: '—') }}</p>
                            </td>
                            <td>
                                {{ $c->assigned_municipality ?: '—' }}
                                @if ($c->assigned_province)<p class="text-xs pl-muted">{{ $c->assigned_province }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold" style="color:#222;">{{ $c->hubs_count }}</span> <span class="pl-muted">{{ \Illuminate\Support\Str::plural('hub', $c->hubs_count) }}</span>
                                <p class="text-xs pl-muted">{{ $c->delivery_areas_count }} {{ \Illuminate\Support\Str::plural('barangay', $c->delivery_areas_count) }}</p>
                            </td>
                            <td class="whitespace-nowrap">
                                {{-- The count opens this company's riders. --}}
                                @if ($c->riders_count)
                                    <a href="{{ route('admin.riders.index', ['center' => $c->id]) }}" class="hover:underline" title="See this sorting center's riders">
                                        <span class="font-semibold" style="color:#222;">{{ $c->active_riders_count }}</span>
                                        <span class="pl-muted">/ {{ $c->riders_count }} active</span>
                                    </a>
                                @else
                                    <span class="pl-muted">None yet</span>
                                @endif
                            </td>
                            <td><span class="pl-tag {{ $statusTag }}">{{ $statusText }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                @if ($c->application_id)
                                    <a href="{{ route('admin.sc-applications.show', $c->application_id) }}" class="adm-action mr-3">Application</a>
                                @endif
                                <a href="{{ route('admin.users.show', $c->id) }}" class="adm-action">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="!py-10 text-center pl-muted">No sorting centers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $centers->links() }}</div>
</div>

</x-admin-layout>
