{{-- Result of the automatic "is this an ID?" check. @include('partials.id-check-note', ['record' => $model]) --}}
@if ($record->id_check_status === 'passed')
    <p class="mt-2 text-xs" style="color:#047857;">
        Automatic check: looks like {{ $record->id_check_note ?: 'a government-issued ID' }}.
    </p>
@elseif ($record->id_check_status === 'unchecked')
    <p class="mt-2 rounded-md px-2.5 py-1.5 text-xs" style="background:#FFF7ED;color:#9A3412;">
        Not checked automatically{{ $record->id_check_note ? ' (' . $record->id_check_note . ')' : '' }}. Please confirm it's a valid ID.
    </p>
@endif
