<x-layout title="Activate your account — ALVY">
<div class="mx-auto max-w-md px-4 py-10">
    <h1 class="font-display text-2xl font-extrabold mb-2" style="color:#222222;">Activate your account</h1>

    <div class="card p-6">
        @if ($done)
            <p class="text-sm font-semibold mb-2" style="color:#15803D;">Your password is saved and your account is now active.</p>
            <p class="text-sm mb-5" style="color:#444444;">Open the ALVY app and log in with <strong>{{ $user->email }}</strong> and your new password.</p>
            {{-- Opens the installed app; without it, Android goes to the download page. --}}
            <a href="intent://login#Intent;scheme=alvy;package=com.example.alvy_app;S.browser_fallback_url={{ urlencode(route('app.page')) }};end"
               class="block w-full rounded-xl px-6 py-3 text-center text-sm font-semibold text-white" style="background:#fa4e1c;">Open the ALVY app</a>
            <a href="{{ route('app.page') }}" class="mt-3 block text-center text-sm" style="color:#6b90aa;">Don't have the app yet? Download it</a>
        @elseif ($problem)
            <p class="text-sm mb-5" style="color:#DC2626;">{{ $problem }}</p>
            <a href="intent://login#Intent;scheme=alvy;package=com.example.alvy_app;S.browser_fallback_url={{ urlencode(route('app.page')) }};end"
               class="block w-full rounded-xl px-6 py-3 text-center text-sm font-semibold text-white" style="background:#fa4e1c;">Open the ALVY app</a>
        @else
            <p class="text-sm mb-5" style="color:#444444;">Hi {{ $user->first_name ?: $user->name }}, choose the password for <strong>{{ $user->email }}</strong>.</p>
            <form method="POST" action="{{ route('account.activate.store', $token) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="password" class="block text-sm font-semibold mb-1" style="color:#222222;">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="w-full rounded-lg border px-3 py-2">
                    <p class="mt-1 text-xs" style="color:#6b90aa;">At least 8 characters, including an uppercase letter, a lowercase letter, a number and a symbol.</p>
                    @error('password')<p class="mt-1 text-xs" style="color:#DC2626;">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold mb-1" style="color:#222222;">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-lg border px-3 py-2">
                </div>
                <button type="submit" class="w-full rounded-xl px-6 py-3 text-sm font-semibold text-white" style="background:#fa4e1c;">Activate</button>
            </form>
        @endif
    </div>
</div>
</x-layout>
