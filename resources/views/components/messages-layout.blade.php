@props(['title' => 'Messages', 'active' => 'messages'])
@php $role = auth()->user()?->role; @endphp

@if ($role === 'admin')
    <x-admin-layout :title="$title" :active="$active">
        {{ $slot }}
    </x-admin-layout>
@elseif ($role === 'seller')
    <x-seller-layout :title="$title" :active="$active">
        {{ $slot }}
    </x-seller-layout>
@else
    <x-layout :title="$title . ' — ALVY'">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </x-layout>
@endif
