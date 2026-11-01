@extends('sc.layout')
@section('title', 'Coverage Areas')
@section('icon', 'location')

@php
    $covered   = $areas->count();
    $pct       = $barangayTotal ? (int) round($covered / $barangayTotal * 100) : 0;
    $noRider   = $areas->where('riders_count', 0)->count();
    $riders    = (int) $areas->sum('riders_count');
@endphp

@section('content')
<style>
    .cv-head { display: grid; gap: 18px; grid-template-columns: minmax(0, 1.3fr) repeat(3, minmax(0, 1fr)); align-items: stretch; margin-bottom: 18px; }
    @media (max-width: 1000px) { .cv-head { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cv-muni { padding: 16px 18px; }
    .cv-k { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); }
    .cv-bar { height: 8px; margin-top: 10px; border-radius: 999px; background: #EEF2F6; overflow: hidden; }
    .cv-bar i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #fb7048, #d93d0e); }
    .cv-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
    .cv-stat-ic { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 11px; }
    .cv-stat-n { font-size: 24px; font-weight: 800; line-height: 1; color: var(--text); }
    .cv-stat-l { margin-top: 3px; font-size: 12px; color: var(--text-muted); }

    .cv-grid { display: grid; gap: 18px; grid-template-columns: 320px minmax(0, 1fr); align-items: start; }
    @media (max-width: 1000px) { .cv-grid { grid-template-columns: 1fr; } }
    .cv-done { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 18px 8px 6px; text-align: center; font-size: 13px; color: var(--text-muted); }
    .cv-done span { display: flex; width: 44px; height: 44px; align-items: center; justify-content: center; border-radius: 999px; background: #E9F8EF; color: var(--accent-green); }

    .cv-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
    .cv-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; border: 1px solid var(--border); background: #fff; padding: 5px 11px; font-size: 12.5px; font-weight: 600; color: var(--text-sub); cursor: pointer; }
    .cv-chip b { border-radius: 999px; background: #F1F5F9; padding: 0 7px; font-size: 11px; color: var(--text-muted); }
    .cv-chip.is-on { border-color: var(--accent-blue); background: var(--accent-blue); color: #fff; }
    .cv-chip.is-on b { background: rgba(255,255,255,.22); color: #fff; }
    .cv-search { margin-left: auto; width: 220px; max-width: 100%; }
    .cv-list { max-height: 640px; overflow-y: auto; }
    .cv-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #F1F5F9; }
    .cv-row:last-child { border-bottom: 0; }
    .cv-row:hover { background: #FAFBFC; }
    .cv-pin { display: flex; width: 32px; height: 32px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 9px; background: #FFF4E5; color: var(--accent-orange); }
    .cv-name { font-size: 13.5px; font-weight: 700; color: var(--text); }
    .cv-code { margin-top: 1px; font-family: 'SF Mono', Consolas, monospace; font-size: 11px; color: var(--text-muted); }
    .cv-riders { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 2px 9px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
</style>

<div class="page-header"><h1>Coverage Areas</h1></div>
<div class="page-body">

    @if (! $sc->assigned_municipality)
        <div class="card" style="padding:26px;text-align:center;">
            <div style="color:var(--accent-red);font-weight:700;margin-bottom:4px;">No municipality assigned</div>
            <div style="color:var(--text-muted);font-size:13px;">An administrator must assign your sorting center to a municipality before you can define barangay coverage.</div>
        </div>
    @else
        {{-- Municipality and coverage --}}
        <div class="cv-head" data-coverage-summary>
            <div class="card cv-muni">
                <div class="cv-k">Assigned municipality</div>
                <div style="margin-top:2px;font-size:19px;font-weight:800;color:var(--text);">{{ $sc->assigned_municipality }}</div>
                <div style="font-size:12.5px;color:var(--text-muted);">{{ $sc->assigned_province }} · {{ $sc->name }}</div>
                <div class="cv-bar" aria-hidden="true"><i style="width:{{ $pct }}%;"></i></div>
                <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:12px;color:var(--text-muted);">
                    <span><b style="color:var(--text);">{{ $covered }}</b> of {{ $barangayTotal }} PSA barangays covered</span><span>{{ $pct }}%</span>
                </div>
            </div>
            @foreach ([
                ['Barangays covered', $covered, 'location', '#FFF4E5', 'var(--accent-orange)'],
                ['Riders assigned', $riders, 'rider', '#EAF2FB', 'var(--accent-blue)'],
                ['Barangays with no rider', $noRider, 'report', $noRider ? '#FFFBEB' : '#E9F8EF', $noRider ? '#B45309' : 'var(--accent-green)'],
            ] as [$label, $n, $ic, $bg, $fg])
                <div class="card cv-stat">
                    <span class="cv-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 19, 'sw' => 2])</span>
                    <div><div class="cv-stat-n">{{ $n }}</div><div class="cv-stat-l">{{ $label }}</div></div>
                </div>
            @endforeach
        </div>

        <div class="cv-grid">
            {{-- Add a barangay (PSA PSGC barangays of the assigned municipality only) --}}
            <div class="card" style="padding:18px;" data-coverage-add>
                <div style="font-size:14px;font-weight:700;color:var(--text);">Add barangay</div>
                <p style="font-size:12px;color:var(--text-muted);margin:2px 0 14px;">Official PSA PSGC barangays of {{ $sc->assigned_municipality }} only.</p>
                @if ($barangayTotal > 0 && empty($barangays))
                    <div class="cv-done">
                        <span>@include('sc.partials.icon', ['name' => 'check', 'size' => 22, 'sw' => 2.4])</span>
                        <b style="color:var(--text);">Every barangay is covered</b>
                        All {{ $barangayTotal }} barangays of {{ $sc->assigned_municipality }} are in your coverage.
                    </div>
                @else
                    <form method="POST" action="{{ route('sc.areas.store') }}">
                        @csrf
                        <label class="form-label" for="brgy_select">Barangay</label>
                        <select id="brgy_select" name="barangay_psgc" class="form-select" style="width:100%;margin-bottom:12px;" required @disabled(empty($barangays))>
                            @if ($barangayTotal === 0)
                                <option value="">Municipality not found in the PSA dataset</option>
                            @else
                                <option value="">Select barangay… ({{ count($barangays) }} not yet covered)</option>
                                @foreach ($barangays as $b)
                                    <option value="{{ $b['psgc'] }}" @selected(old('barangay_psgc') === $b['psgc'])>{{ $b['name'] }}</option>
                                @endforeach
                            @endif
                        </select>
                        <button type="submit" class="btn btn-blue" style="width:100%;justify-content:center;gap:6px;" @disabled(empty($barangays))>
                            @include('sc.partials.icon', ['name' => 'location', 'size' => 15, 'sw' => 2]) Add to coverage
                        </button>
                    </form>
                @endif
                @if ($noRider)
                    <div style="margin-top:14px;border-radius:10px;background:#FFFBEB;border:1px solid #FDE68A;padding:10px 12px;font-size:12px;color:#92400E;">
                        {{ $noRider }} {{ \Illuminate\Support\Str::plural('barangay', $noRider) }} {{ $noRider === 1 ? 'has' : 'have' }} no rider, so parcels there can't be assigned.
                        <a href="{{ route('sc.riders') }}" style="font-weight:700;color:#B45309;">Add riders &rarr;</a>
                    </div>
                @endif
            </div>

            {{-- Current coverage --}}
            <div class="card" data-coverage-list>
                <div class="card-header">
                    <h2>Your coverage</h2>
                    <span class="card-meta">{{ $covered }} {{ \Illuminate\Support\Str::plural('barangay', $covered) }}</span>
                </div>
                @if ($areas->isEmpty())
                    <div style="padding:34px;text-align:center;color:var(--text-muted);font-size:13px;">No barangays added yet. Add the first one on the left.</div>
                @else
                    <div class="cv-tools">
                        <button type="button" class="cv-chip is-on" data-cv-filter="all">All <b>{{ $covered }}</b></button>
                        <button type="button" class="cv-chip" data-cv-filter="none">No rider <b>{{ $noRider }}</b></button>
                        <input type="search" class="form-input cv-search" placeholder="Find a barangay…" data-cv-search aria-label="Find a barangay">
                    </div>
                    <div class="cv-list">
                        @foreach ($areas as $area)
                            <div class="cv-row" data-cv-row data-name="{{ strtolower($area->name) }}" data-riders="{{ $area->riders_count }}">
                                <span class="cv-pin">@include('sc.partials.icon', ['name' => 'location', 'size' => 15, 'sw' => 2])</span>
                                <div style="min-width:0;flex:1;">
                                    <div class="cv-name">{{ $area->name }}</div>
                                    <div class="cv-code">{{ $area->barangay_code ?? 'No PSGC code' }}</div>
                                </div>
                                @if ($area->riders_count)
                                    <span class="cv-riders" style="background:#E9F8EF;color:#047857;">{{ $area->riders_count }} {{ \Illuminate\Support\Str::plural('rider', $area->riders_count) }}</span>
                                @else
                                    <a href="{{ route('sc.riders') }}" class="cv-riders" style="background:#FFFBEB;color:#B45309;text-decoration:none;">No rider</a>
                                @endif
                                <form method="POST" action="{{ route('sc.areas.destroy', $area) }}"
                                      data-confirm="{{ $area->name }} will no longer be in your coverage." data-confirm-title="Remove {{ $area->name }}?" data-confirm-ok="Remove">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#f3c9c9;gap:4px;" aria-label="Remove {{ $area->name }}">
                                        @include('sc.partials.icon', ['name' => 'cross', 'size' => 13, 'sw' => 2.2]) Remove
                                    </button>
                                </form>
                            </div>
                        @endforeach
                        <div data-cv-empty hidden style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No barangays match.</div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    var rows = document.querySelectorAll('[data-cv-row]');
    if (!rows.length) return;
    var search = document.querySelector('[data-cv-search]'), filter = 'all';
    function apply() {
        var q = (search.value || '').trim().toLowerCase(), shown = 0;
        rows.forEach(function (r) {
            var on = (!q || r.dataset.name.indexOf(q) !== -1) && (filter === 'all' || r.dataset.riders === '0');
            r.style.display = on ? '' : 'none';
            if (on) shown++;
        });
        document.querySelector('[data-cv-empty]').hidden = shown > 0;
    }
    search.addEventListener('input', apply);
    document.querySelectorAll('[data-cv-filter]').forEach(function (b) {
        b.addEventListener('click', function () {
            filter = b.dataset.cvFilter;
            document.querySelectorAll('[data-cv-filter]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            apply();
        });
    });
})();
</script>
@endsection
