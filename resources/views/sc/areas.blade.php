@extends('sc.layout')
@section('title', 'Coverage Areas')
@section('icon', 'location')

@section('content')
<div class="page-header"><h1>Coverage Areas</h1></div>
<div class="page-body">

    @if(! $sc->assigned_municipality)
        <div class="card" style="padding:20px;text-align:center;">
            <div style="color:var(--accent-red);font-weight:600;margin-bottom:4px;">No municipality assigned</div>
            <div style="color:var(--text-muted);font-size:13px;">
                An administrator must assign your sorting center to a municipality before you can define barangay coverage.
            </div>
        </div>
    @else
        <div class="card" style="padding:16px 18px;margin-bottom:18px;">
            <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;font-weight:600;">Assigned Municipality</div>
            <div style="font-size:18px;font-weight:700;color:var(--text);margin-top:2px;">{{ $sc->assigned_municipality }}</div>
            <div style="font-size:12.5px;color:var(--text-muted);">{{ $sc->assigned_province }}</div>
        </div>

        <div style="display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start;">

            {{-- Add a barangay --}}
            <div class="card" style="padding:18px;">
                <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px;">Add Barangay</div>
                <form method="POST" action="{{ route('sc.areas.store') }}" id="area-form">
                    @csrf
                    <label class="form-label">Barangay</label>
                    <select id="brgy_select" class="form-select" style="margin-bottom:14px;">
                        <option value="">Loading barangays…</option>
                    </select>
                    <input type="hidden" name="barangay"      id="brgy_name">
                    <input type="hidden" name="barangay_code" id="brgy_code">
                    <button type="submit" class="btn btn-blue" style="width:100%;justify-content:center;gap:6px;">
                        @include('sc.partials.icon', ['name' => 'location', 'size' => 15, 'sw' => 2]) Add to Coverage
                    </button>
                </form>
                <p style="font-size:11.5px;color:var(--text-muted);margin-top:10px;">
                    Only barangays within {{ $sc->assigned_municipality }} can be added.
                </p>
            </div>

            {{-- Current coverage --}}
            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr><th>Barangay</th><th>Riders</th><th>Code</th><th style="text-align:right;">Action</th></tr>
                    </thead>
                    <tbody>
                    @forelse($areas as $area)
                        <tr>
                            <td style="font-weight:600;">{{ $area->name }}</td>
                            <td>{{ $area->riders_count }}</td>
                            <td><span class="text-muted text-sm">{{ $area->barangay_code ?? '—' }}</span></td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('sc.areas.destroy', $area) }}"
                                      onsubmit="return confirm('Remove {{ addslashes($area->name) }} from coverage?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" style="color:#DC2626;border-color:#f3c9c9;gap:4px;">
                                        @include('sc.partials.icon', ['name' => 'cross', 'size' => 13, 'sw' => 2.2]) Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--text-muted);">No barangays added yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        (function () {
            const municipalityCode = @json($sc->assigned_municipality_code);
            const sel  = document.getElementById('brgy_select');
            const name = document.getElementById('brgy_name');
            const code = document.getElementById('brgy_code');
            const existing = @json($areas->pluck('name')->map(fn($n) => strtolower($n))->values());

            function opt(v, label, c) {
                const o = document.createElement('option');
                o.value = v; o.textContent = label; if (c) o.dataset.code = c;
                return o;
            }

            async function loadBarangays() {
                sel.innerHTML = '';
                if (!municipalityCode) { sel.appendChild(opt('', 'No municipality code on file')); return; }
                sel.appendChild(opt('', 'Loading…'));
                try {
                    const res = await fetch(`/api/psgc/municipalities/${municipalityCode}/barangays`);
                    const list = await res.json();
                    sel.innerHTML = '';
                    sel.appendChild(opt('', 'Select barangay…'));
                    list.forEach(b => {
                        if (existing.includes(b.name.toLowerCase())) return; // hide already-added
                        sel.appendChild(opt(b.name, b.name, b.code));
                    });
                    if (sel.options.length === 1) {
                        sel.innerHTML = '';
                        sel.appendChild(opt('', 'All barangays already added'));
                    }
                    sync();
                } catch (e) {
                    sel.innerHTML = '';
                    sel.appendChild(opt('', 'Failed to load barangays'));
                }
            }

            function sync() {
                const o = sel.options[sel.selectedIndex];
                name.value = sel.value;
                code.value = o ? (o.dataset.code || '') : '';
            }

            sel.addEventListener('change', sync);
            document.getElementById('area-form').addEventListener('submit', function (e) {
                if (!name.value) { e.preventDefault(); alert('Please select a barangay.'); }
            });

            loadBarangays();
        })();
        </script>
    @endif
</div>
@endsection
