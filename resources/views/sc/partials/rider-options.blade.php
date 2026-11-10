{{-- Options for a "Choose rider" list: available riders to pick, then the hub's other riders greyed out with why. --}}
@php $except = $except ?? null; $list = $riderChoices->reject(fn ($c) => $c->rider->id === $except); @endphp
<option value="">{{ $placeholder ?? 'Choose rider…' }}</option>
@if ($list->where('available', true)->isNotEmpty())
    <optgroup label="Available">
        @foreach ($list->where('available', true) as $c)
            <option value="{{ $c->rider->id }}" data-available>{{ $c->rider->full_name }}{{ $c->rider->area ? ' · ' . $c->rider->barangayNames() : '' }} ({{ $c->rider->active_jobs }} {{ \Illuminate\Support\Str::plural('job', $c->rider->active_jobs) }}){{ $c->rider->online_now ? '' : ' · offline' }}</option>
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
