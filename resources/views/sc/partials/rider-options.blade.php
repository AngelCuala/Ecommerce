{{-- Options for a "Choose rider" list: available riders to pick, then the hub's other riders greyed out with why.
     $bulky: the parcel is bulky, so only car and van riders can be picked. --}}
@php
    $except = $except ?? null;
    $bulky = $bulky ?? false;
    $list = $riderChoices->reject(fn ($c) => $c->rider->id === $except)
        ->map(fn ($c) => $bulky && $c->available && ! $c->rider->canCarryBulky() ? (object) ['rider' => $c->rider, 'available' => false, 'why' => 'bulky parcel needs a car or van'] : $c);
@endphp
<option value="">{{ $placeholder ?? 'Choose rider…' }}</option>
@if ($list->where('available', true)->isNotEmpty())
    <optgroup label="Available">
        @foreach ($list->where('available', true) as $c)
            <option value="{{ $c->rider->id }}" data-available>{{ $c->rider->full_name }} · {{ $c->rider->vehicle_type ?: 'Motorcycle' }}{{ $c->rider->area ? ' · ' . $c->rider->barangayNames() : '' }} ({{ $c->rider->active_jobs }} {{ \Illuminate\Support\Str::plural('job', $c->rider->active_jobs) }}){{ $c->rider->online_now ? '' : ' · offline' }}</option>
        @endforeach
    </optgroup>
@endif
@if ($list->where('available', false)->isNotEmpty())
    <optgroup label="Not available">
        @foreach ($list->where('available', false) as $c)
            <option value="{{ $c->rider->id }}" disabled>{{ $c->rider->full_name }} · {{ $c->why }}</option>
        @endforeach
    </optgroup>
@endif
