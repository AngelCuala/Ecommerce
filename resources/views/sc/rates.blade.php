@extends('sc.layout')
@section('title', 'Zones & Rates')
@section('icon', 'location')

@section('content')
<div class="page-header"><h1>Delivery Zones &amp; Shipping Rates</h1></div>
<div class="page-body">
    <p class="text-muted" style="margin:-4px 0 16px;font-size:13px;">
        Fee = first kg + each additional kg × (chargeable kg − 1). A change applies to checkouts started after it; orders already placed keep their fee.
    </p>
    @if($errors->any())
        <div class="card" style="padding:10px 14px;margin-bottom:12px;border-left:3px solid var(--accent-red);color:var(--accent-red);font-size:13px;">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="margin-bottom:16px;">
        <table class="data-table">
            <thead><tr><th>Route</th><th>First kg (₱)</th><th>Each additional kg (₱)</th><th></th></tr></thead>
            <tbody>
            @foreach($rates as $route => [$first, $extra])
                @php $fid = 'rate-' . \Illuminate\Support\Str::slug($route); @endphp
                <tr data-route="{{ $route }}">
                    <td>{{ str_replace('-', ' → ', $route) }}</td>
                    <td><input form="{{ $fid }}" name="first_kg" type="number" step="0.01" min="0.01" value="{{ number_format($first, 2, '.', '') }}" class="form-input" style="width:110px;" aria-label="First kg for {{ $route }}"></td>
                    <td><input form="{{ $fid }}" name="additional_kg" type="number" step="0.01" min="0.01" value="{{ number_format($extra, 2, '.', '') }}" class="form-input" style="width:110px;" aria-label="Additional kg for {{ $route }}"></td>
                    <td>
                        <form id="{{ $fid }}" method="POST" action="{{ route('sc.rates.update') }}">@csrf
                            <input type="hidden" name="route" value="{{ $route }}">
                            <button class="btn btn-blue btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card" style="padding:16px 18px;">
        <div style="font-weight:700;margin-bottom:8px;">Zones</div>
        @foreach($zones as $z)
            <div style="font-size:13px;" data-zone="{{ $z->province_name }}">{{ $z->name }} ({{ $z->province_name }}) · ₱{{ number_format($z->first_kg, 2) }} + ₱{{ number_format($z->additional_kg, 2) }}/kg</div>
        @endforeach
        @if($uncovered->isNotEmpty())
            <p class="text-muted" style="font-size:12.5px;margin:10px 0 6px;">Not serviceable yet: {{ $uncovered->implode(', ') }}. Add a zone to start delivering there.</p>
            <form method="POST" action="{{ route('sc.zones.store') }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;">@csrf
                <div><label class="form-label" for="zone-province">Province</label>
                    <select id="zone-province" name="province_code" class="form-select" required>
                        @foreach($uncovered as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                    </select></div>
                <div><label class="form-label" for="zone-first">First kg (₱)</label><input id="zone-first" name="first_kg" type="number" step="0.01" min="0.01" class="form-input" style="width:110px;" required></div>
                <div><label class="form-label" for="zone-extra">Each additional kg (₱)</label><input id="zone-extra" name="additional_kg" type="number" step="0.01" min="0.01" class="form-input" style="width:110px;" required></div>
                <button class="btn btn-green btn-sm">Add zone</button>
            </form>
        @endif
    </div>
</div>
@endsection
