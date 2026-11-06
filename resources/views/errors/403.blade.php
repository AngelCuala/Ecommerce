@php
    $msg = trim((string) ($exception?->getMessage() ?? ''));
    // "Unauthorized. Sorting Center access only." → the part that says whose page it is.
    $msg = trim(preg_replace('/^(Unauthorized|Forbidden)\.?\s*/i', '', $msg));
    $me = auth()->user();
@endphp
@include('errors.alvy', [
    'code' => 403,
    'heading' => 'This page is not for your account',
    'text' => ($msg !== '' && $msg !== 'This action is unauthorized.' ? rtrim($msg, '.') . '. ' : '')
        . ($me ? 'You are logged in as ' . $me->name . '. Log in with the right account in this browser to open it.' : 'Log in with the right account to open it.'),
])
